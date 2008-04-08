<?php

class IcoFileHandlerTheme extends Themelet {
	public function display_image($page, $image) {
		$ilink = $image->get_image_link();
		$html = "<img id='main_image' src='$ilink'>";
		$page->add_block(new Block("Image", $html, "main", 0));
	}
}
?>
