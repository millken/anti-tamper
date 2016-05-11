<?php

namespace Controller;

class Controller extends \Ypf\Core\Controller {

	public function ajaxReturn($data = []) {
		$this->response->addHeader("Content-type", "application/json");
		$this->response->setOutput(json_encode($data));
	}
}
