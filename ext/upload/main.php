<?php

class Upload extends Extension {
	var $theme;
// event handling {{{
	public function receive_event($event) {
		if(is_null($this->theme)) $this->theme = get_theme_object("upload", "UploadTheme");

		$is_full = (disk_free_space(realpath("./images/")) < 100*1024*1024);
		
		if(is_a($event, 'InitExtEvent')) {
			global $config;
			$config->set_default_int('upload_count', 3);
			$config->set_default_int('upload_size', '256KB');
			$config->set_default_bool('upload_anon', false);
		}

		if(is_a($event, 'PostListBuildingEvent')) {
			global $user;
			if($this->can_upload($user)) {
				if($is_full) {
					$this->theme->display_full($event->page);
				}
				else {
					$this->theme->display_block($event->page);
				}
			}
		}

		if(is_a($event, 'PageRequestEvent') && ($event->page_name == "upload")) {
			if(count($_FILES) + count($_POST) > 0) {
				$tags = tag_explode($_POST['tags']);
				$source = isset($_POST['source']) ? $_POST['source'] : null;
				global $user;
				if($this->can_upload($user)) {
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
			else if(!empty($_GET['url'])) {
				global $user;
				if($this->can_upload($user)) {
					$url = $_GET['url'];
					$tags = array('tagme');
					if(!empty($_GET['tags']) && $_GET['tags'] != "null") {
						$tags = tag_explode($_GET['tags']);
					}
					$ok = $this->try_transload($url, $tags, $url);
					$this->theme->display_upload_status($event->page, $ok);
				}
				else {
					$this->theme->display_error($event->page, "Upload Denied", "Anonymous posting is disabled");
				}
			}
			else {
				if(!$is_full) {
					$this->theme->display_page($event->page);
				}
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
				"fopen" => "fopen",
				"WGet" => "wget"
			), "<br>Transload: ");
			$event->panel->add_block($sb);
		}

		if(is_a($event, "DataUploadEvent")) {
			global $config;
			if($is_full) {
				$event->veto("Upload failed; disk nearly full");
			}
			if(filesize($event->tmpname) > $config->get_int('upload_size')) {
				$event->veto("File too large (".filesize($event->tmpname)." &gt; ".($config->get_int('upload_size')).")");
			}
		}
	}
// }}}
// do things {{{
	private function can_upload($user) {
		global $config;
		return ($config->get_bool("upload_anon") || !$user->is_anonymous());
	}

	private function try_upload($file, $tags, $source) {
		global $page;
		global $config;
		
		if(empty($source)) $source = null;

		$ok = true;
		
		// blank file boxes cause empty uploads, no need for error message
		if(file_exists($file['tmp_name'])) {
			global $user;
			$pathinfo = pathinfo($file['name']);
			$metadata['filename'] = $pathinfo['basename'];
			$metadata['extension'] = $pathinfo['extension'];
			$metadata['tags'] = $tags;
			$metadata['source'] = $source;
			$event = new DataUploadEvent($user, $file['tmp_name'], $metadata);
			send_event($event);
			if($event->vetoed) {
				$this->theme->display_upload_error($page, "Error with ".html_escape($file['name']),
					$event->veto_reason);
				$ok = false;
			}
		}

		return $ok;
	}

	private function try_transload($url, $tags, $source) {
		global $page;
		global $config;

		$ok = true;

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
		
		if($config->get_string("transload_engine") == "wget") {
			$ua = "Shimmie-".VERSION;
			$s_url = escapeshellarg($url);
			$s_tmp = escapeshellarg($tmp_filename);
			system("wget $s_url --output-document=$s_tmp --user-agent=$ua --referer=$s_url");
		}
		
		if(filesize($tmp_filename) == 0) {
			$this->theme->display_upload_error($page, "Error with ".html_escape($filename),
				"No data found -- perhaps the site has hotlink protection?");
			$ok = false;
		}
		else {
			global $user;
			$pathinfo = pathinfo($url);
			$metadata['filename'] = $pathinfo['basename'];
			$metadata['extension'] = $pathinfo['extension'];
			$metadata['tags'] = $tags;
			$metadata['source'] = $source;
			$event = new DataUploadEvent($user, $tmp_filename, $metadata);
			send_event($event);
			if($event->vetoed) {
				$this->theme->display_upload_error($page, "Error with ".html_escape($url),
					$event->veto_reason);
				$ok = false;
			}
		}

		unlink($tmp_filename);

		return $ok;
	}
// }}}
}
add_event_listener(new Upload(), 40); // early, so it can veto the DataUploadEvent before any data handlers see it
?>
