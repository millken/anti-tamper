<?php

namespace Controller\Home;

class Index extends \Controller\Controller {
	public function index() {
		$urls = $this->db->table("urls")->select();
		$this->view->assign('urls', $urls);
		$this->view->display("index.html");
	}

	public function test() {
		$body = file_get_contents("/tmp/test");
		$this->response->setOutput($body);
	}
}
