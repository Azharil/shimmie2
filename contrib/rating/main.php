<?php
/**
 * Name: Image Ratings
 * Author: Shish <webmaster@shishnet.org>
 * License: GPLv2
 * Description: Allow users to rate images "safe", "questionable" or "explicit"
 */

class RatingSetEvent extends Event {
	var $image_id, $user, $rating;

	public function RatingSetEvent($image_id, $user, $rating) {
		$this->image_id = $image_id;
		$this->user = $user;
		$this->rating = $rating;
	}
}

class Ratings implements Extension {
	var $theme;

	public function receive_event(Event $event) {
		global $config, $database, $page, $user;
		if(is_null($this->theme)) $this->theme = get_theme_object($this);

		if($event instanceof InitExtEvent) {
			if($config->get_int("ext_ratings2_version") < 2) {
				$this->install();
			}

			$config->set_default_string("ext_rating_anon_privs", 'sq');
			$config->set_default_string("ext_rating_user_privs", 'sq');
		}

		if($event instanceof RatingSetEvent) {
			$this->set_rating($event->image_id, $event->rating);
		}

		if($event instanceof ImageInfoBoxBuildingEvent) {
			if($user->is_admin()) {
				$event->add_part($this->theme->get_rater_html($event->image->id, $event->image->rating), 80);
			}
		}

		if($event instanceof ImageInfoSetEvent) {
			if($user->is_admin()) {
				send_event(new RatingSetEvent($event->image->id, $user, $_POST['rating']));
			}
		}

		if($event instanceof SetupBuildingEvent) {
			$privs = array();
			$privs['Safe Only'] = 's';
			$privs['Safe and Questionable'] = 'sq';
			$privs['All'] = 'sqeu';

			$sb = new SetupBlock("Image Ratings");
			$sb->add_choice_option("ext_rating_anon_privs", $privs, "Anonymous: ");
			$sb->add_choice_option("ext_rating_user_privs", $privs, "<br>Logged in: ");
			$event->panel->add_block($sb);
		}

		if($event instanceof ParseLinkTemplateEvent) {
			$event->replace('$rating', $this->theme->rating_to_name($event->image->rating));
		}

		if($event instanceof SearchTermParseEvent) {
			$matches = array();
			if(is_null($event->term) && $this->no_rating_query($event->context)) {
				if($user->is_anonymous()) {
					$sqes = $config->get_string("ext_rating_anon_privs");
				}
				else {
					$sqes = $config->get_string("ext_rating_user_privs");
				}
				$arr = array();
				for($i=0; $i<strlen($sqes); $i++) {
					$arr[] = "'" . $sqes[$i] . "'";
				}
				$set = join(', ', $arr);
				$event->add_querylet(new Querylet("rating IN ($set)"));
			}
			if(preg_match("/^rating=([sqeu]+)$/", $event->term, $matches)) {
				$sqes = $matches[1];
				$arr = array();
				for($i=0; $i<strlen($sqes); $i++) {
					$arr[] = "'" . $sqes[$i] . "'";
				}
				$set = join(', ', $arr);
				$event->add_querylet(new Querylet("rating IN ($set)"));
			}
		}
	}

	private function no_rating_query($context) {
		foreach($context as $term) {
			if(preg_match("/^rating=([sqeu]+)$/", $term)) {
				return false;
			}
		}
		return true;
	}

	private function install() {
		global $database;
		global $config;

		if($config->get_int("ext_ratings2_version") < 1) {
			$database->Execute("ALTER TABLE images ADD COLUMN rating CHAR(1) NOT NULL DEFAULT 'u'");
			$database->Execute("CREATE INDEX images__rating ON images(rating)");
			$config->set_int("ext_ratings2_version", 3);
		}

		if($config->get_int("ext_ratings2_version") < 2) {
			$database->Execute("CREATE INDEX images__rating ON images(rating)");
			$config->set_int("ext_ratings2_version", 2);
		}

		if($config->get_int("ext_ratings2_version") < 3) {
			$database->Execute("ALTER TABLE images CHANGE rating rating CHAR(1) NOT NULL DEFAULT 'u'");
			$config->set_int("ext_ratings2_version", 3);
		}
	}

	private function set_rating($image_id, $rating) {
		global $database;
		$database->Execute("UPDATE images SET rating=? WHERE id=?", array($rating, $image_id));
	}
}
add_event_listener(new Ratings());
?>
