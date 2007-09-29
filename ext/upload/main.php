<?php

class Upload extends Extension {
	var $theme;
// event handling {{{
	public function receive_event($event) {
		if(is_null($this->theme)) $this->theme = get_theme_object("upload", "UploadTheme");
		
		if(is_a($event, 'InitExtEvent')) {
			global $config;
			$config->set_default_int('upload_count', 3);
			$config->set_default_int('upload_size', '256KB');
			$config->set_default_bool('upload_anon', false);
		}

		if(is_a($event, 'PostListBuildingEvent')) {
			if($this->can_upload()) {
				$this->theme->display_block($event->page);
			}
		}

		if(is_a($event, 'PageRequestEvent') && ($event->page_name == "upload")) {
			if(count($_FILES) + count($_POST) > 0) {
				$tags = tag_explode($_POST['tags']);
				$source = isset($_POST['source']) ? $_POST['source'] : null;
				if($this->can_upload()) {
					$ok = true;
					foreach($_FILES as $file) {
						$ok = $ok & $this->try_upload($file, $tags, $source);
					}
					foreach($_POST as $name => $value) {
						if(substr($name, 0, 3) == "url" && strlen($value) > 0) {
							$ok = $ok & $this->try_transload($value, $tags, $source);
						}
					}

					$this->theme->display_upload_status($event->page, $ok);
				}
				else {
					$this->theme->display_error($event->page, "Upload Denied", "Anonymous posting is disabled");
				}
			}
			else {
				$this->theme->display_page($event->page);
			}
		}

		if(is_a($event, 'SetupBuildingEvent')) {
			$sb = new SetupBlock("Upload");
			$sb->position = 10;
			$sb->add_int_option("upload_count", "Max uploads: ");
			$sb->add_shorthand_int_option("upload_size", "<br>Max size per file: ");
			$sb->add_bool_option("upload_anon", "<br>Allow anonymous uploads: ");
			$sb->add_choice_option("transload_engine", array(
				"Disabled" => "none",
				"cURL" => "curl",
				"fopen" => "fopen"
			), "<br>Transload: ");
			$event->panel->add_block($sb);
		}
	}
// }}}
// do things {{{
	private function can_upload() {
		global $config, $user;
		return $config->get_bool("upload_anon") || !$user->is_anonymous();
	}

	private function try_upload($file, $tags, $source) {
		global $page;
		global $config;
		
		if(empty($source)) $source = null;

		$ok = false;
		
		if(!file_exists($file['tmp_name'])) {
			// this happens normally with blank file boxes
			$ok = true;
		}
		else if(filesize($file['tmp_name']) > $config->get_int('upload_size')) {
			$this->theme->display_upload_error($page, "Error with ".html_escape($file['name']),
				"File too large (".to_shorthand_int(filesize($file['tmp_name']))." &gt; ".
				(to_shorthand_int($config->get_int('upload_size'))).")");
		}
		else if(!($info = getimagesize($file['tmp_name']))) {
			$this->theme->display_upload_error($page, "Error with ".html_escape($file['name']),
				"PHP doesn't recognise this as an image file");
		}
		else {
			$image = new Image($file['tmp_name'], $file['name'], $tags, $source);
		
			if($image->is_ok()) {
				$event = new UploadingImageEvent($image);
				send_event($event);
				$ok = !$event->vetoed;
				if(!$ok) {
					$this->theme->display_upload_error($page, "Error with ".html_escape($file['name']),
						$event->veto_reason);
				}
			}
			else {
				$this->theme->display_upload_error($page, "Error with ".html_escape($file['name']),
					"Something is not right!");
			}
		}

		return $ok;
	}

	private function try_transload($url, $tags, $source) {
		global $page;
		global $config;

		$ok = false;

		if(empty($source)) $source = $url;

		// PHP falls back to system default if /tmp fails, can't we just
		// use the system default to start with? :-/
		$tmp_filename = tempnam("/tmp", "shimmie_transload");
		$filename = basename($url);

		if($config->get_string("transload_engine") == "fopen") {
			$fp = @fopen($url, "r");
			if(!$fp) {
				$this->theme->display_upload_error($page, "Error with ".html_escape($filename),
					"Error reading from ".html_escape($url));
				return false;
			}
			$data = "";
			$length = 0;
			while(!feof($fp) && $length <= $config->get_int('upload_size')) {
				$data .= fread($fp, 8192);
				$length = strlen($data);
			}
			fclose($fp);

			$fp = fopen($tmp_filename, "w");
			fwrite($fp, $data);
			fclose($fp);
		}

		if($config->get_string("transload_engine") == "curl") {
			$ch = curl_init($url);
			$fp = fopen($tmp_filename, "w");

			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_REFERER, $url); 
			curl_setopt($ch, CURLOPT_USERAGENT, "Shimmie-".VERSION); 

			curl_exec($ch);
			curl_close($ch);
			fclose($fp);
		}
		
		if(filesize($tmp_filename) == 0) {
			$this->theme->display_upload_error($page, "Error with ".html_escape($filename),
				"No data found -- perhaps the site has hotlink protection?");
		}
		else if(filesize($tmp_filename) > $config->get_int('upload_size')) {
			$this->theme->display_upload_error($page, "Error with ".html_escape($filename),
				"File too large (".filesize($tmp_filename)." &gt; ".
				($config->get_int('upload_size')).")");
		}
		else if(!($info = @getimagesize($tmp_filename))) {
			$this->theme->display_upload_error($page, "Error with ".html_escape($filename),
				"PHP doesn't recognise this as an image file -- perhaps the site has hotlink protection?");
		}
		else {
			$image = new Image($tmp_filename, basename($url), $tags, $source);
		
			if($image->is_ok()) {
				$event = new UploadingImageEvent($image);
				send_event($event);
				$ok = !$event->vetoed;
				if(!$ok) {
					$this->theme->display_upload_error($page, "Error with ".html_escape($filename),
						$event->veto_reason);
				}
			}
			else {
				$this->theme->display_upload_error($page, "Error with ".html_escape($filename),
					"Something is not right!");
			}
		}

		unlink($tmp_filename);

		return $ok;
	}
// }}}
}
add_event_listener(new Upload());
?>
