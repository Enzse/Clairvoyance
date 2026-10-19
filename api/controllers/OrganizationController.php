<?php

namespace Controllers;

use Core\Request;
use Core\Response;
use Services\OrganizationService;

class OrganizationController
{
    private OrganizationService $organizations;

    public function __construct(OrganizationService $organizations)
    {
        $this->organizations = $organizations;
    }

    /** POST /organizations {name}: create one and become its admin. */
    public function create(Request $request): Response
    {
        return Response::created($this->organizations->create($request->body));
    }

    /** PUT /organization {name}: rename the current organisation (admin). */
    public function rename(Request $request): array
    {
        return $this->organizations->rename($request->body);
    }

    /** POST /organizations/select {organization_id}: work in another of your organisations. */
    public function select(Request $request): array
    {
        return $this->organizations->select($request->body);
    }

    public function setup(): array
    {
        return $this->organizations->setup();
    }

    public function dismissSetup(): array
    {
        return $this->organizations->dismissSetup();
    }
}
