navigator.mozSocial.getWorker().port.onmessage = function onmessage(e) {
  var topic = e.data.topic;
  var data = e.data.data;

  if (topic != "notify") return;

  if (data) {
    jQuery("#sign-in").hide();
    jQuery("#nav-notifications-menu").show();
  }
  else {
    jQuery("#sign-in").show();
    jQuery("#nav-notifications-menu").hide();
    return;
  }

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
