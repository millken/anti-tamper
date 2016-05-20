<?php
namespace Service\Worker;

class KeywordMonitor extends \Service\Service {
	private $regex;

	public function setKeyword($keyword) {
		$blacklist = array_map('trim', explode(",", $keyword));
		$this->regex = sprintf('~%s~', implode('|', array_map(function ($value) {
			if (isset($value[0]) && $value[0] == '[') {
				$value = substr($value, 1, -1);
			} else {
				$value = preg_quote($value);
			}
			return '(?:' . $value . ')';
		}, array_unique($blacklist))));

	}
	public function start($group, $url, $host = null) {
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
		$path = isset($info['path']) ? $info['path'] : '/';
		$query = isset($info['query']) ? $info['query'] : '';

		$client = new \swoole_http_client($host, $port);
		$client->set([
			'timeout' => 3,
			'keep_alive' => 0,
		]);

		$client->setHeaders([
			'Host' => $header_host,
			"User-Agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.75 Safari/537.36",
			'Accept' => 'text/html,application/xhtml+xml,application/xml',
			'Accept-Encoding' => 'default',
		]);

		$path = $path . ($query == '' ? '' : "?$query");
		$client->on("error", function ($cli) use ($group, $url) {
			//$this->log->warn("$url got error: ". print_r($cli, true) . "");
			//$cli->close();
			return;
		});
		$client->get($path, function ($cli) use ($group, $url) {
			if ($cli->statusCode == 200) {
				$this->running($group, $url, $cli->body);
			} else {
				$this->log->warn("$url got status: {$cli->statusCode}");
			}
			$cli->close();
		});

	}

	private function running($group, $url, $body) {

		$data = [
			'class' => 'KeywordMonitor',
			'group' => $group,
			'url' => $url,
			'time' => time(),
		];
		if (preg_match($this->regex, $body)) {
			$this->events->trigger("notification", $data);
		}

	}

}
