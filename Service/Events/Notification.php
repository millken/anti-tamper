<?php

namespace Service\Events;

use Beanstalk\Pool;
use League\Event\EventInterface;
use League\Event\ListenerInterface;

class Notification extends \Service\Service implements ListenerInterface {
	public function isListener($listener) {
		return $listener === $this;
	}

	public function handle(EventInterface $event, $param = []) {
		$this->log->event("event: " . $event->getName() . ", param: " . print_r($param, true));
		$config = $this->config->get("db.beanstalk");
		(new Pool)
			->addServer($config["host"], $config["port"])
			->useTube($config["tube"])
			->put(json_encode($param));
	}

}
