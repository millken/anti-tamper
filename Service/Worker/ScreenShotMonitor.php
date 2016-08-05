<?php

namespace Service\Worker;
use Image\PerceptualHash;
//use Image\PerceptualHash\Algorithm\DifferenceHash;
use Image\PerceptualHash\Algorithm\PerceptionHash;
use Screen\Capture;

class ScreenShotMonitor extends \Service\Service {
	const TABLE = 'screenshot';

	public function start($id) {
		$row = $this->db->table(self::TABLE)->where("id=?", $id)->fetch();
		if ($row) {
			$this->screenCaptureUrl($row['url']);
		}
		//$this->compareCapture("/tmp/screen_output/1-3.jpg", "/tmp/screen_output/1-0.jpg");
	}

	private function screenCaptureUrl($url) {
		$screenCapture = new Capture($url);
		$capture_width = $this->config->get('screenshot.service.capture_width');
		$capture_height = $this->config->get('screenshot.service.capture_height');
		$screenCapture->setWidth($capture_width);
		$screenCapture->setHeight($capture_height);
		$screenCapture->setBackgroundColor('#ffffff');
		$screenCapture->setUserAgentString('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.75 Safari/537.36');
		$screenCapture->setFormat('png');
		$capture_binpath = $this->config->get('screenshot.service.bin_phantomjs');
		if ($capture_binpath) {
			$screenCapture->binPath = $capture_binpath;
		}

		$capture_job_location = $this->config->get('screenshot.service.capture_jobs_location');
		$screenCapture->jobs->setLocation($capture_job_location);
		$capture_output_location = $this->config->get('screenshot.service.capture_output_location');
		$screenCapture->output->setLocation($capture_output_location);
		$file = urlencode($url) . ".png";
		$new = $capture_output_location . "/$file";
		$old = $new . ".old.png";
		$readyCompare = false;
		if (is_file($new) && filesize($new) && copy($new, $old)) {
			$readyCompare = true;
		}
		$screenCapture->save($file);
		$readyCompare && $this->compareCapture($url, $new, $old);
	}

	private function compareCapture($url, $new, $old) {
		if (!file_exists($new) or !file_exists($old)) {
			return;
		}
		$ph = new PerceptualHash($old, new PerceptionHash());
		// Compare with another image, return a Hamming distance
		$distance = $ph->compare($new);
		$this->log->debug("$new vs $old distance = $distance");
		$similarity = $ph->similarity($new);
		if ($similarity < $this->config->get('screenshot.service.capture_similarity')) {
			$data = [
				'beanstalk' => $this->config->get('screenshot.beanstalk'),
				'page_url' => $url,
				'image_path' => $new,
				'images_url' => $this->config->get('screenshot.service.snap_site') . urlencode(basename($new)),
				'time_stamp' => date("Y-m-d H:i:s", time()),
			];
			$this->events->trigger("notification", $data);
		}
		$this->log->debug("$new vs $old similarity = $similarity");
	}
}
