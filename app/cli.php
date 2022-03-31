<?php
/**
 * Entry point of CLI application
 */

use Phalcon\Cli\Console;
use Phalcon\Di\FactoryDefault\Cli;
use Phalcon\Loader;

define('BASE_PATH', dirname(__DIR__) . '/');
define('APP_PATH', __DIR__);

$loader = new Loader;

$loader->registerDirs([
    APP_PATH . '/tasks/',
    APP_PATH . '/models/',
]);
$loader->register();

$di = new Cli;
require_once APP_PATH . '/bootstrap.php';

$cli = new Console;
$cli->setDI($di);

$arguments = [];

foreach ($argv as $k => $arg) {
    if ($k === 1) {
        $arguments['task'] = $arg;
    } elseif ($k === 2) {
        $arguments['action'] = $arg;
    } elseif ($k >= 3) {
        $arguments['params'][] = $arg;
    }
}
$cli->handle($arguments);