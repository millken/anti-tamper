<?php
namespace Service\Async;

class Worker extends \Service\Service {
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
		$header_host = $info['host'];
		$port = isset($info['port']) ? $info['port'] : 80;
		$path = $info['path'];
		$query = isset($info['query']) ? $info['query'] : '';

		$client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
		$client->on("connect", function (\swoole_client $cli) use ($header_host, $path, $query) {
			$path = $path . ($query == '' ? '' : "?$query");
			$header = "GET $path HTTP/1.1\r\n";
			$header .= "Host: $header_host\r\n";
			$header .= "Connection: close\r\n";
			$header .= "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.75 Safari/537.36\r\n";
			$header .= "\r\n";
			echo $header;
			$cli->send($header);
		});
		$client->on("receive", function (\swoole_client $cli, $data) use ($url) {
			list($header, $body) = explode("\r\n\r\n", $data, 2);

			echo $header;
			echo $body;
			if (strpos($header, "200 OK") !== false) {
				$this->diffUrlMd5($url, $body);
			}

		});
		$client->on("error", function (\swoole_client $cli) {
			//echo "error\n";
		});
		$client->on("close", function (\swoole_client $cli) {
			//echo "Connection close\n";
		});
		$client->connect($host, $port, 1);
	}

	private function diffUrlMd5($url, $body) {
		static $record;
		$md5 = md5($body);
		if (!isset($record[$url])) {
			$record[$url] = $this->db->table("logs")->field("url_md5")->where("url=?", $url)->order("id desc")->fetchOne();
		}
		if ($md5 != $record[$url]) {
			$record[$url] = $md5;
			$data = [
				'url' => $url,
				'url_md5' => $md5,
				'url_body' => $body,
			];
			$id = $this->db->table("logs")->insert($data);
			$this->notifyUrl($id, $url);
		}

	}

	private function notifyUrl($id, $url) {
		$data = [
			'log_id' => $id,
			'url' => $url,
			'time' => time(),
		];
		(new \Beanstalk\Pool)
			->addServer($this->config->get("db.beanstalk.host"), $this->config->get("db.beanstalk.port"))
			->useTube($this->config->get("db.beanstalk.tube"))
			->put(json_encode($data));

	}
}
