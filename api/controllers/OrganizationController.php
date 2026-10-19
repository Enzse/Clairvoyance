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

    /** PUT /organization {name?, unit_label?}: rename the organisation or change its word for units (admin). */
    public function rename(Request $request): array
    {
        return $this->organizations->update($request->body);
    }

    /** POST /organizations/select {organization_id}: work in another of your organisations. */
    public function select(Request $request): array
    {
        return $this->organizations->select($request->body);
    }

    /** POST /organization/leave */
    public function leave(): array
    {
        return $this->organizations->leave();
    }

    /** DELETE /organization {name, password}: delete it with everything in it (admin). */
    public function delete(Request $request): array
    {
        return $this->organizations->delete($request->body);
    }
}
