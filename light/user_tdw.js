jQuery(document).ready(function(){
  jQuery("input[type=checkbox][name=public]").click(function() {
    var checked = !!jQuery(this).filter(":checked").length;
    var groups = jQuery("input[type=checkbox][name=group_allow]");

    if (checked) groups.attr("disabled", "disabled");
    else groups.removeAttr("disabled");
  });
  jQuery("#download").click(function(e) {
    e.preventDefault();
    var config = '{'+
    '  "name": USERNAME,'+
    '  "feed": {'+
    '    "headers": { "Authorization": "Basic AUTH" },'+
    '    "url": FEED,'+
    '    "method": "get",'+
    '    "verbs": ["http://activitystrea.ms/schema/1.0/post", "http://activitystrea.ms/schema/1.0/like", ""]'+
    '  },'+
    '  "target": {'+
    '    "headers": { "Authorization": "Basic AUTH" },'+
    '    "url": TARGET,'+
    '    "method": "post",'+
    '    "content": {'+
    '      "status":"{body}",'+
    '      GROUP_ALLOW'+
    '      "in_reply_to_status_id":"{in_reply_to}"'+
    '    }'+
    '  },'+
    '  "like_target": {'+
    '    "headers": { "Authorization": "Basic AUTH" },'+
    '    "url": TARGET,'+
    '    "method": "post",'+
    '    "content": {'+
    '      "status":"---",'+
    '      "verb": "http://activitystrea.ms/schema/1.0/like",'+
    '      GROUP_ALLOW'+
    '      "in_reply_to_status_id":"{in_reply_to}"'+
    '    }'+
    '  }'+
    '}';
    config = config.replace(/USERNAME/g, username);
    config = config.replace(/FEED/g, feed);
    config = config.replace(/TARGET/g, target);

    if (jQuery("input[type=checkbox][name=public]:checked").length) {
      config = config.replace(/GROUP_ALLOW/g, "");
    }
    else {
      var replacement = '';
      var i=0;
      jQuery("input[type=checkbox][name=group_allow]:checked").each(function(){
        replacement += '"group_allow['+(i++)+']": "'+jQuery(this).val()+'",';
      });

      config = config.replace(/GROUP_ALLOW/g, replacement);
    }

    var password = jQuery("input[type=password]").val();
    var auth = base64.encode(nickname+':'+password);
    config = config.replace(/AUTH/g, auth);

    // build data uri
    var data_uri = "data:application/force-download;base64," + base64.encode(config);
    location.href = data_uri;
  });
});
