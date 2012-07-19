<?php

/**
* Name: Light
* Description: light protocol
* Version: 1.0
* Author: leberwurscht <leberwurscht@hoegners.de>
*
*/

require_once("authentication.php");

function light_install() {
  register_hook('plugin_settings', 'addon/light/light.php', 'light_settings');
  register_hook('plugin_settings_post', 'addon/light/light.php', 'light_settings_post');
}


function light_uninstall() {
  unregister_hook('plugin_settings', 'addon/light/light.php', 'light_settings');
  unregister_hook('plugin_settings_post', 'addon/light/light.php', 'light_settings_post');
}

function light_settings(&$a, &$s) {
  if(! local_user()) return;
  $uid = local_user();

  $activated = get_pconfig($uid, 'light', 'activated');
  $activated = intval($activated) ? ' checked="checked"' : '';

  $s .= '<div class="settings-block">';
  $s .= '<h3>Light addon settings</h3>';
  $s .= '<div>';
  $s .= '<label for="light-activated">Activate addon</label>';
  $s .= ' <input id="light-activated" type="checkbox" name="light-activated" value="1"'.$activated.' />';
  $s .= '<br />';
  $s .= '<input type="submit" name="light-submit" value="' . t('Submit') . '" />';
  $s .= '</div>';
  $s .= '</div>';

  // TODO: A function called "reauthorize friend" for friends who lose their token. Should generate
  //       a file containing a new token and the necessary URLs which can be sent to the friend.
  //       The friend should be able to import this file into the TearDownWalls firefox extension.
}

function light_settings_post(&$a, $b) {
  if(! local_user()) return;
  $uid = local_user();

  if (!$b['light-submit']) return;

  $activated = intval($b['light-activated']);
  set_pconfig($uid, 'light', 'activated', $activated);
}


function light_module() {}
function light_init(&$a) {
  $uid = -1;
  if ($_REQUEST["target"]) {
    $r = q("SELECT * FROM `user` WHERE `nickname`='%s'",
      dbesc($_REQUEST["target"])
    );

    if (count($r)) {
      $uid = $r[0]["uid"];
      $activated = intval(get_pconfig($uid, "light", "activated"));

      if (!$activated) { // addon deactivated by user
        $uid = -1;
      }
      else {
        $language = $r[0]["language"];
        $notify_flags = $r[0]["notify-flags"];
        $username = $r[0]["username"];
        $nickname = $r[0]["nickname"];
        $email = $r[0]["email"];
      }
    }
  }

  if (count($a->argv)==2 && $a->argv[1]=="intro") {
    if ($uid==-1) die("Invalid target.");

    $normalised_link = normalise_link($_REQUEST["url"]);

    // check if already introduced
    $r = q("SELECT `id` FROM `contact` WHERE `uid`=%d AND `network`='%s' AND `nurl`='%s'",
      $uid,
      dbesc(NETWORK_LIGHT),
      dbesc($normalised_link)
    );
    if (count($r)) die("Already introduced.");

    // insert into contact table
    // note: notify set to 0, otherwise contact will not show up in acl selector
    q("INSERT INTO `contact` ( `uid`, `created`, `url`, `nurl`, `addr`, `alias`, `notify`, `poll`, `name`, `nick`, `photo`, `network`, `rel`, `priority`, `writable`, `blocked`, `readonly`, `pending` ) VALUES ( %d, '%s', '%s', '%s', '', '', '0', '', '%s', '%s', '%s', '%s', %d, 1, 1, 0, 0, 1 ) ",
      $uid,
      dbesc(datetime_convert()),
      dbesc($_REQUEST["url"]),
      dbesc($normalised_link),
      dbesc($_REQUEST["name"]),
      dbesc($_REQUEST["name"]),
      dbesc($_REQUEST["avatar"]),
      dbesc(NETWORK_LIGHT),
      intval(CONTACT_IS_FRIEND)
    );

    // read contact id
    $r = q("SELECT `id` FROM `contact` WHERE `uid`=%d AND `network`='%s' AND `nurl`='%s'",
      $uid,
      dbesc(NETWORK_LIGHT),
      dbesc($normalised_link)
    );
    $cid = $r[0]["id"];

    // insert into intro table
    $hash = random_string();

    q("INSERT INTO `intro` ( `uid`, `fid`, `contact-id`, `note`, `hash`, `datetime`, `blocked` )
      VALUES( %d, %d, %d, '%s', '%s', '%s', %d )",
      $uid,
      0,
      $cid,
      dbesc($_REQUEST["body"]),
      dbesc($hash),
      dbesc(datetime_convert()),
      intval(0)
    );

    require_once('include/enotify.php');
    notification(array(
      'type'         => NOTIFY_INTRO,
      'notify_flags' => $notify_flags,
      'language'     => $language,
      'to_name'      => $username,
      'to_email'     => $email,
      'uid'          => $uid,
      'link'         => $a->get_baseurl() . '/notifications/intros',
      'source_name'  => $_REQUEST["name"],
      'source_link'  => $_REQUEST["url"],
      'source_photo' => $_REQUEST["avatar"],
      'verb'         => ACTIVITY_REQ_FRIEND,
      'otype'        => 'intro'
    ));

    // generate token, return it and save the hash
    $token = random_string();

    set_pconfig($uid, "light", "token:$cid", hash('whirlpool', $token));
    echo $token;
    killme();
  }
  else if (count($a->argv)==2 && $a->argv[1]=="post") {
    $auth = light_authenticate();
    if (!$auth) die("Not authenticated.");

    // adapted from addon/facebook/facebook.php
    $cmntdata = array();
    $cmntdata['verb'] = ACTIVITY_POST;
    $cmntdata['gravity'] = 6;
    $cmntdata['uid'] = $auth["uid"];
    $cmntdata['wall'] = 0;
    $cmntdata['uri'] = 'light::'.random_string();
    if (!$_REQUEST["in_reply_to"]) {
      $cmntdata['parent-uri'] = $cmntdata['uri'];
      $cmntdata['allow_cid'] = '<'.$auth["id"].'>'; // only recipient may see it
    }
    else {
      $cmntdata['parent-uri'] = $_REQUEST["in_reply_to"];
    }

    $cmntdata['unseen'] = 1;

    $cmntdata['contact-id'] = $auth["id"];

    if($auth['readonly']) return;

    $cmntdata["author-name"] = $r[0]["name"];
    $cmntdata["author-link"] = $r[0]["url"];
    $cmntdata["author-avatar"] = $r[0]["photo"];

    $cmntdata['app'] = 'light';
    $cmntdata['created'] = datetime_convert();
    $cmntdata['edited'] = datetime_convert();
    $cmntdata['verb'] = ACTIVITY_POST;
    $cmntdata['body'] = $_REQUEST["body"];

    require_once('include/auth.php');
    require_once('include/items.php');
    $item = item_store($cmntdata);
    echo "stored";
    killme();
  }
  else if (count($a->argv)==2 && $a->argv[1]=="stream") {
    $auth = light_authenticate();
    if (!$auth) die("Not authenticated.");

    // get nickname for $auth["uid"]
    $r = q("SELECT `nickname` FROM `user` WHERE `uid`=%d", $auth["uid"]);
    $nickname = $r[0]["nickname"];

    require_once('include/auth.php');
    require_once('include/items.php');
    $bbfeed = get_feed_for($a, $auth["id"], $nickname, False, -2);

    // replace bbcode by html
    require_once("include/bbcode.php");
    function callback($m) {
      $r = '<content type="html">';
      $r .= htmlspecialchars(bbcode($m[3]));
      $r .= '</content>';

      return $r;
    }
    $feed = preg_replace_callback('~<content\s+(.*?)type="text"(.*?)>(.*?)</content>~si', callback, $bbfeed);

    echo $feed;
    killme();
  }
}

function light_content(&$a) {
}
