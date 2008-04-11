<?php
/**
 * Name: IP Ban
 * Author: Shish <webmaster@shishnet.org>
 * License: GPLv2
 * Description: Ban IP addresses
 */

// RemoveIPBanEvent {{{
class RemoveIPBanEvent extends Event {
	var $id;

	public function RemoveIPBanEvent($id) {
		$this->id = $id;
	}
}
// }}}
// AddIPBanEvent {{{
class AddIPBanEvent extends Event { 
	var $ip;
	var $reason;
	var $end;

	public function AddIPBanEvent($ip, $reason, $end) {
		$this->ip = $ip;
		$this->reason = $reason;
		$this->end = $end;
	}
}
// }}}

class IPBan extends Extension {
	var $theme;
// event handler {{{
	public function receive_event($event) {
		if(is_null($this->theme)) $this->theme = get_theme_object("ipban", "IPBanTheme");

		if(is_a($event, 'InitExtEvent')) {
			global $config;
			if($config->get_int("ext_ipban_version") < 5) {
				$this->install();
			}
			
			$this->check_ip_ban();
		}

		if(is_a($event, 'PageRequestEvent') && ($event->page_name == "ip_ban")) {
			global $user;
			if($user->is_admin()) {
				if($event->get_arg(0) == "add") {
					if(isset($_POST['ip']) && isset($_POST['reason']) && isset($_POST['end'])) {
						if(empty($_POST['end'])) $end = null;
						else $end = $_POST['end'];
						send_event(new AddIPBanEvent($_POST['ip'], $_POST['reason'], $end));

						global $page;
						$page->set_mode("redirect");
						$page->set_redirect(make_link("ip_ban/list"));
					}
				}
				else if($event->get_arg(0) == "remove") {
					if(isset($_POST['id'])) {
						send_event(new RemoveIPBanEvent($_POST['id']));

						global $page;
						$page->set_mode("redirect");
						$page->set_redirect(make_link("ip_ban/list"));
					}
				}
				else if($event->get_arg(0) == "list") {
					global $page;
					$this->theme->display_bans($page, $this->get_bans());
				}
			}
		}

		if(is_a($event, 'UserBlockBuildingEvent')) {
			if($event->user->is_admin()) {
				$event->add_link("IP Bans", make_link("ip_ban/list"));
			}
		}

		if(is_a($event, 'AddIPBanEvent')) {
			global $user;
			$this->add_ip_ban($event->ip, $event->reason, $event->end, $user);
		}

		if(is_a($event, 'RemoveIPBanEvent')) {
			$this->remove_ip_ban($event->id);
		}
	}
// }}}
// installer {{{
	protected function install() {
		global $database;
		global $config;
		
		if($config->get_int("ext_ipban_version") < 3) {
			$database->upgrade_schema("ext/ipban/schema.xml");
		}
	}
// }}}
// deal with banned person {{{
	private function check_ip_ban() {
		global $config;
		global $database;

		$remote = $_SERVER['REMOTE_ADDR'];
		$bans = $this->get_active_bans();
		foreach($bans as $row) {
			if(
				(strstr($row['ip'], '/') && ip_in_range($remote, $row['ip'])) ||
				($row['ip'] == $remote)
			) {
				$admin = $database->get_user_by_id($row['banner_id']);
				print "IP <b>{$row['ip']}</b> has been banned by <b>{$admin->name}</b> because of <b>{$row['reason']}</b>";

				$contact_link = $config->get_string("contact_link");
				if(!empty($contact_link)) {
					print "<p><a href='$contact_link'>Contact The Admin</a>";
				}
				exit;
			}
		}
	}
// }}}
// database {{{
	private function get_bans() {
		global $database;
		$bans = $database->get_all("SELECT * FROM bans ORDER BY date");
		if($bans) {return $bans;}
		else {return array();}
	}

	private function get_active_bans() {
		global $database;
		$bans = $database->get_all("SELECT * FROM bans WHERE (date < now()) AND (end > now() OR isnull(end))");
		if($bans) {return $bans;}
		else {return array();}
	}

	private function get_ip_ban($ip) {
		global $database;
		return $database->db->GetRow("SELECT * FROM bans WHERE ip = ? AND date < now() AND (end > now() OR isnull(end))", array($ip));
	}

	private function add_ip_ban($ip, $reason, $end, $user) {
		global $database;
		if(preg_match("/^\d+ (day|week|month)$/i", $end)) {
			$sql = "INSERT INTO bans (ip, reason, date, end, banner_id)
			        VALUES (?, ?, now(), now() + interval $end, ?)";
			$database->Execute($sql, array($ip, $reason, $user->id));
		}
		else {
			$sql = "INSERT INTO bans (ip, reason, date, end, banner_id)
			        VALUES (?, ?, now(), ?, ?)";
			$database->Execute($sql, array($ip, $reason, $end, $user->id));
		}
	}

	private function remove_ip_ban($id) {
		global $database;
		$database->Execute("DELETE FROM bans WHERE id = ?", array($id));
	}
// }}}
}
add_event_listener(new IPBan(), 10);
?>
