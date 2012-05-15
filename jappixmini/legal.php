<?php
//
// Copyright 2012 "Leberwurscht" <leberwurscht@hoegners.de>
//
// This file is dual-licensed under the MIT license (see MIT.txt) and the AGPL license (see jappix/COPYING).
//

function jappixmini_download_source(&$a,&$b) {
	// Jappix Mini source download link on About page

	$b .= '<h1>Jappix Mini</h1>';
	$b .= '<p>This site uses the jappixmini addon, which includes Jappix Mini by the <a href="'.$a->get_baseurl().'/addon/jappixmini/jappix/AUTHORS">Jappix authors</a> and is distributed under the terms of the <a href="'.$a->get_baseurl().'/addon/jappixmini/jappix/COPYING">GNU Affero General Public License</a>.</p>';
	$b .= '<p>You can download the <a href="'.$a->get_baseurl().'/addon/jappixmini.tgz">source code of the addon</a>. The rest of Friendica is distributed under compatible licenses and can be retrieved from <a href="https://github.com/friendica/friendica">https://github.com/friendica/friendica</a> and <a href="https://github.com/friendica/friendica-addons">https://github.com/friendica/friendica-addons</a>.</p>';
}

?>
