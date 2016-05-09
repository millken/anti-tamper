<?php

namespace Controller\Api;

class Index extends \Controller\Controller {
	static $map = [
		'add' => 'Controller\Api\Url\add',
	];
	public function index() {
		$data = $this->request->get;
		if (isset($data['action']) && array_key_exists($data['action'], self::$map)) {
			$this->action(self::$map[$data['action']]);
		} else {
			$this->ajaxReturn([]);
		}

	}
}
