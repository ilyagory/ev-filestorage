<?php
/**
 * Entry point of MVC application
 */
use Phalcon\Di\FactoryDefault;
use Phalcon\Events\Event;
use Phalcon\Events\Manager;
use Phalcon\Http\Response\Cookies;
use Phalcon\Loader;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\View;
use Phalcon\Session\Adapter\Files;

define('BASE_PATH', dirname(__DIR__) . '/');
define('APP_PATH', BASE_PATH . 'app/');
define('ENV_PRODUCTION', getenv('PHP_ENV') !== 'development');

$loader = new Loader();
$loader->registerDirs([
    APP_PATH . '/controllers/',
    APP_PATH . '/models/',
    APP_PATH . '/util/',
]);
$loader->registerNamespaces([
    'App\Util' => APP_PATH . '/util/'
]);
$loader->register();

$di = new FactoryDefault();

require_once APP_PATH . 'bootstrap.php';
require_once BASE_PATH . '/vendor/autoload.php';

$di->set('url', function () {
    $url = new Phalcon\Mvc\Url;
    $url->setBaseUri("{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}/");
    return $url;
});
$di->set('view', function () {
    return (new View())->setViewsDir(APP_PATH . '/views/');
});
$di->set('dispatcher', function () {
    $em = new Manager;
    $em->attach('dispatch:beforeException', function (Event $event, Dispatcher $dispatcher, Exception $exception) {
        $dispatcher->forward([
            'controller' => 'index',
            'action' => 'error',
            'params' => [$exception, $event],
        ]);
        return false;
    });
    $dis = new Dispatcher;
    $dis->setEventsManager($em);
    return $dis;
});
$di->set('cookies', function () {
    $c = new Cookies;
    $c->useEncryption(ENV_PRODUCTION);
    return $c;
});
$di->set('session', function () {
    $sess = new Files;
    $sess->start();
    return $sess;
});
$di->set('router', function () {
    $r = new Router;
    $r->removeExtraSlashes(true);

    $r->add('/', [
        'controller' => 'index',
        'action' => 'index',
    ])->setHttpMethods('GET')->setName('app.main');
    $r->add('/upload', [
        'controller' => 'index',
        'action' => 'upload',
    ])->setHttpMethods('POST')->setName('app.upload');
    $r->add('/update/{link:\w+}', [
        'controller' => 'index',
        'action' => 'update',
    ])->setHttpMethods('POST')->setName('app.update');
    $r->add('/delete/{link:\w+}', [
        'controller' => 'index',
        'action' => 'delete',
    ])->setHttpMethods('POST')->setName('app.delete');
    $r->add('/{link:\w+}', [
        'controller' => 'index',
        'action' => 'show',
    ])->setHttpMethods('GET')->setName('app.show');

    return $r;
});

#------------------------------------------------------------------------------
try {
    $app = new Application($di);
    $resp = $app->handle($_SERVER['REQUEST_URI']);
    $resp->send();
} catch (Exception $exception) {
    syslog(LOG_DEBUG, $exception);
}
