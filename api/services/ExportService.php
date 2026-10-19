<?php

namespace Services;

use Core\Auth;
use Core\HttpException;
use Domain\Period;
use Repositories\KpiRepository;
use Repositories\ValueRepository;

/**
 * The stored figures for a unit's subtree over a date range, as the browser needs them to write
 * a CSV that the importer can read back (one row per unit and period, one column per KPI).
 */
class ExportService
{
    private const MAX_VALUES = 100000;

    private Auth $auth;
    private AccessService $access;
    private KpiRepository $kpis;
    private ValueRepository $values;

    public function __construct(Auth $auth, AccessService $access, KpiRepository $kpis, ValueRepository $values)
    {
        $this->auth = $auth;
        $this->access = $access;
        $this->kpis = $kpis;
        $this->values = $values;
    }

    /** Values with period starts from $from up to and including $to. */
    public function values(int $unitId, string $from, string $to): array
    {
        $this->access->requireView($unitId);
        if (!Period::isValidDate($from) || !Period::isValidDate($to)) {
            throw HttpException::badRequest('from and to must be YYYY-MM-DD');
        }
        if ($from > $to) {
            throw HttpException::badRequest('from must be on or before to');
        }

        $tree = $this->access->tree();
        $leafIds = array_values(array_filter($tree->subtreeIds($unitId), fn($id) => $tree->isLeaf($id)));
        $kpis = $this->kpis->all($this->auth->organizationId(), false);
        $until = date('Y-m-d', strtotime("{$to} +1 day"));
        $grid = $this->values->grid(array_column($kpis, 'id'), $leafIds, $from, $until);

        $values = [];
        foreach ($grid as $kpiId => $byUnit) {
            foreach ($byUnit as $leafId => $byPeriod) {
                foreach ($byPeriod as $periodStart => $value) {
                    $values[] = ['kpi_id' => $kpiId, 'unit_id' => $leafId, 'period_start' => $periodStart, 'value' => $value];
                }
            }
        }
        if (count($values) > self::MAX_VALUES) {
            throw HttpException::badRequest('That is more than ' . number_format(self::MAX_VALUES) . ' figures. Export a shorter period or a smaller part of the organisation');
        }

        $rootId = $tree->rootId();
        return [
            'kpis' => array_map(fn($k) => ['id' => $k['id'], 'code' => $k['code'], 'name' => $k['name'], 'decimals' => $k['decimals']], $kpis),
            'units' => array_map(function ($id) use ($tree, $rootId) {
                $unit = $tree->get($id);
                // The path below the organisation itself, e.g. North › Store A
                $path = $tree->pathNames($id);
                if ($id !== $rootId) {
                    array_shift($path);
                }
                return ['id' => $id, 'name' => $unit['name'], 'code' => $unit['code'], 'path' => $path];
            }, $leafIds),
            'values' => $values,
        ];
    }
}
