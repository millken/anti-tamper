<?php

namespace Controller\Api;

class ScreenShotMonitor extends \Controller\Controller {
	private $worker;
	const TABLE = 'screenshot';

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
		$url = $this->request->post('url', 'trim', '');
		$this->log->info("got post data : " . print_r($this->request->post, true));
		if ($group && $interval > 0 && $url) {
			$this->db->table(self::TABLE)->where("`group`=?", $group)->delete();
			$data = [
				'group' => $group,
				'url' => $url,
				'interval' => $interval,
				'status' => 1,
			];
			$this->worker = new \Service\Worker\ScreenShotMonitor();
			$id = $this->db->table(self::TABLE)->insert($data);
			swoole_timer_tick($interval * 1000, function ($timer_id) use ($id) {
				$nid = $this->db->table(self::TABLE)->field("id")->where("id=?", $id)->fetchOne();
				if (!$nid) {
					\swoole_timer_clear($timer_id);
					$this->log->info("clear ScreenShotMonitor timer_id :" . $timer_id);
				} else {
					$this->worker->start($id);
				}
			});

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
		$url = $this->request->post('url', 'trim', '');
		$group = $this->request->post('group', 'trim', '');
		$this->db->table(self::TABLE)->where("`group`=? and `url`=?", $group, $url)->delete();
		$data = [
			'status' => 1,
			'info' => 'ok',
		];

		$this->ajaxReturn($data);
	}
}
