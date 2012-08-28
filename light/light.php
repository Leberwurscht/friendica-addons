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

  $categories = get_pconfig($uid, 'light', 'categories');

  $s .= '<div class="settings-block">';
  $s .= '<h3>Light addon settings</h3>';
  $s .= '<div>';
  $s .= '<label for="light-activated">Activate addon</label>';
  $s .= ' <input id="light-activated" type="checkbox" name="light-activated" value="1"'.$activated.' />';
  $s .= '<br />';
  $s .= '<label for="light-categories">Only show this tags to TearDownWalls users (comma-separated):</label>';
  $s .= ' <input id="light-categories" type="text" name="light-categories" value="'.htmlentities($categories).'" />';
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

  $categories = $b['light-categories'];
  set_pconfig($uid, 'light', 'categories', $categories);
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
    if ($uid==-1) {
      die('{"successful": 0, "error": "Invalid target."}');
    }

    $normalised_link = normalise_link($_REQUEST["url"]);

    // check if already introduced
    $r = q("SELECT `id` FROM `contact` WHERE `uid`=%d AND `network`='%s' AND `nurl`='%s'",
      $uid,
      dbesc(NETWORK_LIGHT),
      dbesc($normalised_link)
    );
    if (count($r)) die('{"successful": 0, "error": "Already introduced."}');

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

    // generate token and save the hash
    $token = random_string();
    set_pconfig($uid, "light", "token:$cid", hash('whirlpool', $token));

    // get categories
    $categories = get_pconfig($uid, "light", "categories");
    if (!$categories) {
      $categories = Array();
    }
    else {
      $categories = explode(",", $categories);
    }

    // output token and the configuration for teardownwalls
    $feed_url = json_encode($a->get_baseurl() . '/light/stream');
    $target_url = json_encode($a->get_baseurl() . '/light/post');
    $token = json_encode($token);
    $categories = json_encode($categories);
    echo <<<EOD
{
  "token": $token,
  "successful": 1,
  "teardownwalls_config": {
    "feed": {
      "url": $feed_url,
      "method":"post",
      "content": {
        "token": $token
      },
      "categories": $categories
    },
    "target": {
      "url": $target_url,
      "method": "post",
      "content": {
        "token": $token,
        "body":"{body}",
        "title":"{title}",
        "in_reply_to":"{in_reply_to}"
      }
    }
  }
}
EOD;
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
    $feed = get_feed_for($a, $auth["id"], $nickname, False, -2, True);

    echo $feed;
    killme();
  }
}

function light_content(&$a) {
  $o = '';

  if (count($a->argv)>=2 && $a->argv[1]=="getfriends") {
    // check target
    $target = $a->argv[2];
    $r = q("SELECT * FROM `user` WHERE `nickname`='%s'", dbesc($target));
    if (!count($r)) {
      $o .= "<p>Unknown user ".htmlentities($target).".</p>";
      return $o;
    }

    // print message
    $o .= "<p>To get friends with ".htmlentities($target)." without having a Friendica account install the TearDownWalls addon.</p>";
    $o .= "<p>Then click onto the TearDownWalls icon to add me.</p>";

    // add link tag
    $href = htmlentities($a->get_baseurl()."/light/intro/?target=".urlencode($target));
    $a->page['htmlhead'] .= '<link rel="alternate" type="application/teardownwalls_intro" href="'.$href.'"/>'."\r\n";
  }

  return $o;
}
