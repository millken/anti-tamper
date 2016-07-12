<?php

namespace Service\Worker;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use \LibDNS\Decoder\DecoderFactory;
use \LibDNS\Encoder\EncoderFactory;
use \LibDNS\Messages\MessageFactory;
use \LibDNS\Messages\MessageTypes;
use \LibDNS\Records\QuestionFactory;
use \LibDNS\Records\ResourceQTypes;

class DnsHijackMonitor extends \Service\Service {
	private $dnsServer;
	/**
	 * https://github.com/symfony/http-foundation/blob/master/IpUtils.php#L36
	 * Compares two IPv4 addresses.
	 * In case a subnet is given, it checks if it contains the request IP.
	 *
	 * @param string $requestIp IPv4 address to check
	 * @param string $ip        IPv4 address or subnet in CIDR notation
	 *
	 * @return bool Whether the request IP matches the IP, or whether the request IP is within the CIDR subnet
	 */
	public static function checkIp4($requestIp, $ip) {
		if (false !== strpos($ip, '/')) {
			list($address, $netmask) = explode('/', $ip, 2);
			if ($netmask === '0') {
				// Ensure IP is valid - using ip2long below implicitly validates, but we need to do it manually here
				return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
			}
			if ($netmask < 0 || $netmask > 32) {
				return false;
			}
		} else {
			$address = $ip;
			$netmask = 32;
		}
		return 0 === substr_compare(sprintf('%032b', ip2long($requestIp)), sprintf('%032b', ip2long($address)), 0, $netmask);
	}

	private function checkIps($ip, $ext_ips) {
		$ngips = $this->config->get('dnshijackmonitor.server.ngips');
		$ips_arr = array_merge($ngips, $ext_ips);
		foreach ($ips_arr as $ips) {
			if (self::checkIp4($ip, $ips)) {
				return true;
			}
		}
		return false;
	}

	public function start($id) {
		$this->makeDnsServerCache();
		$row = $this->db->table("dnshijack_monitor")->where("id=?", $id)->fetch();
		foreach ((array) $this->dnsServer['data'] as $ds) {
			$view_name = $ds['view_name'];
			$rnd_server = $ds['info'][rand(0, count($ds['info']) - 1)]['vip'];

			$client = new \swoole_client(SWOOLE_SOCK_UDP, SWOOLE_SOCK_ASYNC);
			$client->on("connect", function (\swoole_client $cli) use ($row) {
				$question = (new QuestionFactory)->create(ResourceQTypes::A);
				$question->setName($row["subdomain"]);
				$request = (new MessageFactory)->create(MessageTypes::QUERY);
				$request->getQuestionRecords()->add($question);
				$request->isRecursionDesired(true);
				$encoder = (new EncoderFactory)->create();
				$requestPacket = $encoder->encode($request);
				$cli->send($requestPacket);
			});
			$client->on("receive", function (\swoole_client $cli, $data) use ($view_name, $rnd_server, $row) {
				$decoder = (new DecoderFactory)->create();
				try {
					$response = $decoder->decode($data);
					if ($response->getResponseCode() !== 0) {
						$this->log->warning("dns $view_name | $rnd_server response : " . $response->getResponseCode());
						return;
					}
					$answers = $response->getAnswerRecords();
					if (count($answers)) {
						foreach ($response->getAnswerRecords() as $record) {
							$this->log->trace("$view_name |$rnd_server => {$row['subdomain']} |" . $record->getData());
							$ext_ips = explode(',', $row['ext_ips']);
							if (!$this->checkIps($record->getData(), $ext_ips)) {
								$data = [
									'class' => 'DnsHijackMonitor',
									'group' => $row['group'],
									'time' => time(),
									'view_name' => $view_name,
									'dns_server' => $rnd_server,
									'dns_record' => (string) $record->getData(),
									'domain' => $row['subdomain'],
								];
								$this->events->trigger('notification', $data);
							}

						}
					} else {
						$this->log->info("$view_name |$rnd_server => {$row['subdomain']} | NoAnswer");
					}
				} catch (\UnexpectedValueException $e) {
					$this->log->warning("$view_name | $rnd_server => {$row['subdomain']} |" . $e->getMessage());
				}
				$cli->close();
			});
			$client->on("error", function (\swoole_client $cli) {});
			$client->on("close", function (\swoole_client $cli) {});
			$client->connect($rnd_server, 53);
			swoole_timer_after(3000, function () use ($client) {
				@$client->close();
			});

			/*
				if ($client->connect($rnd_server, 53, 3)) {
					$question = (new QuestionFactory)->create(ResourceQTypes::A);
					$question->setName($row["subdomain"]);
					$request = (new MessageFactory)->create(MessageTypes::QUERY);
					$request->getQuestionRecords()->add($question);
					$request->isRecursionDesired(true);
					$encoder = (new EncoderFactory)->create();
					$requestPacket = $encoder->encode($request);
					$client->send($requestPacket);
					$data = $client->recv(512);
					$decoder = (new DecoderFactory)->create();
					try {
						$response = $decoder->decode($data);
						if ($response->getResponseCode() !== 0) {
							echo "    Server returned error code " . $response->getResponseCode() . ".\n";
							return;
						}
						$answers = $response->getAnswerRecords();
						if (count($answers)) {
							foreach ($response->getAnswerRecords() as $record) {
								echo " $view_name |$rnd_server =>   " . $record->getData() . "\n";
							}
						} else {
							echo "    Not found.\n";
						}
					} catch (\UnexpectedValueException $e) {
						echo $e->getMessage();
					}
					$client->close();

				} else {
					echo "connect fail\n";
				}
			*/
		}
	}

	private function makeDnsServerCache() {
		$file = $this->config->get("dnshijackmonitor.server.file");
		if (file_exists($file)) {
			if (empty($this->dnsServer)) {
				$this->dnsServer = json_decode(file_get_contents($file), true);
			}

			if (time() - filemtime($file) < 86400) {
				return;
			}

		}
		$headers = [
			"User-Agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.75 Safari/537.36",
			'Accept' => 'application/json, text/javascript, */*; q=0.01',
			'Accept-Encoding' => 'deflate',
			'Referer' => 'http://tools.fastweb.com.cn/Index/Around',
			'X-Requested-With' => 'XMLHttpRequest',
		];
		$form_params = [
			'isp_name' => '%E7%94%B5%E4%BF%A1%2C%E7%BD%91%E9%80%9A%2C%E7%A7%BB%E5%8A%A8%2C',
			'city_name' => '%E8%BE%BD%E5%AE%81%2C%E5%90%89%E6%9E%97%2C%E9%BB%91%E9%BE%99%E6%B1%9F%2C%E5%8C%97%E4%BA%AC%2C%E5%A4%A9%E6%B4%A5%2C%E6%B2%B3%E5%8C%97%2C%E5%B1%B1%E8%A5%BF%2C%E5%86%85%E8%92%99%E5%8F%A4%2C%E5%B1%B1%E4%B8%9C%2C%E9%99%95%E8%A5%BF%2C%E7%94%98%E8%82%83%2C%E9%9D%92%E6%B5%B7%2C%E5%AE%81%E5%A4%8F%2C%E6%96%B0%E7%96%86%2C%E9%87%8D%E5%BA%86%2C%E5%9B%9B%E5%B7%9D%2C%E8%B4%B5%E5%B7%9E%2C%E4%BA%91%E5%8D%97%2C%E8%A5%BF%E8%97%8F%2C%E6%B2%B3%E5%8D%97%2C%E6%B9%96%E5%8C%97%2C%E6%B9%96%E5%8D%97%2C%E4%B8%8A%E6%B5%B7%2C%E6%B1%9F%E8%8B%8F%2C%E6%B5%99%E6%B1%9F%2C%E5%AE%89%E5%BE%BD%2C%E7%A6%8F%E5%BB%BA%2C%E6%B1%9F%E8%A5%BF%2C%E5%B9%BF%E4%B8%9C%2C%E5%B9%BF%E8%A5%BF%2C%E6%B5%B7%E5%8D%97%2C',
		];
		$url = "http://tools.fastweb.com.cn/Index/infocontent";
		$client = new Client();
		$promise = $client->postAsync($url, [
			'stream' => true,
			'timeout' => 5.14,
			'headers' => $headers,
			'form_params' => $form_params,
		]);
		$promise->then(
			function (ResponseInterface $res) {
				if ($res->getStatusCode() == 200) {
					$body = $res->getBody()->getContents();
					$this->log->debug("dns server response: " . $body);
					$this->dnsServer = json_decode($body, true);

					file_put_contents($this->config->get("dnshijackmonitor.server.file"), $body);
				} else {
					$this->log->warning(sprintf(" got status: %d", $res->getStatusCode()));
				}
			},
			function (RequestException $e) {
				$this->log->error(sprintf("request error: %s", $e->getMessage()));
			}
		)->wait();
	}

}
