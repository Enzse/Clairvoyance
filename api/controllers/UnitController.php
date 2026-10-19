<?php

namespace Controllers;

use Core\Auth;
use Core\Request;
use Core\Response;
use Services\AccessService;
use Services\UnitService;

class UnitController
{
    private Auth $auth;
    private AccessService $access;
    private UnitService $units;

    public function __construct(Auth $auth, AccessService $access, UnitService $units)
    {
        $this->auth = $auth;
        $this->access = $access;
        $this->units = $units;
    }

    /** Units visible to the current user, flat; build the tree from parent_id. */
    public function list(): array
    {
        return $this->access->visibleUnits();
    }

    public function create(Request $request): Response
    {
        $this->auth->requireAdmin();
        return Response::created($this->units->create($request->body));
    }

    public function update(Request $request, array $params): array
    {
        $this->auth->requireAdmin();
        return $this->units->update($params['id'], $request->body);
    }

    public function delete(Request $request, array $params): array
    {
        $this->auth->requireAdmin();
        $this->units->delete($params['id']);
        return [];
    }
}
