<?php

namespace Controllers;

use Core\Request;
use Services\ReportService;

class ReportController
{
    private ReportService $reports;

    public function __construct(ReportService $reports)
    {
        $this->reports = $reports;
    }

    /** GET /scorecard?unit=&date=&grain= (grain optional: each KPI at its own frequency) */
    public function scorecard(Request $request): array
    {
        return $this->reports->scorecard(
            $request->queryInt('unit'),
            $request->requiredQuery('date'),
            $request->queryParam('grain') ?: null
        );
    }

    /** GET /kpis/{id}/series?unit=&grain=&from=&to= */
    public function series(Request $request, array $params): array
    {
        return $this->reports->kpiSeries(
            $params['id'],
            $request->queryInt('unit'),
            $request->requiredQuery('grain'),
            $request->requiredQuery('from'),
            $request->requiredQuery('to')
        );
    }

    /** GET /kpis/{id}/breakdown?unit=&date=&grain= */
    public function breakdown(Request $request, array $params): array
    {
        return $this->reports->breakdown(
            $params['id'],
            $request->queryInt('unit'),
            $request->requiredQuery('date'),
            $request->requiredQuery('grain')
        );
    }
}
