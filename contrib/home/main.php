<?php
/**
* Name: Home Page
* Author: Bzchan <bzchan@animemahou.com>
* License: GPLv2
* Description: Displays a front page with logo, search box and image count
* Documentation:
*  Once enabled, the page will show up at the URL "home", so if you want
*  this to be the front page of your site, you should go to "Board Config"
*  and set "Front Page" to "home".
*  <p>The images used for the numbers can be changed from the board config
*  page. If you want to use your own numbers, upload them into a new folder
*  in <code>/ext/home/counters</code>, and they'll become available
*  alongside the default choices.
*/

class Home implements Extension {
	var $theme;

	public function receive_event(Event $event) {
		global $config, $database, $page, $user;
		if(is_null($this->theme)) $this->theme = get_theme_object($this);

		if(($event instanceof PageRequestEvent) && $event->page_matches("home"))
		{
			// this is a request to display this page so output the page.
		  	$this->output_pages($page);
		}
		if($event instanceof SetupBuildingEvent)
		{
			$counters = array();
			foreach(glob("ext/home/counters/*") as $counter_dirname) {
				$name = str_replace("ext/home/counters/", "", $counter_dirname);
				$counters[ucfirst($name)] = $name;
			}

			$sb = new SetupBlock("Home Page");
			$sb->add_label("Page Links - Example: [$"."base/index|Posts]");
			$sb->add_longtext_option("home_links", "<br>");
			$sb->add_choice_option("home_counter", $counters, "<br>Counter: ");
			$sb->add_label("<br>Note: page accessed via /home");
			$event->panel->add_block($sb);
		}
	}

	private function get_body()
	{
		// returns just the contents of the body
		global $database;
		global $config;
		$base_href = $config->get_string('base_href');
		$data_href = get_base_href();
		$sitename = $config->get_string('title');
	    $contact_link = $config->get_string('contact_link');
		$counter_dir = $config->get_string('home_counter', 'default');

		$total = ceil($database->db->GetOne("SELECT COUNT(*) FROM images"));
		$strtotal = "$total";

		$num_comma = number_format($total);

		$counter_text = "";
		for($n=0; $n<strlen($strtotal); $n++)
		{
			$cur = $strtotal[$n];
			$counter_text .= " <img alt='$cur' src='$data_href/ext/home/counters/$counter_dir/$cur.gif' />  ";
		}

		// get the homelinks and process them
		$main_links = $config->get_string('home_links');
		$main_links = str_replace('$base',	$base_href, 	$main_links);
		$main_links = str_replace('[', 		"<a href='", 	$main_links);
		$main_links = str_replace('|', 		"'>", 			$main_links);
		$main_links = str_replace(']', 		"</a>", 		$main_links);

		return $this->theme->build_body($sitename, $main_links, $contact_link, $num_comma, $counter_text);
	}

    private function output_pages($page)
	{
		// output a sectionalised list of all the main pages on the site.
		global $config;
		$base_href = $config->get_string('base_href');
		$data_href = get_base_href();
		$sitename = $config->get_string('title');
		$theme_name = $config->get_string('theme');

		$body = $this->get_body();

		$this->theme->display_page($page, $sitename, $data_href, $theme_name, $body);
	}

}
add_event_listener(new Home());
?>
