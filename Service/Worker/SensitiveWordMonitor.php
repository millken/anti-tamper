<?php
namespace Service\Worker;

use Baijian\Algorithm\AhoCorasick;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class SensitiveWordMonitor extends \Service\Service {
	private $ac;

	public function setKeyword($keyword) {
		$blacklist = array_unique(array_map('trim', explode(",", $keyword)));
		$this->ac = new AhoCorasick();
		$this->ac->build_tree($blacklist);
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

		$path = $path . ($query == '' ? '' : "?$query");

		$headers = [
			'Host' => $header_host,
			"User-Agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.75 Safari/537.36",
			'Accept' => 'text/html,application/xhtml+xml,application/xml',
			'Accept-Encoding' => 'default',
		];
		$nurl = sprintf("%s://%s:%d%s", $scheme, $host, $port, $path);
		$client = new Client();
		$promise = $client->getAsync($nurl, [
			'timeout' => 3.14,
			'headers' => $headers,
		]);
		$promise->then(
			function (ResponseInterface $res) use ($group, $url) {
				if ($res->getStatusCode() == 200) {
					$this->running($group, $url, $res->getBody()->getContents());
				} else {
					$this->log->warn(sprintf("%s got status: %d", $url, $res->getStatusCode()));
				}
			},
			function (RequestException $e) use ($url) {
				$this->log->error(sprintf("fetching %s error: %s", $url, $e->getMessage()));
			}
		)->wait();

	}

	private function running($group, $url, $body) {

		$data = [
			'class' => 'SensitiveWordMonitor',
			'group' => $group,
			'url' => $url,
			'time' => time(),
		];
		if ($this->ac->find($body)) {
			$this->events->trigger("notification", $data);
		}

	}

}
