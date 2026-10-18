<?php

namespace Controllers;

use Core\Request;
use Services\ReportService;

/** Dates are optional: they default to the latest period that has data for the unit. */
class ReportController
{
    private ReportService $reports;

    public function __construct(ReportService $reports)
    {
        $this->reports = $reports;
    }

    /** GET /scorecard?unit=[&date=][&grain=] (without grain, each KPI at its own frequency) */
    public function scorecard(Request $request): array
    {
        return $this->reports->scorecard(
            $request->queryInt('unit'),
            $request->queryParam('date') ?: null,
            $request->queryParam('grain') ?: null
        );
    }

    /** GET /kpis/{id}/series?unit=&grain=[&from=][&to=] */
    public function series(Request $request, array $params): array
    {
        return $this->reports->kpiSeries(
            $params['id'],
            $request->queryInt('unit'),
            $request->requiredQuery('grain'),
            $request->queryParam('from') ?: null,
            $request->queryParam('to') ?: null
        );
    }

    /** GET /kpis/{id}/breakdown?unit=&grain=[&date=] */
    public function breakdown(Request $request, array $params): array
    {
        return $this->reports->breakdown(
            $params['id'],
            $request->queryInt('unit'),
            $request->queryParam('date') ?: null,
            $request->requiredQuery('grain')
        );
    }
}
