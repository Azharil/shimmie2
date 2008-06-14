<?php
/**
 * Name: Regen Thumb
 * Author: Shish <webmaster@shishnet.org>
 * License: GPLv2
 * Description: Regenerate a thumbnail image
 */

class RegenThumb extends Extension {
	var $theme;

	public function receive_event($event) {
		if(is_null($this->theme)) $this->theme = get_theme_object("regen_thumb", "RegenThumbTheme");

		if(is_a($event, 'PageRequestEvent') && ($event->page_name == "regen_thumb")) {
			global $user;
			if($user->is_admin() && isset($_POST['image_id'])) {
				global $database;
				$image = $database->get_image(int_escape($_POST['image_id']));
				send_event(new ThumbnailGenerationEvent($image->hash, $image->ext));
				$this->theme->display_results($event->page, $image);
			}
		}

		if(is_a($event, 'ImageAdminBlockBuildingEvent')) {
			if($event->user->is_admin()) {
				$event->add_part($this->theme->get_buttons_html($event->image->id));
			}
		}
	}
}
add_event_listener(new RegenThumb());
?>
