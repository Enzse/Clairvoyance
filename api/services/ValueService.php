<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\Validator;
use Domain\KpiScope;
use Domain\Period;
use PDO;
use Repositories\KpiRepository;
use Repositories\ValueRepository;

/**
 * Manual data entry: one unit, one date, every KPI that applies to the unit. The date is
 * moved to each KPI's period start, so a monthly and a weekly KPI can share one form.
 */
class ValueService
{
    private PDO $db;
    private Auth $auth;
    private AccessService $access;
    private KpiRepository $kpis;
    private ValueRepository $values;

    public function __construct(PDO $db, Auth $auth, AccessService $access, KpiRepository $kpis, ValueRepository $values)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->access = $access;
        $this->kpis = $kpis;
        $this->values = $values;
    }

    public function form(int $unitId, string $date): array
    {
        $unit = $this->access->requireView($unitId);
        if (!$this->access->tree()->isLeaf($unitId)) {
            throw HttpException::badRequest('Data is entered for leaf units only; this unit is a roll-up of the units under it');
        }
        if (!Period::isValidDate($date)) {
            throw HttpException::badRequest('date must be YYYY-MM-DD');
        }

        $kpis = array_values(array_filter($this->kpis->all($this->auth->organizationId(), true), fn($k) => $this->appliesTo($k, $unit)));
        $periods = array_map(fn($k) => Period::align($date, $k['frequency']), $kpis);
        $stored = $this->values->forUnit($unitId, $periods);

        $rows = [];
        foreach ($kpis as $i => $kpi) {
            $value = $stored[$kpi['id'] . '|' . $periods[$i]] ?? null;
            $rows[] = [
                'kpi' => $kpi,
                'period_start' => $periods[$i],
                'period_label' => Period::label($periods[$i], $kpi['frequency']),
                'value' => $value ? (float)$value['value'] : null,
                'note' => $value['note'] ?? null,
                'updated_at' => $value['updated_at'] ?? null,
                'updated_by' => $value['updated_by'] ?? null,
            ];
        }

        $role = $this->access->roleFor($unitId);
        return [
            'unit' => $unit,
            'can_edit' => $unit['is_active'] && in_array($role, ['contributor', 'admin'], true),
            'rows' => $rows,
        ];
    }

    /**
     * Save a batch of {kpi_id, unit_id, period, value, note}. value null deletes the entry.
     * All entries are validated first; nothing is written if any is invalid.
     */
    public function save(array $input): array
    {
        $user = $this->auth->requireUser();
        $v = new Validator($input);
        $entries = $v->array('values', true) ?? [];
        $v->check();
        if (!$entries) {
            throw HttpException::badRequest('values must not be empty');
        }

        $tree = $this->access->tree();
        $clean = [];
        foreach (array_values($entries) as $i => $entry) {
            $ev = new Validator(is_array($entry) ? $entry : []);
            $kpiId = $ev->int('kpi_id', true, 1);
            $unitId = $ev->int('unit_id', true, 1);
            $period = $ev->date('period', true);
            $value = $ev->number('value', false, -1e13, 1e13);
            $note = $ev->string('note', false, 500);
            try {
                $ev->check();
                $kpi = $this->kpis->find($this->auth->organizationId(), $kpiId);
                $unit = $this->access->requireContribute($unitId);
                $problem = $this->entryProblem($kpi, $unit, $tree->isLeaf($unitId), $unitId);
                if ($problem) {
                    throw HttpException::badRequest($problem);
                }
            } catch (HttpException $e) {
                $details = $e->getDetails() ? implode('; ', array_map(fn($f, $m) => "{$f} {$m}", array_keys($e->getDetails()), $e->getDetails())) : $e->getMessage();
                $v->addError("values.{$i}", $details);
                continue;
            }
            $clean[] = [$kpiId, $unitId, Period::align($period, $kpi['frequency']), $value, $note];
        }
        $v->check('Some values could not be saved; nothing was changed');

        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0];
        $this->db->beginTransaction();
        try {
            foreach ($clean as [$kpiId, $unitId, $periodStart, $value, $note]) {
                if ($value === null) {
                    $counts['deleted'] += (int)$this->values->delete($kpiId, $unitId, $periodStart, $user['id']);
                } else {
                    $counts[$this->values->upsert($kpiId, $unitId, $periodStart, $value, $note, null, $user['id'])]++;
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $counts;
    }

    /**
     * Who changed this KPI's figures in the unit (and the units inside it), newest first.
     * With a date, only changes to the period containing it.
     */
    public function history(int $kpiId, int $unitId, ?string $date): array
    {
        $this->access->requireView($unitId);
        $kpi = $this->kpis->find($this->auth->organizationId(), $kpiId);
        if (!$kpi) {
            throw HttpException::notFound('KPI not found');
        }
        if ($date !== null && !Period::isValidDate($date)) {
            throw HttpException::badRequest('date must be YYYY-MM-DD');
        }
        $period = $date === null ? null : Period::align($date, $kpi['frequency']);
        $changes = $this->values->changes($kpiId, $this->access->tree()->subtreeIds($unitId), $period);
        return array_map(fn($c) => $c + ['period_label' => Period::label($c['period_start'], $kpi['frequency'])], $changes);
    }

    /**
     * Why a value can't be stored for this KPI and unit, or null if it can. $scopeAnchor is the
     * unit's id, or for a unit an import is about to create, its nearest existing ancestor.
     */
    public function entryProblem(?array $kpi, array $unit, bool $isLeaf, int $scopeAnchor): ?string
    {
        if (!$kpi) {
            return 'KPI not found';
        }
        if (!$kpi['is_active']) {
            return "KPI {$kpi['name']} is inactive";
        }
        if (!$isLeaf) {
            return "{$unit['name']} is a roll-up of the units under it; enter data for those instead";
        }
        if (!$unit['is_active']) {
            return "{$unit['name']} is inactive";
        }
        if ($kpi['applies_to_kind'] !== null && $kpi['applies_to_kind'] !== $unit['kind']) {
            return "KPI {$kpi['name']} only applies to units of kind {$kpi['applies_to_kind']}";
        }
        if (!KpiScope::covers($this->access->tree(), $kpi, $scopeAnchor)) {
            return "{$unit['name']} doesn't report {$kpi['name']}";
        }
        return null;
    }

    private function appliesTo(array $kpi, array $unit): bool
    {
        return ($kpi['applies_to_kind'] === null || $kpi['applies_to_kind'] === $unit['kind'])
            && KpiScope::covers($this->access->tree(), $kpi, $unit['id']);
    }
}
