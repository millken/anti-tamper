<?php

namespace Controller\Home;

class Worker extends \Controller\Controller {

	public function loader() {
		$urls = $this->db->table("urls")->where("status=1")->select();

		foreach ($urls as $url) {
			swoole_timer_tick(3000, function () use ($url) {
				$this->getUrlMd5($url['url']);
			});
		}
	}

	public function getUrlMd5($url, $host = null) {
		$info = parse_url($url);
		if ($info === false) {
			return;
		}
		$scheme = $info['scheme'];
		if ($scheme !== 'http') {
			return;
		}
		$host = $host == null ? $info['host'] : $host;
		$port = isset($info['port']) ? $info['port'] : 80;
		$path = $info['path'];
		$query = isset($info['query']) ? $info['query'] : '';

		$client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
		$client->on("connect", function (\swoole_client $cli) use ($path, $query) {
			$path = $path . ($query == '' ? '' : "?$query");
			$cli->send("GET $path HTTP/1.1\r\n\r\n");
		});
		$client->on("receive", function (\swoole_client $cli, $data) use ($url) {
			list($header, $body) = explode("\r\n\r\n", $data);
			$data = [
				'url' => $url,
				'url_md5' => md5($body),
			];
			$this->db->table("logs")->insert($data);
		});
		$client->on("error", function (\swoole_client $cli) {
			//echo "error\n";
		});
		$client->on("close", function (\swoole_client $cli) {
			//echo "Connection close\n";
		});
		$client->connect($host, $port, 1);
	}

}
