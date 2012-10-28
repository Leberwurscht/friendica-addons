<?php

/**
* Name: Light
* Description: light protocol
* Version: 1.0
* Author: leberwurscht <leberwurscht@hoegners.de>
*
*/

require_once("authentication.php");

function tdw_config($username, $token, $categories) {
  $a = get_app();
  $feed_url = json_encode($a->get_baseurl() . '/light/v0.1/stream');
  $target_url = json_encode($a->get_baseurl() . '/light/v0.1/post');
  $like_target_url = json_encode($a->get_baseurl() . '/light/v0.1/like');
  $token = json_encode($token);
  $username = json_encode($username);

  if (!$categories) {
    $categories = Array();
  }
  else {
    $categories = explode(",", $categories);
  }
  $categories = json_encode($categories);

  $config = <<<EOD
{
  "name": $username,
  "feed": {
    "url": $feed_url,
    "method": "post",
    "content": {
      "token": $token
    },
    "categories": $categories,
    "verbs": ["http://activitystrea.ms/schema/1.0/post", "http://activitystrea.ms/schema/1.0/like", ""]
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
  },
  "like_target": {
    "url": $like_target_url,
    "method": "post",
    "content": {
      "token": $token,
      "in_reply_to":"{in_reply_to}"
    }
  }
}
EOD;

  return $config;
}

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
  $s .= '<label for="light-categories">Only show these tags to TearDownWalls users (comma-separated):</label>';
  $s .= ' <input id="light-categories" type="text" name="light-categories" value="'.htmlspecialchars($categories).'" />';
  $s .= '<br />';
  $s .= '<a href="'.htmlspecialchars($a->get_baseurl() . '/light/list').'">list of light contacts</a>';
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

  if (count($a->argv)==3 && $a->argv[1]=="v0.1" && $a->argv[2]=="intro") {
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

    // output token and the configuration for teardownwalls
    $config_json = tdw_config($username, $token, $categories);
    $token = json_encode($token);
    // TODO: avatar
    echo <<<EOD
{
  "token": $token,
  "successful": 1,
  "teardownwalls_config": $config_json
}
EOD;
    killme();
  }
  else if (count($a->argv)==3 && $a->argv[1]=="v0.1" && $a->argv[2]=="post") {
    $auth = light_authenticate();
    if (!$auth) die("Not authenticated.");

    // adapted from addon/facebook/facebook.php
    $cmntdata = array();
    $cmntdata['verb'] = ACTIVITY_POST;
    $cmntdata['gravity'] = 6;
    $cmntdata['uid'] = $auth["uid"];
    $cmntdata['wall'] = 1; // this is necessary that we get the posts from get_feed_for. TODO: what implications does this have?
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

    $cmntdata["author-name"] = $auth["name"];
    $cmntdata["author-link"] = $auth["url"];
    $cmntdata["author-avatar"] = $auth["photo"];

    $cmntdata['app'] = 'light';
    $cmntdata['created'] = datetime_convert();
    $cmntdata['edited'] = datetime_convert();
    $cmntdata['body'] = $_REQUEST["body"];

    require_once('include/auth.php');
    require_once('include/items.php');
    $item = item_store($cmntdata);
    echo "stored";
    killme();
  }
  else if (count($a->argv)==3 && $a->argv[1]=="v0.1" && $a->argv[2]=="like") {
    $auth = light_authenticate();
    if (!$auth) die("Not authenticated.");

    // check if there is already a like
    $exists = q("SELECT * FROM `item` WHERE `uid` = %d AND `parent-uri` = '%s' AND `contact-id` = %d AND `verb` = '%s' LIMIT 1",
      $auth["uid"],
      dbesc($_REQUEST["in_reply_to"]),
      $auth["id"],
      ACTIVITY_LIKE
    );
    if (count($exists)) return;

    // adapted from addon/facebook/facebook.php
    $likedata = array();
    $likedata['verb'] = ACTIVITY_LIKE;
    $likedata['gravity'] = 3;
    $likedata['uid'] = $auth["uid"];
    $likedata['wall'] = 0;
    $likedata['uri'] = 'light::'.random_string();

    if (!$_REQUEST["in_reply_to"]) return;
    $likedata['parent-uri'] = $_REQUEST["in_reply_to"];

    $likedata['unseen'] = 1;

    $likedata['contact-id'] = $auth["id"];

    if($auth['readonly']) return;

    $likedata["author-name"] = $auth["name"];
    $likedata["author-link"] = $auth["url"];
    $likedata["author-avatar"] = $auth["photo"];

    $likedata['app'] = 'light';
    $likedata['created'] = datetime_convert();

    // get orig post
    $op = q("SELECT * FROM `item` WHERE `uri` = %s AND `uid` = %d LIMIT 1",
      dbesc($likedata['parent-uri']),
//      $uid !!! TODO: why did it work with this????
      $auth[$uid]
    );
    if (count($op)) $orig_post = $op[0];
    else return;

    $author = '[url=' . $likedata['author-link'] . ']' . $likedata['author-name'] . '[/url]';
    $objauthor = '[url=' . $orig_post['author-link'] . ']' . $orig_post['author-name'] . '[/url]';
    $post_type = t('status');
    $plink = '[url=' . $orig_post['plink'] . ']' . $post_type . '[/url]';
    $likedata['object-type'] = ACTIVITY_OBJ_NOTE;

    $likedata['body'] = sprintf( t('%1$s likes %2$s\'s %3$s'), $author, $objauthor, $plink);
    $likedata['object'] = '<object><type>' . ACTIVITY_OBJ_NOTE . '</type><local>1</local>' .
        '<id>' . $orig_post['uri'] . '</id><link>' . xmlify('<link rel="alternate" type="text/html" href="' . xmlify($orig_post['plink']) . '" />') . '</link><title>' . $orig_post['title'] . '</title><content>' . $orig_post['body'] . '</content></object>';

    require_once('include/auth.php');
    require_once('include/items.php');
    $item = item_store($likedata);
    echo "stored";
    killme();
  }
  else if (count($a->argv)==3 && $a->argv[1]=="v0.1" && $a->argv[2]=="stream") {
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
  else if (count($a->argv)==2 && $a->argv[1]=="tdwfile") {
    $username = $_REQUEST["username"];
    $token = $_REQUEST["token"];
    $categories = $_REQUEST["categories"];

    // output token and the configuration for teardownwalls
    $config_json = tdw_config($username, $token, $categories);

    header('Content-disposition: attachment; filename=connection.json');
    header('Content-type: application/json');

    echo $config_json;
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
      $o .= "<p>Unknown user ".htmlspecialchars($target).".</p>";
      return $o;
    }
    else {
      $username = $r[0]["username"];
    }

    // print message
    $o .= "<p>To get friends with ".htmlspecialchars($username)." without having a Friendica account install the TearDownWalls addon.</p>";
    $o .= "<p>Then click onto the TearDownWalls icon to add me.</p>";

    // add link tag
    $href = htmlspecialchars($a->get_baseurl()."/light/v0.1/intro/?target=".urlencode($target));
    $a->page['htmlhead'] .= '<link rel="alternate" type="application/teardownwalls_intro" title="'.htmlspecialchars($username).'" href="'.$href.'"/>'."\r\n";
  }
  else if (count($a->argv)>=2 && $a->argv[1]=="list") {
    if(! local_user()) return;
    $uid = local_user();

    $o .= '<h2>List of contacts over light protocol</h2>';

    $r = q("SELECT * FROM `pconfig` WHERE `cat`='light' AND `k` LIKE 'token:%%' AND `uid`=%d", $uid);
    foreach ($r as $row) {
      $k = $row["k"];
      $p = strpos($k, ":");
      $cid = intval(substr($k, $p+1));

      // retrieve from contact table
      $c = q("SELECT * FROM `contact` WHERE `id`=%d AND `uid`=%d", $cid, $uid);
      if (!count($c)) { // contact deleted, so delete left-over token
        q("DELETE FROM `pconfig` WHERE `cat`='light' AND `k`='%s' AND `uid`=%d", $k, $uid);
        continue;
      }
      $contact = $c[0];

      $sig = hash_hmac('sha256', "reauthenticate $cid", session_id());
      $o .= '<div><img style="height:1.5em;" src="'.htmlspecialchars($contact["photo"]).'"> <a href="'.htmlspecialchars($contact["url"]).'">'.htmlspecialchars($contact["name"]).'</a> (<a href="'.htmlspecialchars($a->get_baseurl().'/light/reauthenticate?cid='.$cid.'&csrf_sig='.$sig).'" onclick="return confirm(\'Communication will be broken until contact uses newly generated token!\')">reauthenticate</a>)</div>';
    }
  }
  else if (count($a->argv)>=2 && $a->argv[1]=="reauthenticate") {
    if(! local_user()) return;
    $uid = local_user();

    $cid = intval($_REQUEST["cid"]);

    // verify CSRF signature
    $sig = $_REQUEST["csrf_sig"];
    $proper_sig = hash_hmac('sha256', "reauthenticate $cid", session_id());
    if ($sig != $proper_sig) return;

    // get contact
    $c = q("SELECT * FROM `contact` WHERE `id`=%d AND `uid`=%d", $cid, $uid);
    if (!count($c)) return;
    $contact = $c[0];

    // get own username
    $r = q("SELECT * FROM `user` WHERE `uid`=%d", $uid);
    $own_name = $r[0]["username"];

    // generate new token
    $token = random_string();
    set_pconfig($uid, "light", "token:$cid", hash('whirlpool', $token));

    // output
    $o .= '<p>The new authentication token for '.htmlspecialchars($contact["name"]).' is '.htmlspecialchars($token).'.</p>';

    $categories = get_pconfig($uid, "light", "categories");
    $o .= '<form action="'.htmlspecialchars($a->get_baseurl().'/light/tdwfile').'" method="post"><input type="hidden" name="categories" value="'.htmlspecialchars($categories).'"><input type="hidden" name="username" value="'.htmlspecialchars($own_name).'"><input type="hidden" name="token" value="'.htmlspecialchars($token).'"> <p>You can <input type="submit" value="download"> the TearDownWalls connection file and send it to the contact.</p></form>';
  }

  return $o;
}
