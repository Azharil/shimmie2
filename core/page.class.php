<?php
class GenericPage {
	var $mode = "page";
	var $type = "text/html";

	public function set_mode($mode) {
		$this->mode = $mode;
	}

	public function set_type($type) {
		$this->type = $type;
	}


	// ==============================================

	// data
	var $data = "";
	var $filename = null;

	public function set_data($data) {
		$this->data = $data;
	}

	public function set_filename($filename) {
		$this->filename = $filename;
	}


	// ==============================================

	// redirect
	var $redirect = "";

	public function set_redirect($redirect) {
		$this->redirect = $redirect;
	}


	// ==============================================

	// page
	var $title = "";
	var $heading = "";
	var $subheading = "";
	var $quicknav = "";
	var $headers = array();
	var $blocks = array();

	public function set_title($title) {
		$this->title = $title;
	}

	public function set_heading($heading) {
		$this->heading = $heading;
	}

	public function set_subheading($subheading) {
		$this->subheading = $subheading;
	}

	public function add_header($line, $position=50) {
		while(isset($this->headers[$position])) $position++;
		$this->headers[$position] = $line;
	}

	public function add_block($block) {
		$this->blocks[] = $block;
	}

	// ==============================================

	public function display() {
		global $page;

		header("Content-type: {$this->type}");
		header("X-Powered-By: SCore-".SCORE_VERSION);

		switch($this->mode) {
			case "page":
				header("Cache-control: no-cache");
				usort($this->blocks, "blockcmp");
				$data_href = get_base_href();
				foreach(glob("lib/*.js") as $js) {
					$this->add_header("<script src='$data_href/$js' type='text/javascript'></script>");
				}
				$layout = new Layout();
				$layout->display_page($page);
				break;
			case "data":
				if(!is_null($this->filename)) {
					header('Content-Disposition: attachment; filename='.$this->filename);
				}
				print $this->data;
				break;
			case "redirect":
				header("Location: {$this->redirect}");
				print "You should be redirected to <a href='{$this->redirect}'>{$this->redirect}</a>";
				break;
			default:
				print "Invalid page mode";
				break;
		}
	}
}
?>
