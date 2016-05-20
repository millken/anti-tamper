<?php

namespace Controller\Api;

class KeywordMonitor extends \Controller\Controller {
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
		$url = $this->request->post('url', 'trim', '');
		$keyword = $this->request->post('keyword', 'trim', '');
		$this->log->info("got post data : " . print_r($this->request->post, true));
		if ($group && $interval > 0 && $url && $keyword) {
			$this->db->table('urls')->where("class='keywordMonitor' and `group`=?", $group)->delete();
			$host = null;
			if (strpos($url, "|") !== false) {
				list($url, $host) = explode("|", $url);
			}
			$data = [
				'class' => 'keywordMonitor',
				'url' => $url,
				'group' => $group,
				'interval' => $interval,
				'status' => 1,
			];
			$this->worker = new \Service\Worker\KeywordMonitor();
			$this->worker->setKeyword($keyword);
			$id = $this->db->table("urls")->insert($data);
			swoole_timer_tick($interval * 1000, function ($timer_id) use ($id, $group, $url, $host) {
				$nid = $this->db->table("urls")->field("id")->where("id=?", $id)->fetchOne();
				if (!$nid) {
					\swoole_timer_clear($timer_id);
					$this->log->info("clear keywordMonitor timer_id :" . $timer_id);
				} else {
					$this->worker->start($group, $url, $host);
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
		$group = $this->request->post('group', 'trim', '');
		$this->db->table('urls')->where("class='keywordMonitor' and `group`=?", $group)->delete();
		$data = [
			'status' => 1,
			'info' => 'ok',
		];

		$this->ajaxReturn($data);
	}
}
