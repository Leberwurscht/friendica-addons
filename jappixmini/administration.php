<?php
//
// Copyright 2012 "Leberwurscht" <leberwurscht@hoegners.de>
//
// This file is dual-licensed under the MIT license (see MIT.txt) and the AGPL license (see jappix/COPYING).
//

function jappixmini_plugin_admin(&$a, &$o) {
	// display instructions and warnings on addon settings page for admin

	if (!file_exists("addon/jappixmini.tgz")) {
		$o .= '<p><strong style="color:#fff;background-color:#f00">The source archive jappixmini.tgz does not exist. This is probably a violation of the Jappix License (AGPL).</strong></p>';
	}

	// warn if cron job has not yet been executed
	$cron_run = get_config("jappixmini", "last_cron_execution");
	if (!$cron_run) $o .= "<p><strong>Warning: The cron job has not yet been executed. If this message is still there after some time (usually 10 minutes), this means that autosubscribe and autoaccept will not work.</strong></p>";

	// bosh proxy
	$bosh_proxy = intval(get_config("jappixmini", "bosh_proxy"));
	$bosh_proxy = $bosh_proxy ? ' checked="checked"' : '';
	$o .= '<label for="jappixmini-proxy">Activate BOSH proxy</label>';
	$o .= ' <input id="jappixmini-proxy" type="checkbox" name="jappixmini-proxy" value="1"'.$bosh_proxy.' /><br />';
	$o .= '<p style="margin-left:2em;">This is needed for older Browsers which do not support CORS.</p>';

	// ejabberd extauth target
	$extauth = intval(get_config("jappixmini", "extauth"));
	$extauth = $extauth ? ' checked="checked"' : '';
	$provided_server = htmlentities(get_config("jappixmini", "provided_server"));
	$provided_bosh = htmlentities(get_config("jappixmini", "provided_bosh"));
	$o .= '<label for="jappixmini-extauth">Activate target for eJabberd <a href="'.$a->get_baseurl().'/jappixmini/extauth_script">extauth script</a></label>';
	$o .= ' <input id="jappixmini-extauth" type="checkbox" name="jappixmini-extauth" value="1"'.$extauth.' /><br />';
	$o .= '<p style="margin-left:2em;"><label for="jappixmini-provided-server">Provided server</label> <input type="text" name="jappixmini-provided-server" value="'.$provided_server.'"><br /><label for="jappixmini-provided-bosh">Provided BOSH host</label> <input type="text" name="jappixmini-provided-bosh" value="'.$provided_bosh.'"></p>';
// Jabber server / BOSH proxy / enable automatically</p>';

	// info text field
	$info_text = get_config("jappixmini", "infotext");
	$o .= '<p><label for="jappixmini-infotext">Info text to help users with configuration (important if you want to provide your own BOSH host!):</label><br />';
	$o .= '<textarea id="jappixmini-infotext" name="jappixmini-infotext" rows="5" cols="50">'.htmlentities($info_text).'</textarea></p>';

	// submit button
	$o .= '<input type="submit" name="jappixmini-admin-settings" value="OK" />';
}

function jappixmini_plugin_admin_post(&$a) {
	// set info text
	$submit = $_REQUEST['jappixmini-admin-settings'];
	if ($submit) {
		$info_text = $_REQUEST['jappixmini-infotext'];
		$bosh_proxy = intval($_REQUEST['jappixmini-proxy']);
		$extauth = intval($_REQUEST['jappixmini-extauth']);
		$provided_server = $_REQUEST['jappixmini-provided-server'];
		$provided_bosh = $_REQUEST['jappixmini-provided-bosh'];
		set_config("jappixmini", "infotext", $info_text);
		set_config("jappixmini", "bosh_proxy", $bosh_proxy);
		set_config("jappixmini", "extauth", $extauth);

		// delete address cache so that new addresses are pushed to contacts
		if ($provided_server != get_config("jappixmini","provided_server")) {
			q("DELETE FROM `pconfig` WHERE `cat`='jappixmini' AND `k` LIKE 'id:%%'");
			set_config("jappixmini", "provided_server", $provided_server);
		}

		set_config("jappixmini", "provided_bosh", $provided_bosh);
	}
}

?>
