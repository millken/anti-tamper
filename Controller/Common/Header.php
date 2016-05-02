<?php

namespace Cat\Common;

class Header extends \Cat\Controller {
	public function index() {
		$this->view->assign('title', 'This is a header variable!');
		return $this->view->fetch("header.tpl");
	}
}
