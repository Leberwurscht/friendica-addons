var end = location.href.indexOf("sidebar.js");
var baselocation = location.href.substr(0, end);

function sign_in() {
  jQuery("#login-failed").hide();

  var xhr = new XMLHttpRequest();
  xhr.open("POST", baselocation + "../../mozsocial/userdata", true);
  xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  xhr.onload = function(e) {
    try {
      var userdata = JSON.parse(xhr.responseText);
      jQuery("#sign-in").hide();
      // TODO: send event to worker so that get_user and get_notifications are called immediately
    }
    catch(e) {
      jQuery("#login-failed").show();
    }
  }
  xhr.onerror = function(e) {
    jQuery("#login-failed").show();
  }

  var username = jQuery("#sign-in input[name=username]").val();
  var password = jQuery("#sign-in input[name=password]").val();
  var persistent = jQuery("#sign-in input[name=persistent]:checked").length;

  var data = 'auth-params=login';
  data += '&username='+encodeURIComponent(username);
  data += '&password='+encodeURIComponent(password);
  if (persistent) {
    data += "&get-login-cookie=1"; // for built-in persistent login of this addon
    data += "&remember=1"; // for native persistent login (since 3.0.1519)
  }

  xhr.send(data);

  return false;
}

jQuery(document).ready(function() {
  jQuery("#sign-in").off();
  jQuery("#sign-in").on('submit', sign_in);
});

navigator.mozSocial.getWorker().port.onmessage = function onmessage(e) {
  var topic = e.data.topic;
  var data = e.data.data;

  if (topic == "social.user-profile") {
    // user changed, so empty notifications
    $ul = jQuery('#nav-notifications-menu');
    $ul.empty();

    // hide sign in if user logged in, show if otherwise
    if (data.userName) {
      jQuery("#sign-in").hide();
      jQuery("#nav-notifications-menu").show();
    }
    else {
      jQuery("#sign-in").show();
      jQuery("#nav-notifications-menu").hide();
    }
  }
  if (topic != "notify") return;


  var parser = new DOMParser();
  var doc = parser.parseFromString(data, "text/xml");
  var $doc = jQuery(doc);

  $ul = jQuery('#nav-notifications-menu');
  $ul.empty();

  $doc.find("notif > note").each(function() {
    var $this = jQuery(this);

    var $li = jQuery('<li>');
    if ($this.attr("seen")=="notify-seen") $li.addClass("notify-seen");
    else $li.addClass("notify-unseen");
    $ul.append($li);

    var $a = jQuery('<a>');
    $a.attr("href", $this.attr("href"));
    $a.attr("target", "friendica");
    $li.append($a);

    var $img = jQuery('<img>');
    $img.attr("src", $this.attr("photo"));
    $a.append($img);

    $span = jQuery('<span class="text">');
    $span.text($this.text());
    $a.append($span);

    $date = jQuery('<span class="notif-when">');
    $date.text($this.attr("date"));
    $a.append($date);
  });
};
