<?php

namespace Controllers;

use Core\Request;
use Services\ExportService;

class ExportController
{
    private ExportService $exports;

    public function __construct(ExportService $exports)
    {
        $this->exports = $exports;
    }

    /** GET /exports/values?unit=&from=&to= : figures for the unit's subtree, for a CSV download */
    public function values(Request $request): array
    {
        return $this->exports->values($request->queryInt('unit'), $request->requiredQuery('from'), $request->requiredQuery('to'));
    }
}
