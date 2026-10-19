<?php

namespace Controllers;

use Core\Request;
use Core\Response;
use Services\InvitationService;

class InvitationController
{
    private InvitationService $invitations;

    public function __construct(InvitationService $invitations)
    {
        $this->invitations = $invitations;
    }

    public function pending(): array
    {
        return $this->invitations->pending();
    }

    /** POST /invitations {email, role, access}: the response holds the token for the link, once. */
    public function create(Request $request): Response
    {
        return Response::created($this->invitations->create($request->body));
    }

    public function revoke(Request $request, array $params): array
    {
        $this->invitations->revoke($params['id']);
        return [];
    }

    /** GET /invitations/lookup?token=: public, for the invitation page before signing in. */
    public function lookup(Request $request): array
    {
        return $this->invitations->lookup($request->requiredQuery('token'));
    }

    /** POST /invitations/accept {token} */
    public function accept(Request $request): array
    {
        return $this->invitations->accept($request->body);
    }
}
