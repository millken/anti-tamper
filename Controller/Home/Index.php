<?php

namespace Controller\Home;

class Index extends \Controller\Controller {
	public function index() {
		$urls = $this->db->table("urls")->where("status=1")->select();
		print_r($urls);
		$this->view->display("index.html");
	}

	public function test() {
		$body = file_get_contents("/tmp/test");
		$this->response->setOutput($body);
	}
}
