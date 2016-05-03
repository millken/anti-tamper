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
//config
$config = new \Ypf\Lib\Config();
$config->load(__CONF__);

$host = $config->get('websocket.server.host');
$port = $config->get('websocket.server.port');
$server = new swoole_websocket_server($host, $port, SWOOLE_BASE);

$server->on('open', function (swoole_websocket_server $server, swoole_http_request $request) {
    echo "server#{$server->worker_pid}: handshake success with fd#{$request->fd}\n";
//    var_dump($request);
});

$server->on('message', function (swoole_websocket_server $server, $frame) {
	echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
	$server->push($frame->fd, "this is server");
});

$server->on('close', function ($ser, $fd) {
	echo "client {$fd} closed\n";
});

$server->start();
?>
