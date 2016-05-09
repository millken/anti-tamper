<?php

namespace Controller;

class Controller extends \Ypf\Core\Controller {

	public function ajaxReturn($data = []) {
		$this->response->addHeader("Server", "");
		$this->response->setOutput(json_encode($data));
	}
}
