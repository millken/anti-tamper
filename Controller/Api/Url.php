<?php

namespace Controller\Api;

class Url extends \Controller\Controller {
	private $worker;
	public function __construct() {
		$this->worker = new \Service\Async\Worker();
	}

	public function add() {
		$worker = new \Service\Async\Worker();
		$interval = $this->request->get("interval", "intval", 60);
		$group = $this->request->get('group', 'trim');
		if (!empty($group) && $interval > 0) {
			$this->db->table('urls')->where("`group`=?", $group)->delete();
			foreach ((array) $this->request->get['url'] as $url) {
				$data = [
					'url' => $url,
					'group' => $group,
					'interval' => $interval,
					'status' => 1,
				];
				$id = $this->db->table("urls")->insert($data);
				swoole_timer_tick($interval * 1000, function ($timer_id) use ($id, $url) {
					$nid = $this->db->table("urls")->field("id")->where("id=?", $id)->fetchOne();
					if (!$nid) {
						\swoole_timer_clear($timer_id);
						$this->log->info("clear timer_id :" . $timer_id);
					} else {
						$this->worker->getUrlMd5($url);
					}
				});

			}
		}

		$data = $this->request->get;
		$this->ajaxReturn($data);
	}
}
