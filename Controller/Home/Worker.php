<?php

namespace Controller\Home;
use GuzzleHttp\Client;

class Worker extends \Controller\Controller {

	public function loader() {
		$this->loadSensitiveWordMonitor();
	}

	private function loadSensitiveWordMonitor() {
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

}
