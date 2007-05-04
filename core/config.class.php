<?php
class Config {
	var $values = array(
			'image_tlink' => '/thumbs/$id.jpg',
			'image_ilink' => '/images/$id.$ext',
	);
	var $defaults = array(
			'title' => 'Shimmie', # setup
			'version' => 'Shimmie2-0.0.9', // internal
			'db_version' => '2.0.0.9', // this should be managed by upgrade.php
			'base_href' => './index.php?q=', # setup
			'data_href' => './', # setup
			'theme' => 'default', # setup
			'debug_enabled' => true, # hidden
			'anon_id' => 0, # general
			'dir_images' => 'images', # general
			'dir_thumbs' => 'thumbs', # general
			'index_width' => 3, # index
			'index_height' => 4, # index
			'index_tips' => true,
			'thumb_width' => 192, # index
			'thumb_height' => 192, # index
			'thumb_quality' => 75,  # index
			'thumb_gd_mem_limit' => '8MB', # upload
			'view_scale' => false, # view
			'tags_default' => 'map', # (ignored)
			'tags_min' => '2', # tags
			'tag_edit_anon' => true, # tags
			'upload_count' => 3, # upload
			'upload_size' => '256KB', # upload
			'upload_anon' => true, # upload
			'comment_anon' => true, # comment
			'comment_window' => 5, # comment
			'comment_limit' => 3, # comment
			'comment_count' => 5, # comment
			'popular_count' => 15, # popular
			'info_link' => 'http://tags.shishnet.org/wiki/$tag', # popular
			'login_signup_enabled' => true, # user
			'login_memory' => 7, # user
			'image_ilink' => '$base/image/$id.$ext', # view
			'image_slink' => '', # view
			'image_tlink' => '$base/thumb/$id.jpg', # view
			'image_tip' => '$tags // $size // $filesize' # view
	);

	public function Config() {
		global $database;
		$this->values = $database->db->GetAssoc("SELECT name, value FROM config");
	}
	public function save($name=null) {
		global $database;

		if(is_null($name)) {
			foreach($this->values as $name => $value) {
				// does "or update" work with sqlite / postgres?
				$database->db->StartTrans();
				$database->db->Execute("DELETE FROM config WHERE name = ?", array($name));
				$database->db->Execute("INSERT INTO config VALUES (?, ?)", array($name, $value));
				$database->db->CommitTrans();
			}
		}
		else {
			$database->db->StartTrans();
			$database->db->Execute("DELETE FROM config WHERE name = ?", array($name));
			$database->db->Execute("INSERT INTO config VALUES (?, ?)", array($name, $this->values[$name]));
			$database->db->CommitTrans();
		}
	}

	public function set_int($name, $value) {
		$this->values[$name] = parse_shorthand_int($value);
		$this->save($name);
	}
	public function set_string($name, $value) {
		$this->values[$name] = $value;
		$this->save($name);
	}
	public function set_bool($name, $value) {
		$this->values[$name] = (($value == 'on' || $value === true) ? 'Y' : 'N');
		$this->save($name);
	}

	public function set_int_from_post($name) {
		if(isset($_POST[$name])) {
			$this->values[$name] = $_POST[$name];
			$this->save($name);
		}
	}
	public function set_string_from_post($name) {
		if(isset($_POST[$name])) {
			$this->values[$name] = $_POST[$name];
			$this->save($name);
		}
	}
	public function set_bool_from_post($name) {
		if(isset($_POST[$name]) && ($_POST[$name] == 'on')) {
			$this->values[$name] = 'Y';
		}
		else {
			$this->values[$name] = 'N';
		}
		$this->save($name);
	}

	public function get_int($name) {
		// deprecated -- ints should be stored as ints now
		return parse_shorthand_int($this->get($name));
	}
	public function get_string($name) {
		return $this->get($name);
	}
	public function get_bool($name) {
		// deprecated -- bools should be stored as Y/N now
		return ($this->get($name) == 'Y' || $this->get($name) == '1');
	}

	public function get($name) {
		if(isset($this->values[$name])) {
			return $this->values[$name];
		}
		else if(isset($this->defaults[$name])) {
			return $this->defaults[$name];
		}
		else {
			return null;
		}
	}
}
?>
