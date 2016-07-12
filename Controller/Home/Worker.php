<?php

namespace Controller\Home;
use GuzzleHttp\Client;

class Worker extends \Controller\Controller {

	public function loader() {
		//$this->loadKeywordMonitor();
		//$this->loadSensitiveWordMonitor();
		//$this->loadTamperMonitor();
		$this->loadDnsHijackMonitor();
	}

	private function loadKeywordMonitor() {
		$this->log->info("loading KeywordMonitor task...");
		$urls = $this->db->table("urls")->where("class='KeywordMonitor' and status=1")->select();
		$client = new Client();
		foreach ($urls as $url) {

			$client->postAsync('http://127.0.0.1:9002/api/KeywordMonitor?action=add', [
				'form_params' => [
					'url' => implode("|", array_filter([$url['url'], $url['host']])),
					'interval' => $url['interval'],
					'group' => $url['group'],
					'keyword' => $url['keyword'],
				],
			])->wait();

		}
	}

	private function loadSensitiveWordMonitor() {
		$this->log->info("loading SensitiveWordMonitor task...");
		$urls = $this->db->table("urls")->where("class='SensitiveMonitor' and status=1")->select();
		$client = new Client();
		foreach ($urls as $url) {

			$client->postAsync('http://127.0.0.1:9002/api/SensitiveWordMonitor?action=add', [
				'form_params' => [
					'url' => implode("|", array_filter([$url['url'], $url['host']])),
					'interval' => $url['interval'],
					'group' => $url['group'],
					'keyword' => $url['keyword'],
				],
			])->wait();

		}
	}

	private function loadTamperMonitor() {
		$this->log->info("loading TamperMonitor task...");
		$groups = $this->db->table("urls")
			->where("class='TamperMonitor' and status=1")
			->group('`group`')->select();
		foreach ($groups as $g) {
			$url = [];
			$interval = $g['interval'];
			$group = $g['group'];
			$urls = $this->db->table("urls")->where("class='TamperMonitor' and status=1 and `group`='{$group}'")->select();
			foreach ($urls as $u) {
				$url[] = implode("|", array_filter([$u['url'], $u['host']]));
			}
			$client = new Client();

			$client->postAsync('http://127.0.0.1:9002/api/TamperMonitor?action=add', [
				'form_params' => [
					'url' => $url,
					'interval' => $interval,
					'group' => $group,
				],
			])->wait();
		}
	}

	private function loadDnsHijackMonitor() {
		$this->log->info("loading DnsHijackMonitor task...");
		$rows = $this->db->table("dnshijack_monitor")->where("status=1")->select();
		$client = new Client();
		foreach ($rows as $row) {

			$client->postAsync('http://127.0.0.1:9002/api/DnsHijackMonitor?action=add', [
				'form_params' => [
					'interval' => $row['interval'],
					'group' => $row['group'],
					'subdomain' => $row['subdomain'],
					'ext_ips' => $row['ext_ips'],
				],
			])->wait();

		}
	}
}
