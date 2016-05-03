<?php

namespace Controller\Home;

class Worker extends \Controller\Controller {
	private $worker;
	public function __construct() {
		$this->worker = new \Service\Async\Worker();
	}

	public function loader() {
		$urls = $this->db->table("urls")->where("status=1")->select();

		foreach ($urls as $url) {
			$interval = $url['interval'] > 0 ? $url['interval'] * 1000 : 60000;
			swoole_timer_tick($interval, function () use ($url) {
				$this->worker->getUrlMd5($url['url']);
			});
		}
	}

}
