<?php

class ViewTheme extends Themelet {
	public function display_image_not_found($page, $image_id) {
		$page->set_title("Image not found");
		$page->set_heading("Image not found");
		$page->add_block(new NavBlock());
		$page->add_block(new Block("Image not found",
			"No image in the database has the ID #$image_id"));
	}
	
	public function display_page($page, $image) {
		$page->set_title("Image {$image->id}: ".html_escape($image->get_tag_list()));
		$page->set_heading(html_escape($image->get_tag_list()));
		$page->add_block(new Block("Navigation", $this->build_navigation($image->id), "left", 0));
		$page->add_block(new Block("Image", $this->build_image_view($image), "main", 0));
		$page->add_block(new Block(null, $this->build_info($image), "main", 10));
	}



	var $pin = null;

	private function build_pin($image_id) {
		if(!is_null($this->pin)) {
			return $this->pin;
		}

		global $database;

		if(isset($_GET['search'])) {
			$search_terms = explode(' ', $_GET['search']);
			$query = "search=".url_escape($_GET['search']);
		}
		else {
			$search_terms = array();
			$query = null;
		}
		
		$next = $database->get_next_image($image_id, $search_terms);
		$prev = $database->get_prev_image($image_id, $search_terms);

		$h_prev = (!is_null($prev) ? "<a href='".make_link("post/view/{$prev->id}", $query)."'>Prev</a>" : "Prev");
		$h_index = "<a href='".make_link()."'>Index</a>";
		$h_next = (!is_null($next) ? "<a href='".make_link("post/view/{$next->id}", $query)."'>Next</a>" : "Next");

		$this->pin = "$h_prev | $h_index | $h_next";
		return $this->pin;
	}

	private function build_navigation($image_id) {
		$h_pin = $this->build_pin($image_id);
		$h_search = "
			<p><form action='".make_link()."' method='GET'>
				<input id='search_input' name='search' type='text'
						value='Search' autocomplete='off'>
				<input type='submit' value='Find' style='display: none;'>
			</form>
			<div id='search_completions'></div>";

		return "$h_pin<br>$h_search";
	}

	private function build_image_view($image) {
		$ilink = $image->get_image_link();
		return "<img id='main_image' src='$ilink'>";
	}

	private function build_info($image) {
		global $user;
		$owner = $image->get_owner();
		$h_owner = html_escape($owner->name);
		$h_ip = html_escape($image->owner_ip);
		$h_source = html_escape($image->source);
		$i_owner_id = int_escape($owner->id);

		$html = "";
		$html .= "<p>Posted on {$image->posted} by <a href='".make_link("user/$h_owner")."'>$h_owner</a>";
		if($user->is_admin()) {
			$html .= " ($h_ip)";
		}
		if(!is_null($image->source)) {
			if(substr($image->source, 0, 7) == "http://") {
				$html .= " (<a href='$h_source'>source</a>)";
			}
			else {
				$html .= " (<a href='http://$h_source'>source</a>)";
			}
		}

		global $config;
		global $user;
		if($config->get_bool("tag_edit_anon") || ($user->id != $config->get_int("anon_id"))) {
			$html .= " (<a href=\"javascript: toggle('imgdata')\">edit</a>)";

			if(isset($_GET['search'])) {$h_query = "search=".url_escape($_GET['search']);}
			else {$h_query = "";}

			$h_tags = html_escape($image->get_tag_list());
			$i_image_id = int_escape($image->id);

			$html .= "
			<div id='imgdata'><form action='".make_link("tag_edit/set")."' method='POST'>
				<input type='hidden' name='image_id' value='$i_image_id'>
				<input type='hidden' name='query' value='$h_query'>
				<table style='width: 500px; margin: 0px;'>
				<tr><td width='50px'>Tags</td><td width='300px'><input type='text' name='tags' value='$h_tags'></td></tr>
				<tr><td>Source</td><td><input type='text' name='source' value='$h_source'></td></tr>
				<tr><td>&nbsp;</td><td><input type='submit' value='Set'></td></tr>
				</table>
			</form>
			</div>
			";
		}
		
		return $html;
	}
}
?>
