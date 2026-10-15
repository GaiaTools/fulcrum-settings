<?php

declare(strict_types=1);
use GaiaTools\FulcrumSettings\Support\Modules\ModuleAccess;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Routing\Router;
use Nwidart\Modules\Contracts\ActivatorInterface;

$loader = require dirname(__DIR__, 2).'/vendor/autoload.php';
$loader->setPsr4('Nwidart\\Modules\\', []);
if (interface_exists(ActivatorInterface::class)) {
    throw new RuntimeException('Laravel Modules must be absent for this check.');
}
$test = new class('smoke') extends TestCase {};
$app = $test->createApplication();
if (! isset($app->make(Router::class)->getMiddleware()['fulcrum.module'])) {
    throw new RuntimeException('Fulcrum middleware was not registered.');
}
if ($app->make(ModuleAccess::class)->allows('Unknown')) {
    throw new RuntimeException('Unknown module access must be denied.');
}
echo "Fulcrum booted without Laravel Modules.\n";
