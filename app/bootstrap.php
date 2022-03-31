<?php
/**
 * Bootstrap common for MVC & CLI applications
 *
 * $di MUST be defined before including this script.
 * $di MUST instantiate \Phalcon\Di\FactoryDefault
 *
 * BASE_PATH & APP_PATH constants MUST be defined before
 * including this script
 */

use Phalcon\Config\Adapter\Ini;
use Phalcon\Db\Adapter\Pdo\Postgresql;
use Phalcon\Logger\Adapter\Syslog;

#------------------------------------------------------------------------------
$config = new Ini(APP_PATH . '/config.ini');
define('STORAGE_PATH', BASE_PATH . trim($config->path('app.storage_path', 'store')) . '/');

$di->set('config', $config);
$di->set('log', function () use ($config) {
    return new Syslog($config->path('app.ident'));
});
$di->set('db', function () use ($config) {
    return new Postgresql((array)$config->get('db'));
});
$di->set('crypt', function () use ($config) {
    $c = new Phalcon\Crypt;
    $c->setCipher('aes-256-ctr');
    $c->setKey($config->path('app.crypt_key'));
    return $c;
});

