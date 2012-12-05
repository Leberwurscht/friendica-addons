<?php

/**
* Name: MozSocial
* Description: Mozilla Social API for Friendica
* Version: 1.0
* Author: leberwurscht <leberwurscht@hoegners.de>
*
*/

function mozsocial_install() {
  register_hook('plugin_settings', 'addon/mozsocial/mozsocial.php', 'mozsocial_settings');
  register_hook('plugin_settings_post', 'addon/mozsocial/mozsocial.php', 'mozsocial_settings_post');

  register_hook('page_end', 'addon/mozsocial/mozsocial.php', 'mozsocial_add_manifest');

  register_hook('authenticate', 'addon/mozsocial/mozsocial.php', 'mozsocial_authenticate_with_login_cookie');
  register_hook('logged_in', 'addon/mozsocial/mozsocial.php', 'mozsocial_create_login_cookie');
  register_hook('logging_out', 'addon/mozsocial/mozsocial.php', 'mozsocial_remove_login_cookie');
}

function mozsocial_uninstall() {
  unregister_hook('plugin_settings', 'addon/mozsocial/mozsocial.php', 'mozsocial_settings');
  unregister_hook('plugin_settings_post', 'addon/mozsocial/mozsocial.php', 'mozsocial_settings_post');

  unregister_hook('page_end', 'addon/mozsocial/mozsocial.php', 'mozsocial_add_manifest');

  unregister_hook('authenticate', 'addon/mozsocial/mozsocial.php', 'mozsocial_authenticate_with_login_cookie');
  unregister_hook('logged_in', 'addon/mozsocial/mozsocial.php', 'mozsocial_create_login_cookie');
  unregister_hook('logging_out', 'addon/mozsocial/mozsocial.php', 'mozsocial_remove_login_cookie');
}

function mozsocial_remember_support() {
  // check if this friendica version has support for persistent login (since 3.0.1519)
  $version = split("\.", FRIENDICA_VERSION);
  $first = intval($version[0]);
  $second = intval($version[1]);
  $third = intval($version[2]);

  if (($first<3) || ($first==3 && $second==0 && $third<1519)) {
    $support = false;
  }
  else {
    $support = true;
  }

  return $support;
}

function mozsocial_module() {}
function mozsocial_init(&$a) {
  if (count($a->argv)==2 && $a->argv[1]=="userdata") {
    if(!( $uid = local_user() )) {
      $info = Array();
      $info["try_login_cookie"] = mozsocial_remember_support() ? 0 : 1;

      header('Content-type: application/json');
      echo json_encode($info);
      killme();
    }

    $user = q("SELECT * FROM `contact` WHERE `uid`=%d AND `self`=1", $uid);
    $user = $user[0];

    $info = Array();
    $info["displayName"] = $user["name"];
    $info["userName"] = $user["nick"];
    $info["portrait"] = $user["micro"];
    $info["profileURL"] = $user["url"];

    header('Content-type: application/json');
    echo json_encode($info);
    killme();
  }
}

function mozsocial_create_login_cookie($a,&$b) {
  if (!$_REQUEST['get-login-cookie']) return;
  if (mozsocial_remember_support()) return;

  $uid = $b['uid'];
  $username = $b['nickname'];

  // get series identifer from cookie / regenerate
  if ($_COOKIE['mozsocial-serial-id']) {
    $serial_id = $_COOKIE['mozsocial-serial-id'];
  }
  else {
    $serial_id = random_string(64, RANDOM_STRING_HEX);
  }

  // create a new password
  $password = random_string(64, RANDOM_STRING_HEX);
  $hash = hash('whirlpool', $password);
  set_pconfig($uid, 'mozsocial', 'password/'.$serial_id, $hash);

  // path / is needed, otherwise cookies can't be deleted from logout page
  $lifetime = 3600*24*365;
  setcookie("mozsocial-serial-id", $serial_id, time()+$lifetime, "/");
  setcookie("mozsocial-password", $password, time()+$lifetime, "/");
  setcookie("mozsocial-username", $username, time()+$lifetime, "/");
}

function mozsocial_authenticate_with_login_cookie($a,&$b) {
  // following http://www.jaspan.com/improved_persistent_login_cookie_best_practice
  // note: "strongly worded warning" is not implemented due to http://drupal.org/node/327263 and not all remembered
  // sessions are deleted but only the affected one.
  // Moreover, friendica does not ask for the old password when a new password is entered.

  // only for /mozsocial/userdata module
  if (count($a->argv)!=2&& $a->argv[0]!="mozsocial" && $a->argv[1]!="userdata") return;

  // only if try-login-cookie=1
  if (!$_REQUEST['try-login-cookie']) return;

  // get user
  $r = q("SELECT * FROM `user` WHERE `nickname`='%s' AND `blocked` = 0 AND `verified` = 1 LIMIT 1",
    dbesc($_COOKIE["mozsocial-username"])
  );
  if (!count($r)) return;
  $user = $r[0];

  // allow this login method only for older friendica versions
  if (mozsocial_remember_support()) return;

  // get serial id
  $serial_id = $_COOKIE["mozsocial-serial-id"];
  if (!$serial_id) return;

  // get password hash
  $hash = get_pconfig($user["uid"], "mozsocial", "password/$serial_id");
  if (!$hash) {
    // delete login cookie
    setcookie("mozsocial-serial-id", "", time() - 3600*24*365, "/");
    setcookie("mozsocial-password", "", time() - 3600*24*365, "/");
    setcookie("mozsocial-username", "", time() - 3600*24*365, "/");

    return;
  }

  // check password
  $password = $_COOKIE["mozsocial-password"];
  if (hash('whirlpool', $password) != $hash) {
    logger("mozsocial: invalid login cookie for ".$user["nickname"]);

    // attacker detected, delete password from database
    del_pconfig($user["uid"], "mozsocial", "password/$serial_id");

    // delete login cookie
    setcookie("mozsocial-serial-id", "", time() - 3600*24*365, "/");
    setcookie("mozsocial-password", "", time() - 3600*24*365, "/");
    setcookie("mozsocial-username", "", time() - 3600*24*365, "/");

    return;
  }

  // everything did work, so user is authenticated
  $b['user_record'] = $user;
  $b['authenticated'] = 1;
}

function mozsocial_remove_login_cookie($a, &$b) {
  $uid = local_user();
  if (!$uid) return;

  // get current serial id
  $serial_id = $_COOKIE["mozsocial-serial-id"];
  if (!$serial_id) return;

  // delete password from database
  del_pconfig($uid, "mozsocial", "password/$serial_id");

  // delete login cookie
  setcookie("mozsocial-serial-id", "", time() - 3600*24*365, "/");
  setcookie("mozsocial-password", "", time() - 3600*24*365, "/");
  setcookie("mozsocial-username", "", time() - 3600*24*365, "/");
}

function mozsocial_settings(&$a, &$s) {
  $s .= '<div class="settings-block">';
  $s .= '  <h3>Firefox social API</h3>';
  $s .= '  <div>';
  $s .= '    <input type="submit" name="mozsocial-submit" value="remove remembered sessions" /><br />';
  $s .= '    <input type="button" name="mozsocial-activate" onclick="var event = new CustomEvent(\'ActivateSocialFeature\'); document.dispatchEvent(event); return false;" value="activate" />';
  $s .= '    <br />';
  $s .= '  </div>';
  $s .= '</div>';
}

function mozsocial_settings_post(&$a,&$b) {
  $uid = local_user();
  if (!$uid) return;
  if (!$_POST['mozsocial-submit']) return;

  // delete passwords from database
  q("DELETE FROM `pconfig` WHERE `uid`=%d AND `cat`='mozsocial' AND `k` LIKE 'password/%%'", $uid);

  // delete login cookie
  setcookie("mozsocial-serial-id", "", time() - 3600*24*365, "/");
  setcookie("mozsocial-password", "", time() - 3600*24*365, "/");
  setcookie("mozsocial-username", "", time() - 3600*24*365, "/");
}

function mozsocial_add_manifest(&$a,&$s) {
  $a->page['htmlhead'] .= '<link rel="manifest" type="text/json" href="'.htmlspecialchars($a->get_baseurl()).'/addon/mozsocial/manifest.json"/>'."\r\n";
}
