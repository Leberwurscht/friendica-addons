<?php

/**
* Name: MozSocial
* Description: Mozilla Social API for Friendica
* Version: 1.0
* Author: leberwurscht <leberwurscht@hoegners.de>
*
*/

function mozsocial_install() {
}

function mozsocial_uninstall() {
}

function mozsocial_module() {}
function mozsocial_init(&$a) {
  if (count($a->argv)==2 && $a->argv[1]=="userdata") {
    if(!( $uid = local_user() )) return;

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
