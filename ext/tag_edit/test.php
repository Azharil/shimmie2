<?php
class TagEditTest extends ShimmieWebTestCase {
	function testTagEdit() {
		$this->log_in_as_user();
		$image_id = $this->post_image("ext/simpletest/data/pbx_screenshot.jpg", "pbx");
		$this->get_page("post/view/$image_id");
		$this->assert_title("Image $image_id: pbx");
		$this->set_field("tag_edit__tags", "new");
		$this->click("Set");
		$this->assert_title("Image $image_id: new");
		$this->set_field("tag_edit__tags", "");
		$this->click("Set");
		$this->assert_title("Image $image_id: tagme");
		$this->log_out();

		$this->log_in_as_admin();
		$this->get_page("admin");
		$this->assert_text("Mass Tag Edit"); // just test it exists
		$this->delete_image($image_id);
		$this->log_out();

		# FIXME: test mass tag editor
	}

	function testSourceEdit() {
		$this->log_in_as_user();
		$image_id = $this->post_image("ext/simpletest/data/pbx_screenshot.jpg", "pbx");
		$this->get_page("post/view/$image_id");
		$this->assert_title("Image $image_id: pbx");

		$this->set_field("tag_edit__source", "example.com");
		$this->click("Set");
		$this->click("source");
		$this->assert_title("Example Web Page");
		$this->back();

		$this->set_field("tag_edit__source", "http://example.com");
		$this->click("Set");
		$this->click("source");
		$this->assert_title("Example Web Page");
		$this->back();

		$this->log_out();

		$this->log_in_as_admin();
		$this->delete_image($image_id);
		$this->log_out();
	}
}
?>
