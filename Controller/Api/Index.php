<?php

namespace Controller\Api;

class Index extends \Controller\Controller {
	static $map = [
		'memory' => 'Controller\Api\Index\memory',
	];
	public function index() {
		$data = $this->request->get;
		if (isset($data['action']) && array_key_exists($data['action'], self::$map)) {
			$this->action(self::$map[$data['action']]);
		} else {
			$this->ajaxReturn([]);
		}

	}

	public function memory() {
		$data = [
			'usage' => memory_get_usage(),
			'pid' => getmypid(),
		];
		$this->ajaxReturn($data);
	}
}
