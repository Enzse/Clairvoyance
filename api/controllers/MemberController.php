<?php

namespace Controllers;

use Core\Request;
use Services\MemberService;

/** People in the current organisation. The service checks the caller is an admin. */
class MemberController
{
    private MemberService $members;

    public function __construct(MemberService $members)
    {
        $this->members = $members;
    }

    public function list(): array
    {
        return $this->members->list();
    }

    public function setRole(Request $request, array $params): array
    {
        return $this->members->setRole($params['id'], $request->body);
    }

    public function remove(Request $request, array $params): array
    {
        $this->members->remove($params['id']);
        return [];
    }

    public function replaceMemberships(Request $request, array $params): array
    {
        return $this->members->replaceMemberships($params['id'], $request->body);
    }
}
