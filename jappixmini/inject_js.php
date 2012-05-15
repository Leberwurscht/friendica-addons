<?php
//
// Copyright 2012 "Leberwurscht" <leberwurscht@hoegners.de>
//
// This file is dual-licensed under the MIT license (see MIT.txt) and the AGPL license (see jappix/COPYING).
//

/*

Problem:
* jabber password should not be stored on server
* jabber password should not be sent between server and browser as soon as the user is logged in
* jabber password should not be reconstructible from communication between server and browser as soon as the user is logged in

Solution:
Only store an encrypted version of the jabber password on the server. The encryption key is only available to the browser
and not to the server (at least as soon as the user is logged in). It can be stored using the jappix setDB function.

This encryption key could be the friendica password, but then this password would be stored in the browser in cleartext.
It is better to use a hash of the password.
The server should not be able to reconstruct the password, so we can't take the same hash the server stores. But we can
 use hash("some_prefix"+password). This will however not work with OpenID logins, for this type of login the password must
be queried manually.

*/

function jappixmini_script(&$a,&$s) {
    // adds the script to the page header which starts Jappix Mini

    $uid = local_user();
    if(!$uid) return;

    if (!get_config("jappixmini","provided_server")) {
	    $activate = get_pconfig(local_user(),'jappixmini','activate');
	    $username = get_pconfig(local_user(),'jappixmini','username');
	    $username = str_replace("'", "\\'", $username);
	    $server = get_pconfig(local_user(),'jappixmini','server');
	    $server = str_replace("'", "\\'", $server);
	    $bosh = get_pconfig(local_user(),'jappixmini','bosh');
	    $bosh = str_replace("'", "\\'", $bosh);
	    $encrypt = get_pconfig(local_user(),'jappixmini','encrypt');
	    $encrypt = intval($encrypt);
	    $password = get_pconfig(local_user(),'jappixmini','password');
	    $password = str_replace("'", "\\'", $password);

	    $autoapprove = get_pconfig(local_user(),'jappixmini','autoapprove');
	    $autoapprove = intval($autoapprove);
	    $autosubscribe = get_pconfig(local_user(),'jappixmini','autosubscribe');
	    $autosubscribe = intval($autosubscribe);
    }
    else {
	$activate = 1;
        $r = q("SELECT `nickname` FROM `user` WHERE `uid`=%d", local_user());
        $username = $r[0]["nickname"];
	$server = get_config("jappixmini", "provided_server");
	$bosh = get_config("jappixmini", "provided_bosh");
	$encrypt = 0;
	$password = 'PHPSESSID='.session_id();
        $autoapprove = 1;
        $autosubscribe = 1;
    }

    if (!$activate) return;

    $a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;g=mini.xml"></script>'."\r\n";
    $a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;f=presence.js~caps.js~name.js~roster.js"></script>'."\r\n";

    $a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/lib.js"></script>'."\r\n";


    // set proxy if necessary
    $use_proxy = get_config('jappixmini','bosh_proxy');
    if ($use_proxy) {
        $proxy = $a->get_baseurl().'/addon/jappixmini/proxy.php';
    }
    else {
        $proxy = "";
    }

    // get a list of jabber accounts of the contacts
    $contacts = Array();
    $rows = q("SELECT * FROM `pconfig` WHERE `uid`=$uid AND `cat`='jappixmini' AND `k` LIKE 'id:%%'");
    foreach ($rows as $row) {
        $key = $row['k'];
	$pos = strpos($key, ":");
	$dfrn_id = substr($key, $pos+1);
        $r = q("SELECT `name` FROM `contact` WHERE `uid`=$uid AND (`dfrn-id`='%s' OR `issued-id`='%s')",
		dbesc($dfrn_id),
		dbesc($dfrn_id)
	);
	$name = $r[0]["name"];

        $value = $row['v'];
        $pos = strpos($value, ":");
        $address = substr($value, $pos+1);
	if (!$address) continue;
	if (!$name) $name = $address;

	$contacts[$address] = $name;
    }
    $contacts_json = json_encode($contacts);
    $contacts_hash = sha1($contacts_json);

    // get nickname
    $r = q("SELECT `username` FROM `user` WHERE `uid`=$uid");
    $nickname = json_encode($r[0]["username"]);

    // add javascript to start Jappix Mini
    $a->page['htmlhead'] .= "<script type=\"text/javascript\">
        jQuery(document).ready(function() {
           jappixmini_addon_start('$server', '$username', '$proxy', '$bosh', $encrypt, '$password', $nickname, $contacts_json, '$contacts_hash', $autoapprove, $autosubscribe);
        });
    </script>";

    return;
}

function jappixmini_login(&$a, &$o) {
    // create client secret on login to be able to encrypt jabber passwords

    // for setDB and str_sha1, needed by jappixmini_addon_set_client_secret
    $a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;f=datastore.js~jsjac.js"></script>'."\n";

    // for jappixmini_addon_set_client_secret
    $a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/lib.js"></script>'."\n";

    // save hash of password
    $a->page['htmlhead'] .= '<script  type="text/javascript">'."\n";
    $a->page['htmlhead'] .= ' jQuery(document).ready(function() {'."\n";
    $a->page['htmlhead'] .= '  var pw_input = document.getElementById("id_password");'."\n";
    $a->page['htmlhead'] .= '  $(pw_input.form).submit(function(){'."\n";
    $a->page['htmlhead'] .= '   jappixmini_addon_set_client_secret(pw_input.value);return true;'."\n";
    $a->page['htmlhead'] .= '  });'."\n";
    $a->page['htmlhead'] .= ' });'."\n";
    $a->page['htmlhead'] .= '</script>'."\n";
}


?>
