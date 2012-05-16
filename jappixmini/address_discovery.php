<?php
//
// Copyright 2012 "Leberwurscht" <leberwurscht@hoegners.de>
//
// This file is dual-licensed under the MIT license (see MIT.txt) and the AGPL license (see jappix/COPYING).
//

/*

Problem:
How to discover the jabber addresses of the friendica contacts?

Solution:
Each Friendica site with this addon provides a /jappixmini/ module page. We go through our contacts and retrieve
this information every week using a cron hook.

Problem:
We do not want to make the jabber address public.

Solution:
When two friendica users connect using DFRN, the relation gets a DFRN ID and a keypair is generated.
Using this keypair, we can provide the jabber address only to contacts:

Alice:
  signed_address = openssl_*_encrypt(alice_jabber_address)
send signed_address to Bob, who does
  trusted_address = openssl_*_decrypt(signed_address)
  save trusted_address
  encrypted_address = openssl_*_encrypt(bob_jabber_address)
reply with encrypted_address to Alice, who does
  decrypted_address = openssl_*_decrypt(encrypted_address)
  save decrypted_address

Interface for this:
GET /jappixmini/?role=%s&signed_address=%s&dfrn_id=%s
(or better, using POST to avoid too long URLs)

Response:
json({"status":"ok", "encrypted_address":"%s"})

*/

function jappixmini_serve_addresses($_REQ) {
	// module page where other Friendica sites can submit Jabber addresses to and also can query Jabber addresses
        // of local users

        // only if role is given
	$role = $_REQ["role"];
	if (!$role) return;

	$dfrn_id = $_REQ["dfrn_id"];
	if (!$dfrn_id) killme();

	if ($role=="pub") {
		$r = q("SELECT * FROM `contact` WHERE LENGTH(`pubkey`) AND `dfrn-id`='%s' LIMIT 1",
			dbesc($dfrn_id)
		);
		if (!count($r)) killme();

		$encrypt_func = openssl_public_encrypt;
		$decrypt_func = openssl_public_decrypt;
		$key = $r[0]["pubkey"];
	} else if ($role=="prv") {
		$r = q("SELECT * FROM `contact` WHERE LENGTH(`prvkey`) AND `issued-id`='%s' LIMIT 1",
			dbesc($dfrn_id)
		);
		if (!count($r)) killme();

		$encrypt_func = openssl_private_encrypt;
		$decrypt_func = openssl_private_decrypt;
		$key = $r[0]["prvkey"];
	} else {
		killme();
	}

	$uid = $r[0]["uid"];

	// save the Jabber address we received
	try {
		$signed_address_hex = $_REQ["signed_address"];
		$signed_address = hex2bin($signed_address_hex);

		$trusted_address = "";
		$decrypt_func($signed_address, $trusted_address, $key);

		$now = intval(time());
		set_pconfig($uid, "jappixmini", "id:$dfrn_id", "$now:$trusted_address");
	} catch (Exception $e) {
	}

	// do not return an address if user deactivated plugin
	$activated = get_pconfig($uid, 'jappixmini', 'activate');
	if (!$activated) killme();

	// return the requested Jabber address
	try {
		if ($server=get_config("jappixmini","provided_server"))
		{
			$r = q("SELECT `nickname` FROM `user` WHERE `uid`=%d", $uid);
			$username = $r[0]["nickname"];
		}
		else {
			$username = get_pconfig($uid, 'jappixmini', 'username');
			$server = get_pconfig($uid, 'jappixmini', 'server');
		}


		$address = "$username@$server";

		$encrypted_address = "";
		$encrypt_func($address, $encrypted_address, $key);

		$encrypted_address_hex = bin2hex($encrypted_address);

		$answer = Array(
			"status"=>"ok",
			"encrypted_address"=>$encrypted_address_hex
		);

		$answer_json = json_encode($answer);
		echo $answer_json;
		killme();
	} catch (Exception $e) {
		killme();
	}
}

function jappixmini_discover_addresses() {
	// go through list of users with jabber enabled
	$users = q("SELECT `uid` FROM `pconfig` WHERE `cat`='jappixmini' AND (`k`='autosubscribe' OR `k`='autoapprove') AND `v`='1'");
	logger("jappixmini: Update list of contacts' jabber accounts for ".count($users)." users.");

	foreach ($users as $row) {
		$uid = $row["uid"];

		// for each user, go through list of contacts
		$contacts = q("SELECT * FROM `contact` WHERE `uid`=%d AND ((LENGTH(`dfrn-id`) AND LENGTH(`pubkey`)) OR (LENGTH(`issued-id`) AND LENGTH(`prvkey`)))", intval($uid));
		foreach ($contacts as $contact_row) {
			$request = $contact_row["request"];
			if (!$request) continue;

			$dfrn_id = $contact_row["dfrn-id"];
			if ($dfrn_id) {
				$key = $contact_row["pubkey"];
				$encrypt_func = openssl_public_encrypt;
				$decrypt_func = openssl_public_decrypt;
				$role = "prv";
			} else {
				$dfrn_id = $contact_row["issued-id"];
				$key = $contact_row["prvkey"];
				$encrypt_func = openssl_private_encrypt;
				$decrypt_func = openssl_private_decrypt;
				$role = "pub";
			}

			// check if jabber address already present
			$present = get_pconfig($uid, "jappixmini", "id:".$dfrn_id);
			$now = intval(time());
			if ($present) {
				// $present has format "timestamp:jabber_address"
				$p = strpos($present, ":");
				$timestamp = intval(substr($present, 0, $p));

				// do not re-retrieve jabber address if last retrieval
				// is not older than a week
				if ($now-$timestamp<3600*24*7) continue;
			}

			// construct retrieval address
			$pos = strpos($request, "/dfrn_request/");
			if ($pos===false) continue;

			$url = substr($request, 0, $pos)."/jappixmini?role=$role";

			// construct own address
			if ($server=get_config("jappixmini","provided_server"))
			{
				$r = q("SELECT `nickname` FROM `user` WHERE `uid`=%d", $uid);
				$username = $r[0]["nickname"];
			}
			else {
				$username = get_pconfig($uid, 'jappixmini', 'username');
				if (!$username) continue;
				$server = get_pconfig($uid, 'jappixmini', 'server');
				if (!$server) continue;
			}

			$address = $username."@".$server;

			// sign address
			$signed_address = "";
			$encrypt_func($address, $signed_address, $key);

			// construct request parameters
			$signed_address_hex = bin2hex($signed_address);
			$params = array();
			$params["signed_address"] = $signed_address_hex;
			$params["dfrn_id"] = $dfrn_id;

			try {
				// send request
				$answer_json = post_url($url, $params);

				// parse answer
				$answer = json_decode($answer_json);
				if ($answer->status != "ok") throw new Exception();

				$encrypted_address_hex = $answer->encrypted_address;
				if (!$encrypted_address_hex) throw new Exception();

				$encrypted_address = hex2bin($encrypted_address_hex);
				if (!$encrypted_address) throw new Exception();

				// decrypt address
				$decrypted_address = "";
				$decrypt_func($encrypted_address, $decrypted_address, $key);
				if (!$decrypted_address) throw new Exception();
			} catch (Exception $e) {
				$decrypted_address = "";
			}

			// save address
			set_pconfig($uid, "jappixmini", "id:$dfrn_id", "$now:$decrypted_address");
		}
	}
}

?>
