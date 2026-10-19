<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\Validator;
use Domain\CsvCell;
use Domain\Period;
use PDO;
use Repositories\ImportRepository;
use Repositories\KpiRepository;
use Repositories\UnitRepository;
use Repositories\ValueRepository;

/**
 * Wide CSV import: each row is one unit at one date, each mapped column one KPI.
 *
 * Mapping (JSON):
 *   unit_columns   ["Region", "Division", "School_Name"]   one or more columns
 *   unit_match     "path" (walk names down from parent_unit_id; default),
 *                  "name" (one column; a leaf with that name anywhere under the parent),
 *                  "code" (one column; unit code)
 *   unit_kinds     optional kind for units created at each path level (default: column name)
 *   parent_unit_id where paths start (default: the organisation root)
 *   create_missing_units  create units that don't exist yet (admins only)
 *   date_column, date_format  one of Domain\CsvCell::DATE_FORMATS
 *   columns        {"CSV header": "kpi_code", ...}
 *
 * preview() and commit() run the same analysis; commit refuses if there are errors unless
 * skip_invalid is set, in which case only the valid values are written.
 */
class ImportService
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_ROWS = 20000;
    private const MAX_ERRORS = 200;

    private PDO $db;
    private Auth $auth;
    private AccessService $access;
    private ValueService $valueRules;
    private KpiRepository $kpis;
    private UnitRepository $units;
    private ValueRepository $values;
    private ImportRepository $imports;

    public function __construct(PDO $db, Auth $auth, AccessService $access, ValueService $valueRules, KpiRepository $kpis,
                                UnitRepository $units, ValueRepository $values, ImportRepository $imports)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->access = $access;
        $this->valueRules = $valueRules;
        $this->kpis = $kpis;
        $this->units = $units;
        $this->values = $values;
        $this->imports = $imports;
    }

    public function preview(string $path, array $mapping): array
    {
        $plan = $this->analyze($path, $mapping);
        $counts = $this->changeCounts($plan);

        return [
            'rows_total' => $plan['rows_total'],
            'values_total' => count($plan['entries']),
            'empty_cells' => $plan['empty_cells'],
            'will_create' => $counts['created'],
            'will_update' => $counts['updated'],
            'unchanged' => $counts['unchanged'],
            'new_units' => array_values(array_map(fn($u) => $u['label'], $plan['new_units'])),
            'errors' => array_slice($plan['errors'], 0, self::MAX_ERRORS),
            'error_count' => count($plan['errors']),
            'sample' => array_map(fn($e) => [
                'row' => $e['row'],
                'unit' => $e['unit_label'],
                'kpi' => $e['kpi']['code'],
                'period_start' => $e['period'],
                'period_label' => Period::label($e['period'], $e['kpi']['frequency']),
                'value' => $e['value'],
            ], array_slice($plan['entries'], 0, 20)),
        ];
    }

    public function commit(string $path, string $filename, array $mapping, bool $skipInvalid): array
    {
        $user = $this->auth->requireUser();
        $plan = $this->analyze($path, $mapping);
        if ($plan['errors'] && !$skipInvalid) {
            throw HttpException::badRequest('The file has problems; nothing was imported', [
                'errors' => array_slice($plan['errors'], 0, self::MAX_ERRORS),
                'error_count' => count($plan['errors']),
            ]);
        }
        if (!$plan['entries']) {
            throw HttpException::badRequest('There are no values to import');
        }

        $this->db->beginTransaction();
        try {
            $unitIds = $this->createUnits($plan['new_units']);
            $importId = $this->imports->start($this->auth->organizationId(), $user['id'], mb_substr($filename, 0, 255), $plan['rows_total']);
            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
            foreach ($plan['entries'] as $e) {
                $unitId = is_int($e['unit']) ? $e['unit'] : $unitIds[$e['unit']];
                $counts[$this->values->upsert($e['kpi']['id'], $unitId, $e['period'], $e['value'], null, $importId, $user['id'])]++;
            }
            $this->imports->finish($importId, $counts['created'], $counts['updated']);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->access->refreshTree();

        return [
            'import_id' => $importId,
            'values_created' => $counts['created'],
            'values_updated' => $counts['updated'],
            'unchanged' => $counts['unchanged'],
            'units_created' => count($unitIds),
            'skipped_errors' => count($plan['errors']),
        ];
    }

    public function recent(): array
    {
        return $this->imports->recent($this->auth->organizationId());
    }

    // ---- analysis -------------------------------------------------------------

    private function analyze(string $path, array $mapping): array
    {
        $user = $this->auth->requireUser();
        if (!is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw HttpException::badRequest('Upload a CSV file of at most 5 MB');
        }
        [$headers, $rows] = CsvReader::read($path, self::MAX_ROWS);
        $m = $this->validateMapping($mapping, $headers, $user);

        $plan = ['rows_total' => count($rows), 'entries' => [], 'errors' => [], 'new_units' => [], 'empty_cells' => 0];
        $resolver = new UnitResolver($this->access->tree(), $m['parent_unit_id'], $m['create_missing_units'], $this->auth->organization()['unit_label']);
        $seen = [];
        $finalUnits = [];   // unit ref => first row using it for values

        foreach ($rows as $rowNo => $cells) {
            $err = function (string $message, ?string $column = null) use (&$plan, $rowNo): void {
                $plan['errors'][] = ['row' => $rowNo, 'column' => $column, 'message' => $message];
            };

            $dateText = $cells[$m['date_index']] ?? '';
            $date = CsvCell::date($dateText, $m['date_format']);
            if ($date === null) {
                $err("'{$dateText}' is not a valid {$m['date_format']} date", $m['date_column']);
                continue;
            }

            $names = array_map(fn($i) => trim($cells[$i] ?? ''), $m['unit_indexes']);
            try {
                [$unitRef, $unitLabel] = $resolver->resolve($m['unit_match'], $names, $m['unit_kinds']);
            } catch (\DomainException $e) {
                $err($e->getMessage(), implode(' > ', $m['unit_columns']));
                continue;
            }
            if (is_int($unitRef)) {
                $role = $this->access->roleFor($unitRef);
                if (!in_array($role, ['contributor', 'admin'], true)) {
                    $err("You cannot enter data for {$unitLabel}");
                    continue;
                }
            }
            $unit = is_int($unitRef) ? $this->access->tree()->get($unitRef) : ['name' => $unitLabel, 'kind' => $resolver->plannedKind($unitRef), 'is_active' => true];
            $finalUnits[$unitRef] ??= $rowNo;

            foreach ($m['kpi_columns'] as $index => [$column, $kpi]) {
                $raw = trim($cells[$index] ?? '');
                if ($raw === '') {
                    $plan['empty_cells']++;
                    continue;
                }
                $value = CsvCell::number($raw);
                if ($value === null) {
                    $err("'{$raw}' is not a number", $column);
                    continue;
                }
                $problem = $this->valueRules->entryProblem(
                    $kpi, $unit, is_int($unitRef) ? $this->access->tree()->isLeaf($unitRef) : true, $resolver->existingAncestor($unitRef)
                );
                if ($problem) {
                    $err($problem, $column);
                    continue;
                }
                $period = Period::align($date, $kpi['frequency']);
                $key = "{$kpi['id']}|{$unitRef}|{$period}";
                if (isset($seen[$key])) {
                    $err("Repeats row {$seen[$key]}: same place, KPI and period", $column);
                    continue;
                }
                $seen[$key] = $rowNo;
                $plan['entries'][] = ['row' => $rowNo, 'kpi' => $kpi, 'unit' => $unitRef, 'unit_label' => $unitLabel, 'period' => $period, 'value' => $value];
            }
        }

        // A unit can't both hold values and have new units under it
        foreach ($resolver->parentsOfNewUnits() as $ref => $label) {
            if (isset($finalUnits[$ref])) {
                $plan['errors'][] = ['row' => $finalUnits[$ref], 'column' => null, 'message' => "{$label} has values in this file and also units under it"];
                $plan['entries'] = array_values(array_filter($plan['entries'], fn($e) => $e['unit'] !== $ref));
            }
        }
        $plan['new_units'] = $resolver->newUnits();
        usort($plan['errors'], fn($a, $b) => $a['row'] <=> $b['row']);
        return $plan;
    }

    private function validateMapping(array $mapping, array $headers, array $user): array
    {
        $v = new Validator($mapping);
        $unitColumns = $v->array('unit_columns', true) ?? [];
        $unitMatch = $v->enum('unit_match', ['path', 'name', 'code']) ?? 'path';
        $unitKinds = $v->array('unit_kinds') ?? [];
        $parentId = $v->int('parent_unit_id', false, 1) ?? $this->access->tree()->rootId();
        $create = $v->bool('create_missing_units') ?? false;
        $dateColumn = $v->string('date_column', true);
        $dateFormat = $v->enum('date_format', array_keys(CsvCell::DATE_FORMATS), true);
        $columns = $v->array('columns', true) ?? [];
        $v->check('The column mapping is incomplete');

        $index = array_flip($headers);
        $find = function (string $field, $column) use ($v, $index): ?int {
            if (!is_string($column) || !isset($index[$column])) {
                $v->addError($field, "column '" . (is_string($column) ? $column : '?') . "' is not in the file");
                return null;
            }
            return $index[$column];
        };

        if (!$unitColumns) {
            $v->addError('unit_columns', 'choose at least one column');
        } elseif ($unitMatch !== 'path' && count($unitColumns) > 1) {
            $v->addError('unit_match', 'matching by name or code uses a single column');
        }
        $unitIndexes = array_map(fn($c) => $find('unit_columns', $c), array_values($unitColumns));
        $dateIndex = $find('date_column', $dateColumn);

        if (!$this->access->tree()->get($parentId)) {
            $v->addError('parent_unit_id', 'not found');
        }
        if ($create && !$this->auth->isAdmin()) {
            $v->addError('create_missing_units', 'only administrators can add new ones');
        }
        if ($create && $unitMatch === 'code') {
            $v->addError('create_missing_units', "new ones can't be added when matching by code");
        }

        $kpiColumns = [];
        foreach ($columns as $column => $code) {
            $i = $find("columns.{$column}", (string)$column);
            $kpi = is_string($code) ? $this->kpis->findByCode($this->auth->organizationId(), $code) : null;
            if (!$kpi) {
                $v->addError("columns.{$column}", "no KPI with code '" . (is_string($code) ? $code : '?') . "'");
            } elseif ($i !== null) {
                $kpiColumns[$i] = [(string)$column, $kpi];
            }
        }
        if (!$columns) {
            $v->addError('columns', 'map at least one column to a KPI');
        }
        $v->check('The column mapping does not match the file');

        $kinds = [];
        foreach (array_values($unitColumns) as $level => $column) {
            $kind = $unitKinds[$level] ?? null;
            $kinds[] = is_string($kind) && trim($kind) !== '' ? trim($kind) : ucwords(str_replace('_', ' ', $column));
        }

        return [
            'unit_columns' => array_values($unitColumns),
            'unit_indexes' => $unitIndexes,
            'unit_match' => $unitMatch,
            'unit_kinds' => $kinds,
            'parent_unit_id' => $parentId,
            'create_missing_units' => $create,
            'date_column' => $dateColumn,
            'date_index' => $dateIndex,
            'date_format' => $dateFormat,
            'kpi_columns' => $kpiColumns,
        ];
    }

    private function changeCounts(array $plan): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        $existing = array_values(array_filter($plan['entries'], fn($e) => is_int($e['unit'])));
        $counts['created'] = count($plan['entries']) - count($existing);
        if (!$existing) {
            return $counts;
        }
        $periods = array_column($existing, 'period');
        $grid = $this->values->grid(
            array_unique(array_map(fn($e) => $e['kpi']['id'], $existing)),
            array_unique(array_column($existing, 'unit')),
            min($periods),
            Period::next(max($periods), 'daily')
        );
        foreach ($existing as $e) {
            $old = $grid[$e['kpi']['id']][$e['unit']][$e['period']] ?? null;
            $key = $old === null ? 'created' : (round($old, 6) === round($e['value'], 6) ? 'unchanged' : 'updated');
            $counts[$key]++;
        }
        return $counts;
    }

    /** @return array<string, int> placeholder ref => new unit id */
    private function createUnits(array $newUnits): array
    {
        $ids = [];
        foreach ($newUnits as $ref => $unit) { // parents are always planned before their children
            $parentId = is_int($unit['parent']) ? $unit['parent'] : $ids[$unit['parent']];
            $ids[$ref] = $this->units->create($this->auth->organizationId(), $parentId, $unit['name'], null, $unit['kind']);
        }
        return $ids;
    }
}
