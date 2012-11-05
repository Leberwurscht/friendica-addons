navigator.mozSocial.getWorker().port.onmessage = function onmessage(e) {
    var topic = e.data.topic;
    var data = e.data.data;

    if (topic != "notify") return;

    var parser = new DOMParser();
    var doc = parser.parseFromString(data, "text/xml");
    var $doc = jQuery(doc);

    var $body = jQuery("body");
    $body.empty();

    $doc.find("notif > note").each(function() {
      var $this = jQuery(this);

      var $div = jQuery('<div class="notification">');
      if ($this.attr("seen")=="notify-seen") $div.addClass("seen");

      var $imga = jQuery('<a>');
      $imga.attr("href", $this.attr("url"));
      $imga.attr("target", "friendica");
      $div.append($imga);

      var $img = jQuery('<img class="avatar">');
      $img.attr("src", $this.attr("photo"));
      $imga.append($img);

      var $span = jQuery('<span class="message">');
      $span.text($this.text());
      $div.append($span);

      $div.append(" ");

      $datea = jQuery('<a>');
      $datea.attr("href", $this.attr("href"));
      $datea.attr("target", "friendica");
      $div.append($datea);

      $date = jQuery('<span class="date">');
      $date.text($this.attr("date"));
      $datea.append($date);

      $body.append($div);
    });
};
