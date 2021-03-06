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

//log
$logfile = new \Ypf\Log\Filter\File();
$logfile->setFile($config->get('log.default.log_file'));
$logmaster = new \Ypf\Log\Master();
$logmaster->addFilter(\Ypf\Log\Level::ALL, $logfile, $config->get('log.default.log_layout'));
$app->set('log', $logmaster);

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

//events
$events = new \Service\Events();
$events->load($config->get('event'));
$app->set('events', $events);

//view
$view = new \Controller\View();
$view->setTemplateDir(__APP__ . '/View/');
$app->set('view', $view);

$app->addPreAction("Controller\Common\Router\index");
$app->start();
