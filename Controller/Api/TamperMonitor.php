<?php

namespace Controller\Api;

class TamperMonitor extends \Controller\Controller {
	private $worker;

	public function index() {
		$action = $this->request->get('action', 'trim', '');
		switch ($action) {
		case 'add':
		case 'del':
			$this->$action();
			break;
		default:
			$this->ajaxReturn([]);
			break;
		}

	}

	private function add() {
		$interval = $this->request->post("interval", "intval", 60);
		$group = $this->request->post('group', 'trim', '');
		$urls = isset($this->request->post['url']) ? $this->request->post['url'] : [];
		$this->log->info("got post data : " . print_r($this->request->post, true));
		if ($group && $interval > 0 && is_array($urls) && count($urls)) {
			$urls = array_unique($urls);
			$this->db->table('urls')->where("class='TamperMonitor' and `group`=?", $group)->delete();
			foreach ($urls as $url) {

				$host = null;
				if (strpos($url, "|") !== false) {
					list($url, $host) = explode("|", $url);
				}
				$data = [
					'class' => 'TamperMonitor',
					'url' => $url,
					'group' => $group,
					'host' => $host,
					'interval' => $interval,
					'status' => 1,
				];
				$this->worker = new \Service\Worker\TamperMonitor();
				$id = $this->db->table("urls")->insert($data);
				swoole_timer_tick($interval * 1000, function ($timer_id) use ($id, $group, $url, $host) {
					$nid = $this->db->table("urls")->field("id")->where("id=?", $id)->fetchOne();
					if (!$nid) {
						\swoole_timer_clear($timer_id);
						$this->log->info("clear TamperMonitor timer_id :" . $timer_id);
					} else {
						$this->worker->start($group, $url, $host);
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

	private function del() {
		$this->log->info("got post data : " . print_r($this->request->post, true));
		$group = $this->request->post('group', 'trim', '');
		$this->db->table('urls')->where("class='TamperMonitor' and `group`=?", $group)->delete();
		$data = [
			'status' => 1,
			'info' => 'ok',
		];

		$this->ajaxReturn($data);
	}
}
