<?php

namespace Controllers;

use Core\Request;
use Services\ValueService;

class ValueController
{
    private ValueService $values;

    public function __construct(ValueService $values)
    {
        $this->values = $values;
    }

    /** GET /values?unit=&date= : the data-entry form for one leaf unit. */
    public function form(Request $request): array
    {
        return $this->values->form($request->queryInt('unit'), $request->requiredQuery('date'));
    }

    /** GET /values/progress?date= : how much of the month's figures each unit has entered */
    public function progress(Request $request): array
    {
        return $this->values->progress($request->requiredQuery('date'));
    }

    /** PUT /values {values: [{kpi_id, unit_id, period, value, note}]} */
    public function save(Request $request): array
    {
        return $this->values->save($request->body);
    }

    /** GET /kpis/{id}/history?unit=[&date=][&before=] : changes to the figures, a page at a time */
    public function history(Request $request, array $params): array
    {
        return $this->values->history(
            (int)$params['id'], $request->queryInt('unit'), $request->queryParam('date') ?: null, $request->queryInt('before', false)
        );
    }
}
