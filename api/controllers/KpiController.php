<?php

namespace Controllers;

use Core\Auth;
use Core\Request;
use Core\Response;
use Services\KpiService;

/** KPI definitions are organisation-wide: everyone can read them, admins change them. */
class KpiController
{
    private Auth $auth;
    private KpiService $kpis;

    public function __construct(Auth $auth, KpiService $kpis)
    {
        $this->auth = $auth;
        $this->kpis = $kpis;
    }

    public function list(Request $request): array
    {
        $this->auth->requireUser();
        $includeInactive = $request->queryParam('include_inactive') === '1' && $this->auth->isAdmin();
        return $this->kpis->list($includeInactive);
    }

    public function get(Request $request, array $params): array
    {
        $this->auth->requireUser();
        return $this->kpis->get($params['id']);
    }

    public function create(Request $request): Response
    {
        $this->auth->requireAdmin();
        return Response::created($this->kpis->create($request->body));
    }

    public function update(Request $request, array $params): array
    {
        $this->auth->requireAdmin();
        return $this->kpis->update($params['id'], $request->body);
    }

    /** Admins only: targets cover every unit, including ones a member can't see. */
    public function targets(Request $request, array $params): array
    {
        $this->auth->requireAdmin();
        return $this->kpis->targets($params['id']);
    }

    public function replaceTargets(Request $request, array $params): array
    {
        $this->auth->requireAdmin();
        return $this->kpis->replaceTargets($params['id'], $request->body);
    }
}
