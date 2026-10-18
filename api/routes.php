<?php

use Controllers\AuthController;
use Controllers\ImportController;
use Controllers\KpiController;
use Controllers\ReportController;
use Controllers\UnitController;
use Controllers\UserController;
use Controllers\ValueController;
use Core\Router;

return function (Router $r): void {
    $r->add('POST', '/auth/register', [AuthController::class, 'register']);
    $r->add('POST', '/auth/login', [AuthController::class, 'login']);
    $r->add('POST', '/auth/logout', [AuthController::class, 'logout']);
    $r->add('GET', '/auth/me', [AuthController::class, 'me']);

    $r->add('GET', '/users', [UserController::class, 'list']);
    $r->add('POST', '/users', [UserController::class, 'create']);
    $r->add('PUT', '/users/{id}', [UserController::class, 'update']);
    $r->add('DELETE', '/users/{id}', [UserController::class, 'delete']);
    $r->add('GET', '/users/{id}/memberships', [UserController::class, 'memberships']);
    $r->add('PUT', '/users/{id}/memberships', [UserController::class, 'replaceMemberships']);

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
