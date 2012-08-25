<?php

function light_authenticate() {
	// - read token from _REQUEST variable
	// - check if there is an entry in the pconfig table with this token
	// - from this entry, we get the contact id
	// - return corresponding contact row in case of success, otherwise return false
	// - also, check whether user has deactivated the addon in the user settings

	$token = $_REQUEST["token"];
	$r = q("SELECT * FROM `pconfig` WHERE `cat`='light' AND `k` LIKE 'token:%%' AND `v`='%s'",
		dbesc(hash('whirlpool', $token))
	);

	if (count($r)) {
		$uid = intval($r[0]["uid"]);
		$activated = intval(get_pconfig($uid, "light", "activated"));
		if (!$activated) { // deactivated by user
			return false;
		}

		// k has format "token:$cid"
		$k = $r[0]["k"];
		$p = strpos($k, ":");
		$cid = intval(substr($k, $p+1));

		// retrieve from contact table
		$r = q("SELECT * FROM `contact` WHERE `id`='%d'", $cid);

		if (!count($r)) return false;
		$contact = $r[0];
		if (!$uid==$contact["uid"]) {
			logger("Error: invalid uid in light plugin.");
			return false;
		}

		if ($contact["pending"] || $contact["blocked"]) return false;

		return $contact;
	}
	else {
		return false;
	}
}

?>
