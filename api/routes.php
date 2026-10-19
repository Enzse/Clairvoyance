<?php

use Controllers\AuthController;
use Controllers\HealthController;
use Controllers\ImportController;
use Controllers\InvitationController;
use Controllers\KpiController;
use Controllers\MemberController;
use Controllers\OrganizationController;
use Controllers\ReportController;
use Controllers\UnitController;
use Controllers\ValueController;
use Core\Router;

return function (Router $r): void {
    // Account
    $r->add('POST', '/auth/register', [AuthController::class, 'register']);
    $r->add('POST', '/auth/login', [AuthController::class, 'login']);
    $r->add('POST', '/auth/logout', [AuthController::class, 'logout']);
    $r->add('GET', '/auth/me', [AuthController::class, 'me']);
    $r->add('PUT', '/auth/me', [AuthController::class, 'update']);
    $r->add('POST', '/auth/verify-email', [AuthController::class, 'verifyEmail']);
    $r->add('POST', '/auth/verify-email/resend', [AuthController::class, 'resendVerification']);
    $r->add('POST', '/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    $r->add('POST', '/auth/reset-password', [AuthController::class, 'resetPassword']);

    $r->add('GET', '/health', [HealthController::class, 'check']);

    // Organisations: create, switch, rename the current one, getting-started checklist
    $r->add('POST', '/organizations', [OrganizationController::class, 'create']);
    $r->add('POST', '/organizations/select', [OrganizationController::class, 'select']);
    $r->add('PUT', '/organization', [OrganizationController::class, 'rename']);
    $r->add('GET', '/organization/setup', [OrganizationController::class, 'setup']);
    $r->add('POST', '/organization/setup/dismiss', [OrganizationController::class, 'dismissSetup']);

    // People in the current organisation
    $r->add('GET', '/members', [MemberController::class, 'list']);
    $r->add('PUT', '/members/{id}', [MemberController::class, 'setRole']);
    $r->add('DELETE', '/members/{id}', [MemberController::class, 'remove']);
    $r->add('PUT', '/members/{id}/memberships', [MemberController::class, 'replaceMemberships']);

    $r->add('GET', '/invitations', [InvitationController::class, 'pending']);
    $r->add('POST', '/invitations', [InvitationController::class, 'create']);
    $r->add('DELETE', '/invitations/{id}', [InvitationController::class, 'revoke']);
    $r->add('GET', '/invitations/lookup', [InvitationController::class, 'lookup']);
    $r->add('POST', '/invitations/accept', [InvitationController::class, 'accept']);

    // Everything below works on the current organisation
    $r->add('GET', '/units', [UnitController::class, 'list']);
    $r->add('POST', '/units', [UnitController::class, 'create']);
    $r->add('PUT', '/units/{id}', [UnitController::class, 'update']);
    $r->add('DELETE', '/units/{id}', [UnitController::class, 'delete']);

    $r->add('GET', '/kpis', [KpiController::class, 'list']);
    $r->add('POST', '/kpis', [KpiController::class, 'create']);
    $r->add('GET', '/kpis/{id}', [KpiController::class, 'get']);
    $r->add('PUT', '/kpis/{id}', [KpiController::class, 'update']);
    $r->add('GET', '/kpis/{id}/targets', [KpiController::class, 'targets']);
    $r->add('PUT', '/kpis/{id}/targets', [KpiController::class, 'replaceTargets']);
    $r->add('GET', '/kpis/{id}/series', [ReportController::class, 'series']);
    $r->add('GET', '/kpis/{id}/breakdown', [ReportController::class, 'breakdown']);

    $r->add('GET', '/values', [ValueController::class, 'form']);
    $r->add('PUT', '/values', [ValueController::class, 'save']);

    $r->add('GET', '/imports', [ImportController::class, 'list']);
    $r->add('POST', '/imports/preview', [ImportController::class, 'preview']);
    $r->add('POST', '/imports', [ImportController::class, 'commit']);

    $r->add('GET', '/scorecard', [ReportController::class, 'scorecard']);
};
