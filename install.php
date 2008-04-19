<?php ob_start(); ?>
<html>
<!--
 - install.php (c) Shish 2007
 -
 - Initialise the database, check that folder
 - permissions are set properly, set an admin
 - account.
 -
 - This file should be independant of the database
 - and other such things that aren't ready yet
-->
	<head>
		<title>Shimmie Installation</title>
		<style>
BODY {background: #EEE;font-family: "Arial", sans-serif;font-size: 14px;}
H1, H3 {border: 1px solid black;background: #DDD;text-align: center;}
H1 {margin-top: 0px;margin-bottom: 0px;padding: 2px;}
H3 {margin-top: 32px;padding: 1px;}
FORM {margin: 0px;}
A {text-decoration: none;}
A:hover {text-decoration: underline;}
#block {width: 512px; margin: auto; margin-top: 64px;}
#iblock {width: 512px; margin: auto; margin-top: 16px;}
TD INPUT {width: 350px;}
		</style>
	</head>
	<body>
<?php if(false) { ?>
		<div id="block">
			<h1>Install Error</h1>
			<p>Shimmie needs to be run via a web server with PHP support -- you
			appear to be either opening the file from your hard disk, or your
			web server is mis-configured.
			<p>If you've installed a web server on your desktop PC, you probably
			want to visit <a href="http://localhost/">the local web server</a>.
			<p>For more help with installation, visit
			<a href="http://trac.shishnet.org/shimmie2/wiki/Guides/Admin/Install">the
			documentation wiki</a>.
		</div>
		<div style="display: none;">
			<PLAINTEXT>
<?php }
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_BAIL, 1);

/*
 * This file lets anyone destroy the database -- disable it
 * as soon as the admin is done installing for the first time
 */
if(is_readable("config.php")) {
	echo "'config.php' exists -- install function is disabled";
	exit;
}
require_once "core/compat.inc.php";
require_once "lib/adodb/adodb.inc.php";
require_once "lib/adodb/adodb-xmlschema03.inc.php";

do_install();

// utilities {{{
function installer_write_file($fname, $data) {
	$fp = fopen($fname, "w");
	if(!$fp) return false;
	
	fwrite($fp, $data);
	fclose($fp);
	
	return true;
}

function check_gd_version() {
	$gdversion = 0;

	if (function_exists('gd_info')){
		$gd_info = gd_info();
		if (substr_count($gd_info['GD Version'], '2.')) {
			$gdversion = 2;
		} else if (substr_count($gd_info['GD Version'], '1.')) {
			$gdversion = 1;
		}
	}

	return $gdversion;
}

function check_im_version() {
	if(!ini_get('safe_mode')) {
		$convert_check = exec("convert");
	}
	return (empty($convert_check) ? 0 : 1);
}
// }}}
function do_install() { // {{{
	if(!isset($_POST['database_dsn'])) {
		begin();
	}
	else {
		install_process();
	}
} // }}}
function begin() { // {{{
	if(check_gd_version() == 0 && check_im_version() == 0) {
		$gd = "<h3>Error</h3>\nPHP's GD extension seems to be missing, ".
		      "and imagemagick's \"convert\" command cannot be found - ".
			  "no thumbnailing engines are available.";
	}
	else {
		$gd = "";
	}

	print <<<EOD
		<div id="iblock">
			<h1>Shimmie Installer</h1>

			$gd

			<h3>Install</h3>
			<form action="install.php" method="POST">
				<center>
					<table>
						<tr><td>Database:</td><td><input type="text" name="database_dsn" size="40"></td></tr>
						<tr><td>Admin Name:</td><td><input type="text" name="admin_name" size="40"></td></tr>
						<tr><td>Admin Pass:</td><td><input type="password" name="admin_pass" size="40"></td></tr>
						<tr><td colspan="2"><center><input type="submit" value="Go!"></center></td></tr>
					</table>
				</center>
			</form>

			<h3>Help</h3>
					
			<p>Databases should be specified like so:
			<br>ie: <code>protocol://username:password@host/database?options</code>
			<br>eg: <code>mysql://shimmie:pw123@localhost/shimmie?persist</code>
			
			<p>For more help with installation, visit
			<a href="http://trac.shishnet.org/shimmie2/wiki/Guides/Admin/Install">the
			documentation wiki</a>.
		</div>
EOD;
} // }}}
function install_process() { // {{{
	if(!isset($_POST['database_dsn']) || !isset($_POST["admin_name"]) || !isset($_POST["admin_pass"])) {
		die("Install is missing some paramaters (database_dsn, admin_name, or admin_pass)");
	}
	else if(strlen($_POST["admin_name"]) < 1 || strlen($_POST["admin_pass"]) < 1) {
		die("Admin name and password must be at least one character each");
	}
	else {
		$database_dsn = $_POST['database_dsn'];
		$admin_name = $_POST["admin_name"];
		$admin_pass = $_POST["admin_pass"];
	}

	set_admin_cookie($admin_name, $admin_pass);
	create_tables($database_dsn);
	insert_defaults($database_dsn, $admin_name, $admin_pass);
	build_dirs();
	write_config($database_dsn);
	
	header("Location: index.php?q=setup");
} // }}}
function set_admin_cookie($admin_name, $admin_pass) { // {{{
	$addr = $_SERVER['REMOTE_ADDR'];
	$addr = inet_ntop(inet_pton($addr) & inet_pton("255.255.0.0"));
	$hash = md5(strtolower($admin_name) . $admin_pass);
	setcookie("shm_user", $admin_name, time()+60*60*24*365);
	setcookie("shm_session", md5($hash.$addr), time()+60*60*24*7, "/");
} // }}}
function create_tables($dsn) { // {{{
	$db = NewADOConnection($dsn);
	if(!$db) {
		die("Couldn't connect to \"$dsn\"");
	}
	else {
		if(substr($dsn, 0, 5) == "mysql") {
			$db->Execute("SET NAMES utf8");
		}
		$schema = new adoSchema($db);
		$sql = $schema->ParseSchema("ext/upgrade/schema.xml");
		$result = $schema->ExecuteSchema();

		if(!$result) {
			die("Error creating tables from XML schema");
		}
	}
	$db->Close();
} // }}}
function insert_defaults($dsn, $admin_name, $admin_pass) { // {{{
	$db = NewADOConnection($dsn);
	if(!$db) {
		die("Couldn't connect to \"$dsn\"");
	}
	else {
		$config_insert = $db->Prepare("INSERT INTO config(name, value) VALUES(?, ?)");
		$user_insert = $db->Prepare("INSERT INTO users(name, pass, joindate, admin) VALUES(?, ?, now(), ?)");
		$admin_pass = md5(strtolower($admin_name).$admin_pass);

		$db->Execute($user_insert, Array('Anonymous', null, 'N'));
		$db->Execute($config_insert, Array('anon_id', $db->Insert_ID()));
		$db->Execute($user_insert, Array($admin_name, $admin_pass, 'Y'));

		if(check_im_version() > 0) {
			$db->Execute($config_insert, Array('thumb_engine', 'convert'));
		}

		$db->Close();
	}
} // }}}
function build_dirs() { // {{{
	if(!file_exists("images")) @mkdir("images"); // *try* and make default dirs. Ignore any errors -- 
	if(!file_exists("thumbs")) @mkdir("thumbs"); // if something is amiss, we'll tell the user later
	if(!file_exists("data")) @mkdir("data");

	if(
			((!file_exists("images") || !file_exists("thumbs") || !file_exists("data")) && !is_writable("./")) ||
			(!is_writable("images") || !is_writable("thumbs") || !is_writable("data"))
	) {
		print "Shimmie needs three folders in it's directory, 'images', 'thumbs', and 'data',
		       and they need to be writable by the PHP user (if you see this error,
			   if probably means the folders are owned by you, and they need to be
			   writable by the web server).
			   
			   <p>Once you have created these folders, hit 'refresh' to continue.";
		exit;
	}
	else {
		assert(file_exists("images") && is_writable("images"));
		assert(file_exists("thumbs") && is_writable("thumbs"));
		assert(file_exists("data") && is_writable("data"));

		if(!file_exists("images/ff")) {
			for($i=0; $i<256; $i++) {
				mkdir(sprintf("images/%02x", $i));
				mkdir(sprintf("thumbs/%02x", $i));
			}
		}
	}
} // }}}
function write_config($dsn) { // {{{
	$file_content = "<?php \$database_dsn='$dsn'; ?>";
	
	if(is_writable("./") && installer_write_file("config.php", $file_content)) {
		assert(file_exists("config.php"));
	}
	else {
		$h_file_content = htmlentities($file_content);
		print <<<EOD
		The web server isn't allowed to write to the config file; please copy
	    the text below, save it as 'config.php', and upload it into the shimmie
	    folder manually. Make sure that when you save it, there is no whitespace
		before the "&lt;?php" or after the "?&gt;"

		<p><textarea cols="80" rows="2">$file_content</textarea>
						
		<p>One done, <a href='index.php?q=setup'>Continue</a>
EOD;
		exit;
	}
} // }}}
?>
	</body>
</html>
