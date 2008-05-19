<?php

class PostListBuildingEvent extends Event {
	var $page = null;
	var $search_terms = null;

	public function PostListBuildingEvent($page, $search) {
		$this->page = $page;
		$this->search_terms = $search;
	}
}

class Index extends Extension {
	var $theme;

	public function receive_event($event) {
		if(is_null($this->theme)) $this->theme = get_theme_object("index", "IndexTheme");
		
		if(is_a($event, 'InitExtEvent')) {
			global $config;
			$config->set_default_int("index_width", 3);
			$config->set_default_int("index_height", 4);
			$config->set_default_bool("index_tips", true);
		}

		if(is_a($event, 'PageRequestEvent') && (($event->page_name == "index") ||
					($event->page_name == "post" && $event->get_arg(0) == "list"))) {
			if($event->page_name == "post") array_shift($event->args);

			if(isset($_GET['search'])) {
				$search = url_escape(trim($_GET['search']));
				if(empty($search)) {
					$event->page->set_mode("redirect");
					$event->page->set_redirect(make_link("post/list/1"));
				}
				else {
					$event->page->set_mode("redirect");
					$event->page->set_redirect(make_link("post/list/$search/1"));
					$event->veto();
				}
				return;
			}

			$search_terms = array();
			$page_number = 1;

			if($event->count_args() == 1) {
				$page_number = int_escape($event->get_arg(0));
			}
			else if($event->count_args() == 2) {
				$search_terms = explode(' ', $event->get_arg(0));
				$page_number = int_escape($event->get_arg(1));
			}
			
			if($page_number == 0) $page_number = 1; // invalid -> 0

			global $config;
			global $database;

			$total_pages = $database->count_pages($search_terms);
			$count = $config->get_int('index_width') * $config->get_int('index_height');
			$images = $database->get_images(($page_number-1)*$count, $count, $search_terms);

			send_event(new PostListBuildingEvent($event->page, $search_terms));
			
			$this->theme->set_page($page_number, $total_pages, $search_terms);
			$this->theme->display_page($event->page, $images);
		}

		if(is_a($event, 'SetupBuildingEvent')) {
			$sb = new SetupBlock("Index Options");
			$sb->position = 20;
			
			$sb->add_label("Index table size ");
			$sb->add_int_option("index_width");
			$sb->add_label(" x ");
			$sb->add_int_option("index_height");
			$sb->add_label(" images");

			$event->panel->add_block($sb);
		}

		if(is_a($event, 'SearchTermParseEvent')) {
			$matches = array();
			if(preg_match("/size(<|>|<=|>=|=)(\d+)x(\d+)/", $event->term, $matches)) {
				$cmp = $matches[1];
				$args = array(int_escape($matches[2]), int_escape($matches[3]));
				$event->set_querylet(new Querylet("width $cmp ? AND height $cmp ?", $args));
			}
			else if(preg_match("/ratio(<|>|<=|>=|=)(\d+):(\d+)/", $event->term, $matches)) {
				$cmp = $matches[1];
				$args = array(int_escape($matches[2]), int_escape($matches[3]));
				$event->set_querylet(new Querylet("width / height $cmp ? / ?", $args));
			}
			else if(preg_match("/(filesize|id)(<|>|<=|>=|=)(\d+[kmg]?b?)/i", $event->term, $matches)) {
				$col = $matches[1];
				$cmp = $matches[2];
				$val = parse_shorthand_int($matches[3]);
				$event->set_querylet(new Querylet("images.$col $cmp ?", array($val)));
			}
			else if(preg_match("/hash=([0-9a-fA-F]*)/i", $event->term, $matches)) {
				$hash = strtolower($matches[2]);
				$event->set_querylet(new Querylet("images.hash = '$hash'"));
			}
			else if(preg_match("/(filetype|ext)=([a-zA-Z0-9]*)/i", $event->term, $matches)) {
				$ext = strtolower($matches[2]);
				$event->set_querylet(new Querylet("images.ext = '$ext'"));
			}
			else if(preg_match("/(filename|name)=([a-zA-Z0-9]*)/i", $event->term, $matches)) {
				$filename = strtolower($matches[2]);
				$event->set_querylet(new Querylet("images.filename LIKE '%$filename%'"));
			}
		}
	}
}
add_event_listener(new Index());
?>
