<?php

namespace Controller;

class View extends \Ypf\Core\View {

	private $response;

	public function setResponse($response) {
		$this->response = &$response;
	}

	public function display($template) {
		$output = $this->fetch($template);
		\Ypf\Swoole\Response::getInstance()->setOutput($output);
	}
}
