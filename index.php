#!/usr/bin/env php
<?php
error_reporting(E_ALL);
ini_set('limit_memory', '512M');
date_default_timezone_set('Asia/Shanghai');
define("__MODE__", "swoole");
define("__APP__", __DIR__);
define("__ROOT__", dirname(__DIR__));
if (get_cfg_var('app.env')) {
	define("__CONF__", __DIR__ . '/conf.d/' . get_cfg_var('app.env'));
} else {
	define("__CONF__", __DIR__ . '/conf.d/default');
}
require __DIR__ . '/vendor/autoload.php';
//require __ROOT__ . '/Ypf/src/Ypf/Ypf.php';
//spl_autoload_register("\\Ypf\\Ypf::autoload");
$setting = array(
	'root' => __DIR__,
);
$app = new \Ypf\Swoole($setting);
//config
$config = new \Ypf\Lib\Config();
$config->load(__CONF__);
$app->set('config', $config);

$app->setServerConfigIni(__CONF__ . '/server.conf');
$app->setWorkerConfigPath(__CONF__ . '/worker/');

//db
$db = new \Ypf\Database\Mysql($config->get("db.master"));
$app->set('db', $db);

//cache
$cache = new \Ypf\Swoole\Cache(1024);
$app->set('cache', $cache);
$app->setCache($cache);

//request
$app->set('request', new \Ypf\Swoole\Request());

//response
$response = new \Ypf\Swoole\Response();
$app->set('response', $response);

//view
$view = new \Controller\View();
$view->setTemplateDir(__APP__ . '/View/');
$app->set('view', $view);

$app->addPreAction("Controller\Common\Router\index");
$app->start();
