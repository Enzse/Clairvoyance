<?php

namespace Core;

use Core\Mail\Mailer;
use Core\Mail\SmtpMailer;
use PDO;

/**
 * Turns a Request into a Response. Each request gets a fresh container, so nothing
 * (including the logged-in user) leaks between requests; tests rely on this.
 */
class App
{
    /** @var callable(): PDO */
    private $connect;
    /** @var callable(): Mailer */
    private $mailer;
    private Router $router;

    public function __construct(?callable $connect = null, ?callable $mailer = null)
    {
        $this->connect = $connect ?? fn() => Database::connect(Environment::getInstance());
        $this->mailer = $mailer ?? fn() => new SmtpMailer(Environment::getInstance());
        $this->router = new Router();
        (require __DIR__ . '/../routes.php')($this->router);
    }

    public function handle(Request $request): Response
    {
        try {
            [[$class, $method], $params] = $this->router->match($request->method, $request->path);

            $container = new Container();
            $container->set(PDO::class, fn() => ($this->connect)());
            $container->set(Mailer::class, fn() => ($this->mailer)());
            $container->set(Environment::class, fn() => Environment::getInstance());
            $result = $container->get($class)->$method($request, $params);

            return $result instanceof Response ? $result : Response::ok($result);
        } catch (HttpException $e) {
            return Response::error($e->getStatus(), $e->getMessage(), $e->getDetails());
        } catch (\Throwable $e) {
            error_log('API error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return Response::error(500, 'Something went wrong on the server');
        }
    }
}
