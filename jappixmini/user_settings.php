<?php
//
// Copyright 2012 "Leberwurscht" <leberwurscht@hoegners.de>
//
// This file is dual-licensed under the MIT license (see MIT.txt) and the AGPL license (see jappix/COPYING).
//

function jappixmini_settings(&$a, &$s) {
    // only if server does not provide own server
    if (get_config("jappixmini","provided_server")) return;

    // addon settings for a user

    $activate = get_pconfig(local_user(),'jappixmini','activate');
    $activate = intval($activate) ? ' checked="checked"' : '';

    $username = get_pconfig(local_user(),'jappixmini','username');
    $username = htmlentities($username);
    $server = get_pconfig(local_user(),'jappixmini','server');
    $server = htmlentities($server);
    $bosh = get_pconfig(local_user(),'jappixmini','bosh');
    $bosh = htmlentities($bosh);
    $password = get_pconfig(local_user(),'jappixmini','password');
    $autosubscribe = get_pconfig(local_user(),'jappixmini','autosubscribe');
    $autosubscribe = intval($autosubscribe) ? ' checked="checked"' : '';
    $autoapprove = get_pconfig(local_user(),'jappixmini','autoapprove');
    $autoapprove = intval($autoapprove) ? ' checked="checked"' : '';
    $encrypt = intval(get_pconfig(local_user(),'jappixmini','encrypt'));
    $encrypt_checked = $encrypt ? ' checked="checked"' : '';
    $encrypt_disabled = $encrypt ? '' : ' disabled="disabled"';

    $info_text = get_config("jappixmini", "infotext");
    $info_text = htmlentities($info_text);
    $info_text = str_replace("\n", "<br />", $info_text);

    // count contacts
    $r = q("SELECT COUNT(1) as `cnt` FROM `pconfig` WHERE `uid`=%d AND `cat`='jappixmini' AND `k` LIKE 'id:%%'", local_user());
    if (count($r)) $contact_cnt = $r[0]["cnt"];
    else $contact_cnt = 0;

    // count jabber addresses
    $r = q("SELECT COUNT(1) as `cnt` FROM `pconfig` WHERE `uid`=%d AND `cat`='jappixmini' AND `k` LIKE 'id:%%' AND `v` LIKE '%%@%%'", local_user());
    if (count($r)) $address_cnt = $r[0]["cnt"];
    else $address_cnt = 0;

    if (!$activate) {
	// load scripts if not yet activated so that password can be saved
        $a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;g=mini.xml"></script>'."\r\n";
        $a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/jappix/php/get.php?t=js&amp;f=presence.js~caps.js~name.js~roster.js"></script>'."\r\n";

        $a->page['htmlhead'] .= '<script type="text/javascript" src="' . $a->get_baseurl() . '/addon/jappixmini/lib.js"></script>'."\r\n";
    }

    $s .= '<div class="settings-block">';

    $s .= '<h3>Jappix Mini addon settings</h3>';
    $s .= '<div>';

    $s .= '<label for="jappixmini-activate">Activate addon</label>';
    $s .= ' <input id="jappixmini-activate" type="checkbox" name="jappixmini-activate" value="1"'.$activate.' />';
    $s .= '<br />';
    $s .= '<label for="jappixmini-username">Jabber username</label>';
    $s .= ' <input id="jappixmini-username" type="text" name="jappixmini-username" value="'.$username.'" />';
    $s .= '<br />';
    $s .= '<label for="jappixmini-server">Jabber server</label>';
    $s .= ' <input id="jappixmini-server" type="text" name="jappixmini-server" value="'.$server.'" />';
    $s .= '<br />';

    $s .= '<label for="jappixmini-bosh">Jabber BOSH host</label>';
    $s .= ' <input id="jappixmini-bosh" type="text" name="jappixmini-bosh" value="'.$bosh.'" />';
    $s .= '<br />';

    $s .= '<label for="jappixmini-password">Jabber password</label>';
    $s .= ' <input type="hidden" id="jappixmini-password" name="jappixmini-encrypted-password" value="'.$password.'" />';
    $s .= ' <input id="jappixmini-clear-password" type="password" value="" onchange="jappixmini_set_password();" />';
    $s .= '<br />';
    $onchange = "document.getElementById('jappixmini-friendica-password').disabled = !this.checked;jappixmini_set_password();";
    $s .= '<label for="jappixmini-encrypt">Encrypt Jabber password with Friendica password (recommended)</label>';
    $s .= ' <input id="jappixmini-encrypt" type="checkbox" name="jappixmini-encrypt" onchange="'.$onchange.'" value="1"'.$encrypt_checked.' />';
    $s .= '<br />';
    $s .= '<label for="jappixmini-friendica-password">Friendica password</label>';
    $s .= ' <input id="jappixmini-friendica-password" name="jappixmini-friendica-password" type="password" onchange="jappixmini_set_password();" value=""'.$encrypt_disabled.' />';
    $s .= '<br />';
    $s .= '<label for="jappixmini-autoapprove">Approve subscription requests from Friendica contacts automatically</label>';
    $s .= ' <input id="jappixmini-autoapprove" type="checkbox" name="jappixmini-autoapprove" value="1"'.$autoapprove.' />';
    $s .= '<br />';
    $s .= '<label for="jappixmini-autosubscribe">Subscribe to Friendica contacts automatically</label>';
    $s .= ' <input id="jappixmini-autosubscribe" type="checkbox" name="jappixmini-autosubscribe" value="1"'.$autosubscribe.' />';
    $s .= '<br />';
    $s .= '<label for="jappixmini-purge">Purge internal list of jabber addresses of contacts</label>';
    $s .= ' <input id="jappixmini-purge" type="checkbox" name="jappixmini-purge" value="1" />';
    $s .= '<br />';
    if ($info_text) $s .= '<br />Configuration help:<p style="margin-left:2em;">'.$info_text.'</p>';
    $s .= '<br />Status:<p style="margin-left:2em;">Addon knows <a href="' . $a->get_baseurl() . '/jappixmini/address_list/">'.$address_cnt.' Jabber addresses of '.$contact_cnt.' Friendica contacts</a> (takes some time, usually 10 minutes, to update).</p>';
    $s .= '<input type="submit" name="jappixmini-submit" value="' . t('Submit') . '" />';
    $s .= ' <input type="button" value="Add contact" onclick="jappixmini_addon_subscribe();" />';
//    $s .= ' <input type="button" value="Debug connection" id="jappixmini-debug-button" />';
    $s .= '</div>';

    $s .= '</div>';

    $a->page['htmlhead'] .= "<script type=\"text/javascript\">
        function jappixmini_set_password() {
            encrypt = document.getElementById('jappixmini-encrypt').checked;
            password = document.getElementById('jappixmini-password');
            clear_password = document.getElementById('jappixmini-clear-password');
            if (encrypt) {
                friendica_password = document.getElementById('jappixmini-friendica-password');

                if (friendica_password) {
                    jappixmini_addon_set_client_secret(friendica_password.value);
                    jappixmini_addon_encrypt_password(clear_password.value, function(encrypted_password){
                        password.value = encrypted_password;
                    });
                }
            }
            else {
                password.value = clear_password.value;
            }
        }

        jQuery(document).ready(function() {
            encrypt = document.getElementById('jappixmini-encrypt').checked;
            password = document.getElementById('jappixmini-password');
            clear_password = document.getElementById('jappixmini-clear-password');
            if (encrypt) {
                jappixmini_addon_decrypt_password(password.value, function(decrypted_password){
                    clear_password.value = decrypted_password;
                });
            }
            else {
                clear_password.value = password.value;
            }
        });

"./*    jQuery(document).ready(function() {
            jQuery('#jappixmini-debug-button').click(function(){
                setDB('jappix-mini', 'debug-username', document.getElementById('jappixmini-username'));
                setDB('jappix-mini', 'debug-server', document.getElementById('jappixmini-server'));
                setDB('jappix-mini', 'debug-password', document.getElementById('jappixmini-clear-password'));
                setDB('jappix-mini', 'debug-bosh', document.getElementById('jappixmini-bosh'));
		window.location = '".$a->get_baseurl()."/addon/jappixmini/debug/1.php';
            });*/"
        });
    </script>";
}

function jappixmini_settings_post(&$a,&$b) {
	// save addon settings for a user

	if(! local_user()) return;
	$uid = local_user();

	if($_POST['jappixmini-submit']) {
		$encrypt = intval($b['jappixmini-encrypt']);
		if ($encrypt) {
			// check that Jabber password was encrypted with correct Friendica password
			$friendica_password = trim($b['jappixmini-friendica-password']);
			$encrypted = hash('whirlpool',$friendica_password);
			$r = q("SELECT * FROM `user` WHERE `uid`=$uid AND `password`='%s'",
				dbesc($encrypted)
			);
			if (!count($r)) {
				info("Wrong friendica password!");
				return;
			}
		}

		$purge = intval($b['jappixmini-purge']);

		$username = trim($b['jappixmini-username']);
		$old_username = get_pconfig($uid,'jappixmini','username');
		if ($username!=$old_username) $purge = 1;

		$server = trim($b['jappixmini-server']);
		$old_server = get_pconfig($uid,'jappixmini','server');
		if ($server!=$old_server) $purge = 1;

		$activate = intval($b['jappixmini-activate']);
		$was_activated = get_pconfig($uid,'jappixmini','activate');
		if ($was_activated && !$activate) $purge = 1;

		set_pconfig($uid,'jappixmini','username',$username);
		set_pconfig($uid,'jappixmini','server',$server);
		set_pconfig($uid,'jappixmini','bosh',trim($b['jappixmini-bosh']));
		set_pconfig($uid,'jappixmini','password',trim($b['jappixmini-encrypted-password']));
		set_pconfig($uid,'jappixmini','autosubscribe',intval($b['jappixmini-autosubscribe']));
		set_pconfig($uid,'jappixmini','autoapprove',intval($b['jappixmini-autoapprove']));
		set_pconfig($uid,'jappixmini','activate',$activate);
		set_pconfig($uid,'jappixmini','encrypt',$encrypt);
		info( 'Jappix Mini settings saved.' );

		if ($purge) {
			q("DELETE FROM `pconfig` WHERE `uid`=$uid AND `cat`='jappixmini' AND `k` LIKE 'id:%%'");
			info( 'List of addresses purged.' );
		}
	}
}


function jappixmini_address_list() {
	$uid = local_user();
	if(!$uid) return;

	$addresses = 0;
	$with = "";
	$without = "";

	$rows = q("SELECT * FROM `pconfig` WHERE `uid`=$uid AND `cat`='jappixmini' AND `k` LIKE 'id:%%'");
	foreach ($rows as $row) {
		$key = $row['k'];
		$pos = strpos($key, ":");
		$dfrn_id = substr($key, $pos+1);
		$r = q("SELECT * FROM `contact` WHERE `uid`=$uid AND (`dfrn-id`='%s' OR `issued-id`='%s')",
			dbesc($dfrn_id),
			dbesc($dfrn_id)
		);
		$name = $r[0]["name"];
		$image = $r[0]["micro"];
		if ($r[0]["url"]) $url = $r[0]["url"];
		else $url = $r[0]["nurl"];

		$value = $row['v'];
		$pos = strpos($value, ":");
		$address = substr($value, $pos+1);
		if (!$address) {
			$address = "No address.";
			$target = &$without;
		}
		else {
			$addresses += 1;
			$target = &$with;
		}

		$address = htmlspecialchars($address);
		$name = htmlspecialchars($name);
		$url = htmlspecialchars($url);
		$image = htmlspecialchars($image);
		$target .= "<tr><td><a href=\"$url\"><img alt=\"\" src=\"$image\">$name</a></td><td>$address</td></tr>";
	}

	return "<p>Knowing $addresses addresses.</p><table border=\"1\">".$with.$without."</table>";
}

?>
