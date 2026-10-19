<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Core\Validator;
use Domain\Period;
use PDO;
use Repositories\KpiRepository;
use Repositories\TargetRepository;

/** KPI definitions and targets of the current organisation. */
class KpiService
{
    private const TIME_RULES = ['sum', 'average', 'last'];
    private const UNIT_RULES = ['sum', 'average', 'weighted_average'];
    private const DIRECTIONS = ['higher_is_better', 'lower_is_better', 'none'];

    private PDO $db;
    private Auth $auth;
    private KpiRepository $kpis;
    private TargetRepository $targets;
    private AccessService $access;

    public function __construct(PDO $db, Auth $auth, KpiRepository $kpis, TargetRepository $targets, AccessService $access)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->kpis = $kpis;
        $this->targets = $targets;
        $this->access = $access;
    }

    /**
     * The organisation's KPIs, each with `target` (the target for every unit in force today,
     * or null) and `unit_target_count` (how many targets are set for particular units).
     */
    public function list(bool $includeInactive): array
    {
        $kpis = $this->kpis->all($this->auth->organizationId(), !$includeInactive);
        $targets = $this->targets->forKpis(array_column($kpis, 'id'));
        $today = date('Y-m-d');
        return array_map(function (array $kpi) use ($targets, $today) {
            $current = null;
            $perUnit = 0;
            foreach ($targets[$kpi['id']] as $t) {
                if ($t['unit_id'] !== null) {
                    $perUnit++;
                } elseif ($t['effective_from'] <= $today) {
                    $current = $t['value']; // ordered by start date, so the last one wins
                }
            }
            return $kpi + ['target' => $current, 'unit_target_count' => $perUnit];
        }, $kpis);
    }

    public function get(int $id): array
    {
        $kpi = $this->kpis->find($this->auth->organizationId(), $id);
        if (!$kpi) {
            throw HttpException::notFound('KPI not found');
        }
        return $kpi;
    }

    public function create(array $input): array
    {
        $fields = $this->validate($input, null);
        $scope = $this->validScope($input, true);
        return $this->save(null, $fields, $scope);
    }

    public function update(int $id, array $input): array
    {
        $existing = $this->get($id);
        $fields = $this->validate($input, $existing);
        $scope = $this->validScope($input, false);
        return $this->save($id, $fields, $scope);
    }

    private function save(?int $id, array $fields, ?array $scope): array
    {
        $orgId = $this->auth->organizationId();
        $this->db->beginTransaction();
        try {
            if ($id === null) {
                $id = $this->kpis->create($orgId, $fields);
            } else {
                $this->kpis->update($orgId, $id, $fields);
            }
            if ($scope !== null) {
                $this->kpis->replaceScope($id, $scope);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->get($id);
    }

    /**
     * `scope_unit_ids`: the units whose leaves report this KPI; empty means everyone.
     * Returns null when an update leaves the scope unchanged.
     */
    private function validScope(array $input, bool $creating): ?array
    {
        if (!array_key_exists('scope_unit_ids', $input)) {
            return $creating ? [] : null;
        }
        $ids = $input['scope_unit_ids'] ?? [];
        $v = new Validator([]);
        if (!is_array($ids)) {
            $v->addError('scope_unit_ids', 'must be a list');
        } else {
            foreach ($ids as $id) {
                if (filter_var($id, FILTER_VALIDATE_INT) === false || !$this->access->tree()->get((int)$id)) {
                    $v->addError('scope_unit_ids', 'contains one that no longer exists');
                }
            }
        }
        $v->check();
        $ids = array_values(array_unique(array_map('intval', $ids)));
        // Reporting "everywhere" is the same as listing the whole organisation
        $tree = $this->access->tree();
        return $tree->hasRoot() && in_array($tree->rootId(), $ids, true) ? [] : $ids;
    }

    public function targets(int $id): array
    {
        $this->get($id);
        return $this->targets->forKpis([$id])[$id];
    }

    /** Replace all targets of a KPI. effective_from dates are moved to their period start. */
    public function replaceTargets(int $id, array $input): array
    {
        $kpi = $this->get($id);
        $v = new Validator($input);
        $rows = $v->array('targets', true) ?? [];
        $v->check();

        $tree = $this->access->tree();
        $clean = [];
        $seen = [];
        foreach (array_values($rows) as $i => $row) {
            $rv = new Validator(is_array($row) ? $row : []);
            $unitId = $rv->int('unit_id', false, 1);
            $from = $rv->date('effective_from', true);
            $value = $rv->number('value', true);
            if ($unitId !== null && !$tree->get($unitId)) {
                $rv->addError('unit_id', 'not found');
            }
            try {
                $rv->check();
            } catch (HttpException $e) {
                $v->addError("targets.{$i}", implode('; ', array_map(fn($f, $m) => "{$f} {$m}", array_keys($e->getDetails()), $e->getDetails())));
                continue;
            }
            $from = Period::align($from, $kpi['frequency']);
            $key = ($unitId ?? 0) . '|' . $from;
            if (isset($seen[$key])) {
                $v->addError("targets.{$i}", 'duplicates another target for the same place and start date');
                continue;
            }
            $seen[$key] = true;
            $clean[] = ['unit_id' => $unitId, 'effective_from' => $from, 'value' => $value];
        }
        $v->check('Some targets are invalid');

        $this->db->beginTransaction();
        try {
            $this->targets->replace($id, $clean);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $this->targets($id);
    }

    /** @param array|null $existing the KPI being updated, or null when creating */
    private function validate(array $input, ?array $existing): array
    {
        $creating = $existing === null;
        $v = new Validator($input);
        // Required on create; on update, may be omitted but not blanked
        $req = fn(string $field) => $creating || $v->has($field);
        $fields = [
            'code' => $v->string('code', $req('code'), 64, '/^[a-z][a-z0-9_]*$/', 'must start with a letter and use only a-z, 0-9 and _'),
            'name' => $v->string('name', $req('name'), 255),
            'description' => $v->string('description', false, 2000),
            'frequency' => $v->enum('frequency', Period::FREQUENCIES, $req('frequency')),
            'time_aggregation' => $v->enum('time_aggregation', self::TIME_RULES, $req('time_aggregation')),
            'unit_aggregation' => $v->enum('unit_aggregation', self::UNIT_RULES, $req('unit_aggregation')),
            'weight_kpi_id' => $v->int('weight_kpi_id', false, 1),
            'direction' => $v->enum('direction', self::DIRECTIONS, $req('direction')),
            'tolerance_pct' => $v->number('tolerance_pct', $v->has('tolerance_pct'), 0, 1000),
            'applies_to_kind' => $v->string('applies_to_kind', false, 64),
            'decimals' => $v->int('decimals', $v->has('decimals'), 0, 6),
            'prefix' => $v->string('prefix', false, 10),
            'suffix' => $v->string('suffix', false, 10),
            'is_active' => $v->bool('is_active', $v->has('is_active')),
        ];
        $v->check();

        // Only fields that were sent; on create, fill defaults
        $fields = array_filter($fields, fn($key) => $v->has($key), ARRAY_FILTER_USE_KEY);
        if ($creating) {
            $fields += ['tolerance_pct' => 10, 'decimals' => 0, 'prefix' => '', 'suffix' => ''];
        }
        foreach (['prefix', 'suffix'] as $key) {
            if (array_key_exists($key, $fields)) {
                $fields[$key] ??= ''; // empty text clears it
            }
        }
        $merged = array_merge($existing ?? [], $fields);
        if ($merged['unit_aggregation'] !== 'weighted_average') {
            $merged['weight_kpi_id'] = $fields['weight_kpi_id'] = null;
        }

        if (isset($fields['code'])) {
            $clash = $this->kpis->findByCode($this->auth->organizationId(), $fields['code']);
            if ($clash && $clash['id'] !== ($existing['id'] ?? null)) {
                $v->addError('code', 'is already used by another KPI');
            }
        }
        if ($merged['unit_aggregation'] === 'weighted_average') {
            $this->validateWeight($v, $merged);
        }
        if (!$creating && $merged['frequency'] !== $existing['frequency'] && $this->kpis->hasValues($existing['id'])) {
            $v->addError('frequency', 'cannot change once the KPI has values (they are stored per period)');
        }
        if (!$creating && $merged['is_active'] === false && $existing['is_active']) {
            $dependents = $this->kpis->activeDependents($this->auth->organizationId(), $existing['id']);
            if ($dependents) {
                $v->addError('is_active', 'is the weight for: ' . implode(', ', $dependents));
            }
        }
        $v->check();

        if (isset($fields['is_active'])) {
            $fields['is_active'] = (int)$fields['is_active'];
        }
        return $fields;
    }

    private function validateWeight(Validator $v, array $kpi): void
    {
        if (empty($kpi['weight_kpi_id'])) {
            $v->addError('weight_kpi_id', 'is required for a weighted average');
            return;
        }
        $weight = $this->kpis->find($this->auth->organizationId(), $kpi['weight_kpi_id']);
        if (!$weight) {
            $v->addError('weight_kpi_id', 'KPI not found');
        } elseif ($weight['id'] === ($kpi['id'] ?? null)) {
            $v->addError('weight_kpi_id', 'cannot be the KPI itself');
        } elseif (!$weight['is_active']) {
            $v->addError('weight_kpi_id', 'is inactive');
        } elseif ($weight['frequency'] !== $kpi['frequency']) {
            $v->addError('weight_kpi_id', "must be reported {$kpi['frequency']} like this KPI");
        } elseif ($weight['unit_aggregation'] === 'weighted_average') {
            $v->addError('weight_kpi_id', 'cannot itself be a weighted average');
        }
    }
}
