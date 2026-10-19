<?php

namespace Controllers;

use Core\Request;
use Services\SetupService;

/** The getting-started guide (admins of the current organisation). */
class SetupController
{
    private SetupService $setup;

    public function __construct(SetupService $setup)
    {
        $this->setup = $setup;
    }

    /** GET /organization/setup */
    public function get(): array
    {
        return $this->setup->get();
    }

    /** POST /organization/setup/dismiss {dismissed?: bool} */
    public function dismiss(Request $request): array
    {
        return $this->setup->dismiss($request->body);
    }

    /** POST /organization/setup/skip {step, skipped?: bool} */
    public function skip(Request $request): array
    {
        return $this->setup->skip($request->body);
    }
}
