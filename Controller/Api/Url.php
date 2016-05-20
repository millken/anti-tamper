<?php

namespace Controller\Api;

class Url extends \Controller\Controller {
	private $worker;
	public function __construct() {
		$this->worker = new \Service\Async\Worker();
	}

	public function add() {
		$worker = new \Service\Async\Worker();
		$interval = $this->request->post("interval", "intval", 60);
		$group = $this->request->post('group', 'trim');
		$type = $this->request->post('type', 'intval', 2); //type=1,source. type=2, outside
		$this->log->info("got post data : " . print_r($this->request->post, true));
		if (!empty($group) && $interval > 0 && isset($this->request->post['url'])) {
			$urls = $this->request->post['url'];
			$this->db->table('urls')->where("`group`=?", $group)->delete();
			foreach ((array) $urls as $url) {
				$host = null;
				if($type == 1 and strpos($url, "|") !== false) {
					list($url, $host) = explode("|", $url);
				}
				$data = [
					'url' => $url,
					'group' => $group,
					'interval' => $interval,
					'status' => 1,
				];
				$id = $this->db->table("urls")->insert($data);
				swoole_timer_tick($interval * 1000, function ($timer_id) use ($id, $group, $url, $host) {
					$nid = $this->db->table("urls")->field("id")->where("id=?", $id)->fetchOne();
					if (!$nid) {
						\swoole_timer_clear($timer_id);
						$this->log->info("clear timer_id :" . $timer_id);
					} else {
						$this->worker->getUrlMd5($group, $url, $host);
					}
				});

			}
			$data = [
				'status' => 1,
				'info' => 'ok',
			];
		} else {
			$data = [
				'status' => 0,
				'info' => 'param error',
			];
		}

		$this->ajaxReturn($data);
	}

	public function del() {
		$this->log->info("got post data : " . print_r($this->request->post, true));
		$group = $this->request->post('group', 'trim');
		$this->db->table('urls')->where("`group`=?", $group)->delete();
			$data = [
				'status' => 1,
				'info' => 'ok',
			];

		$this->ajaxReturn($data);
	}

	
}
