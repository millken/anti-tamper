<?php
namespace Service\Async;

class Worker extends \Service\Service {
	public function getUrlMd5($group, $url, $host = null) {
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
		$path = isset($info['path']) ? $info['path']: '/';
		$query = isset($info['query']) ? $info['query'] : '';

		$client = new \swoole_http_client($host, $port);
		$client->set([
			'timeout' => 3,
			'keep_alive' => 1,
		]);

		$client->setHeaders([
			'Host' => $header_host,
			"User-Agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.75 Safari/537.36",
			'Accept' => 'text/html,application/xhtml+xml,application/xml',
			'Accept-Encoding' => 'gzip',
		]);

		$path = $path . ($query == '' ? '' : "?$query");
		$client->on("error", function($cli) use ($group, $url) {
			$this->log->warn("$url got error: ". print_r($cli, true) . "");
			$cli->close();
			return;
		});
		$client->get($path, function ($cli) use ($group, $url) {
			if ($cli->statusCode == 200) {
				$this->diffUrlMd5($group, $url, $cli->body);
			} else {
				$this->log->warn("$url got status: {$cli->statusCode}");
			}
			$cli->close();
		});

	}

	private function diffUrlMd5($group, $url, $body) {
		static $record;
		$md5 = md5($body);
		$is_trigger = true;
		if (!isset($record[$url])) {
			$record[$url] = $this->db->table("logs")->field("url_md5")
				->where("url=?", $url)->order("id desc")->fetchOne();
			$is_trigger = false;
		}
		if ($md5 != $record[$url]) {
			$record[$url] = $md5;
			$data = [
				'url' => $url,
				'url_md5' => $md5,
				'url_body' => addslashes($body),
			];
			$id = $this->db->table("logs")->insert($data);
			$data = [
				'log_id' => $id,
				'group' => $group,
				'url' => $url,
				'url_md5' => $md5,
				'time' => time(),
			];
			if($is_trigger) {
				$this->events->trigger("notification", $data);
			}
		}

	}

}
