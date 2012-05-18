<?php
//
// Copyright 2012 "Leberwurscht" <leberwurscht@hoegners.de>
//
// This file is dual-licensed under the MIT license (see MIT.txt) and the AGPL license (see jappix/COPYING).
//

function jappixmini_extauth() {
	if (!intval(get_config("jappixmini", "extauth"))) killme();

	header("Content-type: text/plain");

	if ($_REQUEST["extauth"]=="auth") {
		$username = $_REQUEST["username"];
		$password = $_REQUEST["password"];

		// try session id
		$uid = local_user();
		$r = q("SELECT * FROM `user` WHERE `nickname`='%s' AND `uid`='%d'",
			dbesc($username),
			$uid
		);
		if (count($r)) {
			echo "1";
			killme();
		}

		// try friendica password
		$encrypted = hash('whirlpool',$password);
		$r = q("SELECT * FROM `user` WHERE `nickname`='%s' AND `password`='%s'",
			dbesc($username),
			dbesc($encrypted)
		);
		if (count($r)) {
			echo "1";
			killme();
		}

		echo "0";
		killme();
	}
	else if ($_REQUEST["extauth"]=="isuser") {
		$username = $_REQUEST["username"];

		$r = q("SELECT * FROM `user` WHERE `nickname`='%s'",
			dbesc($username)
		);
		if (count($r)) echo "1";
		else echo "0";

		killme();
	}
	else {
		echo "0";
		killme();
	}

}

function jappixmini_extauth_script($a) {
	if (!intval(get_config("jappixmini", "extauth"))) killme();

	$secret = get_config("jappixmini", "extauth-secret");
	if (!$secret) {
		$secret = random_string();
		set_config("jappixmini", "extauth-secret", $secret);
	}

	header("Content-type: text/plain");

	// extauth script
	echo "#!/usr/bin/env python\n\n";
	echo "TARGET_URL = '".addslashes($a->get_baseurl())."/jappixmini/extauth/'\n\n";

	echo <<<EOF
import sys
from urllib import urlencode
import struct, urllib2

while True:
    announcement = sys.stdin.read(2)
    length, = struct.unpack("!h", announcement)
    string = sys.stdin.read(length)

    opener = urllib2.build_opener()
    data = {}

    try:
        parts = string.split(":")
        if parts[0]=="auth":
            username = parts[1]
	    # server = parts[2]
            password = parts[3]

            data["extauth"] = "auth"
            data["username"] = username

            if password.startswith("PHPSESSID="):
                marker, sessid = password.split("=", 1)
                cookie = urlencode({"PHPSESSID":sessid})
                opener.addheaders.append(("Cookie", cookie))
            else:
                data["password"] = password
        elif parts[0]=="isuser":
            username = parts[1]
	    # server = parts[2]

            data["extauth"] = "isuser"
            data["username"] = username

        data = urlencode(data)
        f = opener.open(TARGET_URL, data)
        response = f.read()
        response = int(response)
        if not response in (0,1): raise Exception("Invalid response - must be 0 or 1!")
    except Exception, e:
        print >>sys.stderr, e
        response = 0

    announcement = struct.pack("!h", 2)
    string = struct.pack("!h", response)

    sys.stdout.write(announcement)
    sys.stdout.write(string)
    sys.stdout.flush()
EOF;
}

?>
