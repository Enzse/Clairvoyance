<?php

/**
 * PSR-4 autoloading for the API's own namespaces. The API has no third-party dependencies,
 * so this replaces Composer: Core\Auth -> core/Auth.php, Tests\Unit\X -> tests/Unit/X.php.
 */
spl_autoload_register(function (string $class): void {
    static $roots = [
        'Core\\' => 'core/',
        'Domain\\' => 'domain/',
        'Repositories\\' => 'repositories/',
        'Services\\' => 'services/',
        'Controllers\\' => 'controllers/',
        'Tests\\' => 'tests/',
    ];
    foreach ($roots as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = __DIR__ . '/' . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
