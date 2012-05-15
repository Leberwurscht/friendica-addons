<?php

/**
* Name: jappixmini
* Description: Provides a Facebook-like chat using Jappix Mini
* Version: 1.0
* Author: leberwurscht <leberwurscht@hoegners.de>
*
*/

//
// Copyright 2012 "Leberwurscht" <leberwurscht@hoegners.de>
//
// This file is dual-licensed under the MIT license (see MIT.txt) and the AGPL license (see jappix/COPYING).
//

require_once("address_discovery.php");
require_once("inject_js.php");
require_once("user_settings.php");
require_once("administration.php");
require_once("legal.php");

require_once("extauth.php");

function jappixmini_install() {
	register_hook('plugin_settings', 'addon/jappixmini/jappixmini.php', 'jappixmini_settings');
	register_hook('plugin_settings_post', 'addon/jappixmini/jappixmini.php', 'jappixmini_settings_post');

	register_hook('page_end', 'addon/jappixmini/jappixmini.php', 'jappixmini_script');
	register_hook('login_hook', 'addon/jappixmini/jappixmini.php', 'jappixmini_login');

	register_hook('cron', 'addon/jappixmini/jappixmini.php', 'jappixmini_cron');

	// Jappix source download as required by AGPL
	register_hook('about_hook', 'addon/jappixmini/jappixmini.php', 'jappixmini_download_source');

	// set standard configuration
	$info_text = get_config("jappixmini", "infotext");
	if (!$info_text) set_config("jappixmini", "infotext",
		"To get the chat working, you need to know a BOSH host which works with your Jabber account. ".
		"An example of a BOSH server that works for all accounts is https://bind.jappix.com/, but keep ".
		"in mind that the BOSH server can read along all chat messages. If you know that your Jabber ".
		"server also provides an own BOSH server, it is much better to use this one!"
	);

	$bosh_proxy = get_config("jappixmini", "bosh_proxy");
	if ($bosh_proxy==="") set_config("jappixmini", "bosh_proxy", "1");

	// set addon version so that safe updates are possible later
	$addon_version = get_config("jappixmini", "version");
	if ($addon_version==="") set_config("jappixmini", "version", "1");
}


function jappixmini_uninstall() {
	unregister_hook('plugin_settings', 'addon/jappixmini/jappixmini.php', 'jappixmini_settings');
	unregister_hook('plugin_settings_post', 'addon/jappixmini/jappixmini.php', 'jappixmini_settings_post');

	unregister_hook('page_end', 'addon/jappixmini/jappixmini.php', 'jappixmini_script');
	unregister_hook('login_hook', 'addon/jappixmini/jappixmini.php', 'jappixmini_login');

	unregister_hook('cron', 'addon/jappixmini/jappixmini.php', 'jappixmini_cron');

	unregister_hook('about_hook', 'addon/jappixmini/jappixmini.php', 'jappixmini_download_source');

	// purge lists of jabber addresses
	q("DELETE FROM `pconfig` WHERE `cat`='jappixmini' AND `k` LIKE 'id:%%'");
}

function jappixmini_module() {}
function jappixmini_init(&$a) {
	if (count($a->argv)==1) {
		// Path: /jappixmini/
		// Serve and receive Jabber addresses.
		jappixmini_serve_addresses($_REQUEST);
		killme();
	}
	else if (count($a->argv)==2 && $a->argv[1]=="extauth_script") {
		// Path: /jappixmini/extauth_script/
		// Display the ejabberd extauth script for the administrator
		jappixmini_extauth_script($a);
		killme();
	}
	else if (count($a->argv)==2 && $a->argv[1]=="extauth") {
		// Path: /jappixmini/extauth/
		// extauth script can validate credentials here
		jappixmini_extauth();
		killme();
	}
}

function jappixmini_content(&$a) {
	if (count($a->argv)==2 && $a->argv[1]=="address_list") {
		// Path: /jappixmini/address_list/
		// Display the list of Jabber addresses for a user
		return jappixmini_address_list($a);
	}
}

function jappixmini_cron(&$a, $d) {
	// For autosubscribe/autoapprove, we need to maintain a list of jabber addresses of our contacts.

	set_config("jappixmini", "last_cron_execution", $d);

	jappixmini_discover_addresses();
}
