<?php

/**
 * Usage: php scripts/seed_demo.php <admin-email>
 *
 * Loads the DepEd sample (demo/deped.csv) into the first organisation the person administers:
 * six school KPIs with targets, and the Region > Division > School units with a year of
 * monthly reports. Runs through the API in-process as that admin, so it gets exactly the same
 * validation as the UI. Safe to run again: existing KPIs are kept and the import is an upsert.
 */

if (PHP_SAPI !== 'cli') {
    exit('Command line only.');
}

require_once __DIR__ . '/../vendor/autoload.php';

use Core\App;
use Core\Database;
use Core\Environment;
use Core\Request;
use Repositories\OrganizationRepository;
use Repositories\UserRepository;

$email = $argv[1] ?? null;
if (!$email) {
    fwrite(STDERR, "Usage: php scripts/seed_demo.php <admin-email>\n");
    exit(1);
}

$db = Database::connect(Environment::getInstance());
$admin = (new UserRepository($db))->findByEmail($email);
$organization = $admin
    ? array_values(array_filter((new OrganizationRepository($db))->forUser($admin['id']), fn($o) => $o['role'] === 'admin'))[0] ?? null
    : null;
if (!$organization) {
    fwrite(STDERR, "{$email} is not an administrator of any organisation\n");
    exit(1);
}
echo "Loading into {$organization['name']}\n";

$app = new App(fn() => $db);
$_SESSION = ['user_id' => $admin['id'], 'org_id' => $organization['id']];

$call = function (string $method, string $path, array $body = [], array $files = []) use ($app): array {
    $response = $app->handle(new Request($method, $path, [], $body, $files));
    if ($response->status >= 300) {
        fwrite(STDERR, "{$method} {$path} failed: " . json_encode($response->payload, JSON_PRETTY_PRINT) . "\n");
        exit(1);
    }
    return $response->payload['data'];
};

$kpis = [
    ['code' => 'total_students', 'name' => 'Total students', 'description' => 'Enrolled students at month end',
     'time_aggregation' => 'last', 'unit_aggregation' => 'sum', 'direction' => 'none'],
    ['code' => 'teachers', 'name' => 'Teachers', 'description' => 'Teaching staff at month end',
     'time_aggregation' => 'last', 'unit_aggregation' => 'sum', 'direction' => 'none'],
    ['code' => 'attendance_rate', 'name' => 'Attendance rate', 'description' => 'Average daily student attendance',
     'time_aggregation' => 'average', 'unit_aggregation' => 'weighted_average', 'weight' => 'total_students',
     'direction' => 'higher_is_better', 'tolerance_pct' => 3, 'decimals' => 1, 'suffix' => '%', 'target' => 95],
    ['code' => 'average_grade', 'name' => 'Average grade', 'description' => 'Mean final grade across students',
     'time_aggregation' => 'average', 'unit_aggregation' => 'weighted_average', 'weight' => 'total_students',
     'direction' => 'higher_is_better', 'tolerance_pct' => 5, 'decimals' => 1, 'target' => 85],
    ['code' => 'teacher_absences', 'name' => 'Teacher absences', 'description' => 'Teacher absence days in the month',
     'time_aggregation' => 'sum', 'unit_aggregation' => 'sum', 'direction' => 'lower_is_better', 'tolerance_pct' => 25, 'target' => 3],
    ['code' => 'budget_utilization', 'name' => 'Budget utilization', 'description' => 'Share of the allotted budget used',
     'time_aggregation' => 'average', 'unit_aggregation' => 'average', 'direction' => 'higher_is_better',
     'tolerance_pct' => 5, 'decimals' => 1, 'suffix' => '%', 'target' => 90],
];

$existing = array_column($call('GET', '/kpis'), null, 'code');
foreach ($kpis as $def) {
    if (isset($existing[$def['code']])) {
        echo "KPI {$def['code']} already exists, keeping it\n";
        continue;
    }
    $fields = array_diff_key($def, ['weight' => 1, 'target' => 1]) + ['frequency' => 'monthly'];
    if (isset($def['weight'])) {
        $fields['weight_kpi_id'] = $existing[$def['weight']]['id'];
    }
    $kpi = $call('POST', '/kpis', $fields);
    $existing[$kpi['code']] = $kpi;
    if (isset($def['target'])) {
        $call('PUT', "/kpis/{$kpi['id']}/targets", ['targets' => [['unit_id' => null, 'effective_from' => '2025-01-01', 'value' => $def['target']]]]);
    }
    echo "Created KPI {$kpi['code']}\n";
}

$file = __DIR__ . '/../demo/deped.csv';
$mapping = [
    'unit_columns' => ['Region', 'Division', 'School_Name'],
    'unit_kinds' => ['Region', 'Division', 'School'],
    'create_missing_units' => true,
    'date_column' => 'Report_Date',
    'date_format' => 'YYYY-MM-DD',
    'columns' => array_combine(
        ['Total_Students', 'Teachers', 'Attendance_Rate', 'Average_Grade', 'Teacher_Absences', 'Budget_Utilization'],
        array_column($kpis, 'code')
    ),
];
$result = $call('POST', '/imports', ['mapping' => json_encode($mapping)], [
    'file' => ['name' => 'deped.csv', 'tmp_name' => $file, 'error' => UPLOAD_ERR_OK],
]);
echo "Imported: {$result['values_created']} new, {$result['values_updated']} updated, {$result['unchanged']} unchanged values; {$result['units_created']} units created\n";
