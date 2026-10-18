<?php

namespace Controllers;

use Core\Auth;
use Core\Request;
use Core\Response;
use Services\UserService;

/** Admin-only user management. */
class UserController
{
    private Auth $auth;
    private UserService $users;

    public function __construct(Auth $auth, UserService $users)
    {
        $this->auth = $auth;
        $this->users = $users;
    }

    public function list(): array
    {
        $this->auth->requireAdmin();
        return $this->users->list();
    }

    public function create(Request $request): Response
    {
        $this->auth->requireAdmin();
        return Response::created($this->users->create($request->body));
    }

    public function update(Request $request, array $params): array
    {
        $this->auth->requireAdmin();
        return $this->users->update($params['id'], $request->body);
    }

    public function delete(Request $request, array $params): array
    {
        $this->auth->requireAdmin();
        $this->users->delete($params['id']);
        return [];
    }

    public function memberships(Request $request, array $params): array
    {
        $this->auth->requireAdmin();
        return $this->users->memberships($params['id']);
    }

    public function replaceMemberships(Request $request, array $params): array
    {
        $this->auth->requireAdmin();
        return $this->users->replaceMemberships($params['id'], $request->body);
    }
}
