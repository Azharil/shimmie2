<?php
/**
 * Name: Link to Image
 * Author: Artanis <artanis.00@gmail.com>
 * Description: Show various forms of link to each image, for copy & paste
 */
class LinkImage implements Extension {
	var $theme;

	public function receive_event(Event $event) {
		global $config, $database, $page, $user;
		if(is_null($this->theme)) $this->theme = get_theme_object($this);

			if(($event instanceof DisplayingImageEvent)) {
				$data_href = get_base_href();
				$page->add_header("<link rel='stylesheet' href='$data_href/ext/link_image/_style.css' type='text/css'>",0);

				$this->theme->links_block($page, $this->data($event->image));
			}
			if($event instanceof SetupBuildingEvent) {
				$sb = new SetupBlock("Link to Image");
				$sb->add_text_option("ext_link-img_text-link_format", "Text Link Format: ");
				$event->panel->add_block($sb);
			}
			if($event instanceof InitExtEvent) {
				//just set default if empty.
				$config->set_default_string("ext_link-img_text-link_format",
										'$title - $id ($ext $size $filesize)');
			}
		}
	private function data($image) {
		global $config;

		$text_link = $image->parse_link_template($config->get_string("ext_link-img_text-link_format"));
		$text_link = $text_link==" "? null : $text_link; // null blank setting so the url gets filled in on the text links.

		return array(
			'thumb_src'	=>	$image->get_thumb_link(),
			'image_src'	=>	$image->get_image_link(),
			'post_link'	=>	$image->get_short_link(),
			'text_link'		=>	$text_link);
	}
}
add_event_listener(new LinkImage());
?>
