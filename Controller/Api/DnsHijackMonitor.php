<?php

namespace Controller\Api;

class DnsHijackMonitor extends \Controller\Controller {
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
		$subdomain = $this->request->post('subdomain', 'trim', '');
		$ext_ips = $this->request->post('ext_ips', 'trim', '');
		$this->log->info("got post data : " . print_r($this->request->post, true));
		if ($group && $interval > 0 && $subdomain) {
			$this->db->table('dnshijack_monitor')->where("`group`=? and `subdomain`=?", $group, $subdomain)->delete();
			$data = [
				'group' => $group,
				'subdomain' => $subdomain,
				'interval' => $interval,
				'ext_ips' => $ext_ips,
				'status' => 1,
			];
			$this->worker = new \Service\Worker\DnsHijackMonitor();
			$id = $this->db->table("dnshijack_monitor")->insert($data);
			swoole_timer_tick($interval * 1000, function ($timer_id) use ($id) {
				$nid = $this->db->table("dnshijack_monitor")->field("id")->where("id=?", $id)->fetchOne();
				if (!$nid) {
					\swoole_timer_clear($timer_id);
					$this->log->info("clear DnsHijackMonitor timer_id :" . $timer_id);
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
		$subdomain = $this->request->post('subdomain', 'trim', '');
		$group = $this->request->post('group', 'trim', '');
		$this->db->table('dnshijack_monitor')->where("`group`=? and `subdomain`=?", $group, $subdomain)->delete();
		$data = [
			'status' => 1,
			'info' => 'ok',
		];

		$this->ajaxReturn($data);
	}
}
