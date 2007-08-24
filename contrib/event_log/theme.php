<?php

class EventLogTheme extends Themelet {
	public function display_page($page, $events) {
		$page->set_title("Event Log");
		$page->set_heading("Event Log");
		$page->add_block(new NavBlock());
		
		$this->display_table($page, $events);
		$this->display_controls($page);
	}

	protected function display_table($page, $events) {
		$table = "
			<style>
			.event_log_table TD {
				font-size: 0.75em;
			}
			.event_log_table TD.entry {
				text-align: left;
				vertical-align: middle;
			}
			</style>
			<table border='1' class='event_log_table'>
				<tr>
					<th>User
						<a href='".make_link("event_log", "sort=name&order=ASC")."'>+</a>
						<a href='".make_link("event_log", "sort=name&order=DESC")."'>-</a>
					</th>
					<th style='width: 10em;'>IP
						<a href='".make_link("event_log", "sort=owner_ip&order=ASC")."'>+</a>
						<a href='".make_link("event_log", "sort=owner_ip&order=DESC")."'>-</a>
					</th>
					<th rowspan='2' class='entry'>Entry</th>
				</tr>
				<tr>
					<th style='width: 10em;'>Date
						<a href='".make_link("event_log", "sort=date&order=ASC")."'>+</a>
						<a href='".make_link("event_log", "sort=date&order=DESC")."'>-</a>
					</th>
					<th>Event
						<a href='".make_link("event_log", "sort=event&order=ASC")."'>+</a>
						<a href='".make_link("event_log", "sort=event&order=DESC")."'>-</a>
					</th>
				</tr>
		";
		foreach($events as $event) {
			$table .= "
				<tr>
					<td>
						<a href='".make_link("event_log", "filter=name&where={$event['name']}")."'>{$event['name']}</a>
					</td>
					<td>
						<a href='".make_link("event_log", "filter=owner_ip&where={$event['owner_ip']}")."'>{$event['owner_ip']}</a>
					</td>
					<td rowspan='2' class='entry'>{$event['entry']}</td>
				</tr>
				<tr>
					<td>
						{$event['date']}
					</td>
					<td>
						<a href='".make_link("event_log", "filter=event&where={$event['event']}")."'>{$event['event']}</a>
					</td>
				</tr>
			";
		}
		$table .= "</table>";
		$page->add_block(new Block("Log Contents", $table));
	}

	protected function display_controls($page) {
		$html = "
		<form action='".make_link("event_log")."' method='POST'>
			<input type='hidden' name='action' value='clear'>
			<input type='submit' value='Clear Log'>
		</form>
		";
		$page->add_block(new Block(null, $html, "main", 60));
	}
}
?>
