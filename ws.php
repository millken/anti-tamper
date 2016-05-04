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
$server = new swoole_websocket_server($host, $port);
$lock = false;


$server->on('message', function (swoole_websocket_server $server, $frame) {
	global $lock, $config;
	echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
	$server->push($frame->fd, "this is server");
	if(!$lock) {
		$lock = true;
			$bean = (new Beanstalk\Pool)
			->addServer($config->get("db.beanstalk.host"), $config->get("db.beanstalk.port"))
			->watchTube($config->get("db.beanstalk.tube"));
		
		swoole_timer_tick(5000, function ($timer_id) use ($server, $bean) {
			while(true) {
				try
				{
					$job = $bean->reserve($timeout = 1);
					$data = $job->getMessage();
					foreach($server->connections as $fd) {

						$server->push($fd, json_encode($data));
					}
					$job->delete();
				}catch(Beanstalk\Exception $e){
					switch ($e->getCode())
					{
						case Beanstalk\Exception::TIMED_OUT:
						   // echo "Timed out waiting for a job.  Retrying in 1 second.\n";
							break;
						    //sleep(1);
						    //continue;
						    break;

						default:
						    throw $e;
						    break;
					}
				}
			}
		});
	}
});

$server->on('close', function ($ser, $fd) {
	echo "client {$fd} closed\n";
});


$server->start();
?>
