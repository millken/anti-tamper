<?php

namespace Service;

use League\Event\Emitter;

class Events extends Service {
	private $emitter;

	public function load($config = []) {
		$this->emitter = new Emitter;
		foreach ($config as $name => $cf) {
			if (!isset($cf['status']) or !$cf['status']) {
				continue;
			}
			$priority = isset($cf['priority']) ? intval($cf['priority']) : 0;
			$this->bind($cf['name'], new $cf['listener'](), $priority);
		}
	}

	public function bind($name, $listener) {
		$this->emitter->addListener($name, $listener);
	}

	public function trigger($name, $param = []) {
		$this->emitter->emit($name, $param);
	}
}
