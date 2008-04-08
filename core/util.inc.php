<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Input / Output Sanitising                                                 *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function html_escape($input) {
	return htmlentities($input, ENT_QUOTES, "UTF-8");
}

function int_escape($input) {
	return (int)$input;
}

function url_escape($input) {
	$input = str_replace('/', '//', $input);
	$input = rawurlencode($input);
	$input = str_replace('%2F', '/', $input);
	return $input;
}

function sql_escape($input) {
	global $database;
	return $database->db->Quote($input);
}

function parse_shorthand_int($limit) {
	if(is_numeric($limit)) {
		return (int)$limit;
	}

	if(preg_match('/^([\d\.]+)([gmk])?b?$/i', "$limit", $m)) {
		$value = $m[1];
		if (isset($m[2])) {
			switch(strtolower($m[2])) {
				case 'g': $value *= 1024;  # fallthrough
				case 'm': $value *= 1024;  # fallthrough
				case 'k': $value *= 1024; break;
				default: $value = -1;
			}
		}
		return (int)$value;
	} else {
		return -1;
	}
}

function to_shorthand_int($int) {
	if($int >= pow(1024, 3)) {
		return sprintf("%.1fGB", $int / pow(1024, 3));
	}
	else if($int >= pow(1024, 2)) {
		return sprintf("%.1fMB", $int / pow(1024, 2));
	}
	else if($int >= 1024) {
		return sprintf("%.1fKB", $int / 1024);
	}
	else {
		return "$int";
	}
}

function tag_explode($tags) {
	if(is_string($tags)) {
		$tags = explode(' ', $tags);
	}
	else if(is_array($tags)) {
		// do nothing
	}
	else {
		die("tag_explode only takes strings or arrays");
	}

	$tags = array_map("trim", $tags);

	$tag_array = array();
	foreach($tags as $tag) {
		if(is_string($tag) && strlen($tag) > 0) {
			$tag_array[] = $tag;
		}
	}

	if(count($tag_array) == 0) {
		$tag_array = array("tagme");
	}

	return $tag_array;
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* HTML Generation                                                           *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function make_link($page=null, $query=null) {
	global $config;

	if(is_null($page)) $page = $config->get_string('main_page');

	$base = $config->get_string('base_href');

	if(is_null($query)) {
		return "$base/$page";
	}
	else {
		if(strpos($base, "?")) {
			return "$base/$page&$query";
		}
		else {
			return "$base/$page?$query";
		}
	}
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Misc                                                                      *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function get_thumbnail_size($orig_width, $orig_height) {
	global $config;

	if($orig_width == 0) $orig_width = 192;
	if($orig_height == 0) $orig_height = 192;

	$max_width  = $config->get_int('thumb_width');
	$max_height = $config->get_int('thumb_height');

	$xscale = ($max_height / $orig_height);
	$yscale = ($max_width / $orig_width);
	$scale = ($xscale < $yscale) ? $xscale : $yscale;

	if($scale > 1 && $config->get_bool('thumb_upscale')) {
		return array((int)$orig_width, (int)$orig_height);
	}
	else {
		return array((int)($orig_width*$scale), (int)($orig_height*$scale));
	}
}

# $db is the connection object
function _count_execs($db, $sql, $inputarray) {
	global $_execs;
	if(DEBUG) {
		$fp = fopen("sql.log", "a");
		fwrite($fp, preg_replace('/\s+/msi', ' ', $sql)."\n");
		fclose($fp);
	}
	if (!is_array($inputarray)) $_execs++;
	# handle 2-dimensional input arrays
	else if (is_array(reset($inputarray))) $_execs += sizeof($inputarray);
	else $_execs++;
	# in PHP4.4 and PHP5, we need to return a value by reference
	$null = null; return $null;
}

function get_theme_object($file, $class) {
	global $config;
	$theme = $config->get_string("theme", "default");
	if(class_exists("Custom$class")) {
		$class = "Custom$class";
		return new $class();
	}
	else {
		return new $class();
	}
}

function blockcmp($a, $b) {
	if($a->position == $b->position) {
		return 0;
	}
	else {
		return ($a->position > $b->position);
	}
}

function get_memory_limit() {
	global $config;

	// thumbnail generation requires lots of memory
	$default_limit = 8*1024*1024;
	$shimmie_limit = parse_shorthand_int($config->get_int("thumb_mem_limit"));
	if($shimmie_limit < 3*1024*1024) {
		// we aren't going to fit, override
		$shimmie_limit = $default_limit;
	}
	
	ini_set("memory_limit", $shimmie_limit);
	$memory = parse_shorthand_int(ini_get("memory_limit"));

	// changing of memory limit is disabled / failed
	if($memory == -1) {
		$memory = $default_limit; 
	}

	assert($memory > 0);

	return $memory;
}

function get_session_ip() {
	global $config;

    $mask = $config->get_string("session_hash_mask");
    if(!$mask) {
        $config->set_string("session_hash_mask", "255.255.0.0");
        $mask = "255.255.0.0";
    }

    $addr = $_SERVER['REMOTE_ADDR'];
    $addr = inet_ntop(inet_pton($addr) & inet_pton($mask));

    return $addr;
}

/*
 * PHP really, really sucks.
 */
function get_base_href() {
	$possible_vars = array('SCRIPT_NAME', 'PHP_SELF', 'PATH_INFO', 'ORIG_PATH_INFO');
	$ok_var = null;
	foreach($possible_vars as $var) {
		if(substr($_SERVER[$var], -4) == '.php') {
			$ok_var = $_SERVER[$var];
			break;
		}
	}
	assert(!empty($ok_var));
	$dir = dirname($ok_var);
	if($dir == "/" || $dir == "\\") $dir = "";
	return $dir;
}


function move_upload_to_archive($event) {
	$hash = $event->hash;
	$ha = substr($hash, 0, 2);
	if(!copy($event->tmpname, "images/$ha/$hash")) {
		$event->veto("Failed to copy file from uploads ({$event->tmpname}) to archive (images/$ha/$hash)");
		return false;
	}
	return true;
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Debugging functions                                                       *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function get_debug_info() {
	global $config, $_event_count;
	
	if(function_exists('memory_get_usage')) {
		$i_mem = sprintf("%5.2f", ((memory_get_usage()+512)/1024)/1024);
	}
	else {
		$i_mem = "???";
	}
	if(function_exists('getrusage')) {
		$ru = getrusage();
		$i_utime = sprintf("%5.2f", ($ru["ru_utime.tv_sec"]*1e6+$ru["ru_utime.tv_usec"])/1000000);
		$i_stime = sprintf("%5.2f", ($ru["ru_stime.tv_sec"]*1e6+$ru["ru_stime.tv_usec"])/1000000);
	}
	else {
		$i_utime = "???";
		$i_stime = "???";
	}
	$i_files = count(get_included_files());
	global $_execs;
	$debug = "<br>Took $i_utime + $i_stime seconds and {$i_mem}MB of RAM";
	$debug .= "; Used $i_files files and $_execs queries";
	$debug .= "; Sent $_event_count events";

	return $debug;
}

// print_obj ($object, $title, $return)
function print_obj($object,$title="Object Information", $return=false) {
	global $user;
	if(DEBUG && isset($_GET['debug']) && $user->is_admin()) { 
		$pr = print_r($object,true);
		$count = substr_count($pr,"\n")<=25?substr_count($pr,"\n"):25;
		$pr = "<textarea rows='".$count."' cols='80'>$pr</textarea>";
		
		if($return) {
			return $pr;
		} else {
			global $page;
			$page->add_block(new Block($title,$pr,"main",1000));
			return true;
		}
	}
}

// preset tests. 

// Prints the contents of $event->args, even though they are clearly visible in 
// the URL bar. 
function print_url_args() { 
	global $event; 
	print_obj($event->args,"URL Arguments"); 
} 

// Prints all the POST data. 
function print_POST() { 
	print_obj($_POST,"\$_POST"); 
} 

// Prints GET, though this is also visible in the url ( url?var&var&var) 
function print_GET() { 
	print_obj($_GET,"\$_GET"); 
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Things which should be in the core API                                    *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function array_remove($array, $to_remove) {
	$array = array_unique($array);
	$a2 = array();
	foreach($array as $existing) {
		if($existing != $to_remove) {
			$a2[] = $existing;
		}
	}
	return $a2;
}

function array_add($array, $element) { 
	$array[] = $element; 
	$array = array_unique($array); 
	return $array; 
} 

function array_contains($array, $target) {
	foreach($array as $element) {
		if($target == $element) {
			return true;
		}
	}
	return false;
}

// from http://uk.php.net/network
function ip_in_range($IP, $CIDR) {
	list ($net, $mask) = split ("/", $CIDR);

	$ip_net = ip2long ($net);
	$ip_mask = ~((1 << (32 - $mask)) - 1);

	$ip_ip = ip2long ($IP);

	$ip_ip_net = $ip_ip & $ip_mask;

	return ($ip_ip_net == $ip_net);
}

// from a patch by Christian Walde; only intended for use in the
// "extension manager" extension, but it seems to fit better here
function deltree($f) {
	if (is_link($f)) {
		unlink($f);
	}
	else if(is_dir($f)) {
		foreach(glob($f.'/*') as $sf) {
			if (is_dir($sf) && !is_link($sf)) {
				deltree($sf);
			} else {
				unlink($sf);
			}
		}
		rmdir($f);
	}
}

// from a comment on http://uk.php.net/copy
function full_copy($source, $target) {
	if(is_dir($source)) {
		@mkdir($target);

		$d = dir($source);

		while(FALSE !== ($entry = $d->read())) {
			if($entry == '.' || $entry == '..') {
				continue;
			}

			$Entry = $source . '/' . $entry;          
			if(is_dir($Entry)) {
				full_copy($Entry, $target . '/' . $entry);
				continue;
			}
			copy($Entry, $target . '/' . $entry);
		}
		$d->close();
	}
	else {
		copy($source, $target);
	}
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Event API                                                                 *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

$_event_listeners = array();

function add_event_listener($extension, $pos=50) {
	global $_event_listeners;
	while(isset($_event_listeners[$pos])) {
		$pos++;
	}
	$_event_listeners[$pos] = $extension;
}

$_event_count = 0;
function send_event($event) {
	global $_event_listeners, $_event_count;
	$my_event_listeners = $_event_listeners; // http://bugs.php.net/bug.php?id=35106
	ksort($my_event_listeners);
	foreach($my_event_listeners as $listener) {
		$listener->receive_event($event);
		if($event->vetoed) break;
	}
	$_event_count++;
}


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *\
* Request initialisation stuff                                              *
\* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

function _get_query_parts() {
	if(isset($_GET["q"])) {
		$path = $_GET["q"];
	}
	else if(isset($_SERVER["PATH_INFO"])) {
		$path = $_SERVER["PATH_INFO"];
	}
	else {
		$path = "";
	}
	
	while(strlen($path) > 0 && $path[0] == '/') {
		$path = substr($path, 1);
	}

	/*
	 * Split post/list/fate//stay_night/1
	 * into post list fate/stay_night 1
	 */
	/*
	$parts = array();
	$n = 0;
	$lastsplit = 0;
	while($n<=strlen($path)) {
		if(
				$n == strlen($path) ||
				(
					$path[$n] == '/' &&
					($n < strlen($path) && $path[$n+1] != '/')
					&& ($n > 0 && $path[$n-1] != '/')
				)
		) {
			$part = substr($path, $lastsplit, $n-$lastsplit);
			$part = str_replace('//', '/', $part);
			$parts[] = $part;
			$lastsplit = $n+1;
		}
		$n++;
	}
	*/
	$path = str_replace('/', '%%', $path);
	$path = str_replace('%%%%', '/', $path);
	$parts = split('%%', $path);

	return $parts;
}

function _get_page_request($page, $user) {
	global $config;
	$args = _get_query_parts();

	if(count($args) == 0 || strlen($args[0]) == 0) {
		$parts = split('/', $config->get_string('front_page'));
		$page_name = array_shift($parts);
		$args = $parts;
	}
	else if(count($args) == 1) {
		$page_name = $args[0];
		$args = array();
	}
	else {
		$page_name = $args[0];
		$args = array_slice($args, 1);
	}
	
	return new PageRequestEvent($page_name, $args, $page, $user);
}

function _get_user() {
	global $database;
	global $config;
	
	$user = null;
	if(isset($_COOKIE["shm_user"]) && isset($_COOKIE["shm_session"])) {
	    $tmp_user = $database->get_user_session($_COOKIE["shm_user"], $_COOKIE["shm_session"]);
		if(!is_null($tmp_user) && $tmp_user->is_enabled()) {
			$user = $tmp_user;
		}
		
	}
	if(is_null($user)) {
		$user = $database->get_user_by_id($config->get_int("anon_id", 0));
	}
	assert(!is_null($user));
	return $user;
}

?>
