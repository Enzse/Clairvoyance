<?php

/**
 * Test runner.
 *   php tests/run.php              all tests
 *   php tests/run.php Unit         only tests/Unit
 *   php tests/run.php Aggregator   only test classes whose name contains "Aggregator"
 *
 * Integration tests use the database named in tests/bootstrap.php (must end in _test).
 */

require_once __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';
$files = array_merge(glob(__DIR__ . '/Unit/*Test.php'), glob(__DIR__ . '/Integration/*Test.php'));

$passed = 0;
$failures = [];

foreach ($files as $file) {
    $suite = basename(dirname($file));
    $class = 'Tests\\' . $suite . '\\' . basename($file, '.php');
    if ($filter !== '' && stripos("{$suite}\\{$class}", $filter) === false) {
        continue;
    }

    if (method_exists($class, 'setUpBeforeClass')) {
        $class::setUpBeforeClass();
    }

    foreach (get_class_methods($class) as $method) {
        if (strpos($method, 'test') !== 0) {
            continue;
        }
        $name = basename($file, '.php') . "::{$method}";
        try {
            (new $class())->runTest($method);
            $passed++;
            echo "  \u{2713} {$name}\n";
        } catch (\Throwable $e) {
            $where = $e instanceof Tests\AssertionFailed ? '' : ' (' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            $failures[] = "{$name}: {$e->getMessage()}{$where}";
            echo "  \u{2717} {$name}\n";
        }
    }
}

echo "\n{$passed} passed, " . count($failures) . " failed\n";
foreach ($failures as $failure) {
    echo "\n  FAIL {$failure}\n";
}
exit($failures ? 1 : 0);
