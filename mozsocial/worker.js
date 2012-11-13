// for debugging: set browser.dom.window.dump.enabled to true in about:config, then start Firefox from command line to see dump() messages

dump("mozsocial: worker running\n");

var userdata = {};

var end = location.href.indexOf("worker.js");
var baselocation = location.href.substr(0, end);

var apiPort;
var ports = [];

var poll_interval = 40*1000;
//poll_interval = 5*1000; // for debugging

var user_job, notifications_job;

onconnect = function(e) {
    var port = e.ports[0];
    ports.push(port);
    dump("mozsocial: new port received, now we have "+ports.length+"\n");

    port.onmessage = function (msgEvent)
    {
        var msg = msgEvent.data;
        dump("mozsocial: worker received '"+msg.topic+"' message\n");

        if (msg.topic == "social.port-closing") {
            if (port == apiPort) {
                apiPort.close();
                apiPort = null;

                if (user_job) clearInterval(user_job);
                if (notifications_job) clearInterval(notifications_job);
            }

            var index = ports.indexOf(port);
            if (index != -1) {
              ports.splice(index, 1);
            }
            dump("mozsocial: port removed, now we have "+ports.length+"\n");
            return;
        }
        if (msg.topic == "social.initialize") {
          apiPort = port;

          if (user_job) clearInterval(user_job);
          if (notifications_job) clearInterval(notifications_job);

          get_user();
          get_notifications();
          user_job = setInterval(get_user, poll_interval);
          notifications_job = setInterval(get_notifications, poll_interval);
        }
        if (msg.topic == "social.user-recommend-prompt") { return;
          return;
          port.postMessage({topic: 'social.user-recommend-prompt-response', data: {
            messages: {
              'shareTooltip': "Share this site",
              'unshareTooltip': "Unshare this site",
              'sharedLabel': "shared",
              'unsharedLabel': "unshared",
              "unshareLabel": "Really unshare?",
              "portraitLabel": "portait",
              "unshareConfirmLabel": "Yes",
              "unshareConfirmAccessKey": "Y",
              "unshareCancelLabel": "Cancel",
              "unshareCancelAccessKey": "C"
            },
            images: {
              'share': baselocation + "share.png",
              'unshare': baselocation + "unshare.png",
            }
          }});
        }
        if (msg.topic == "social.user-recommend") {
        // needed: select audience
//          jQuery.post("/api/statuses/update.json");
//          /api/statuses/update.json?status=test // TODO: CSRF??
        }
        if (msg.topic == "social.user-unrecommend") {
          // TODO
        }
    }
}

// send a message to all provider content
function broadcast(topic, data) {
  for (var i = 0; i < ports.length; i++) {
    if (ports[i] != apiPort) ports[i].postMessage({topic: topic, data: data});
  }
}

function get_user(persistent_login) {
  /*
    - first, try if we get a valid userdata object, i.e. we are already logged in
    - if not, try persistent login, but only once
  */

  if (!apiPort) return;

  var xhr = new XMLHttpRequest();
  xhr.open("POST", baselocation + "../../mozsocial/userdata", true);
  xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  xhr.onload = function(e) {
    try {
      var json = xhr.responseText;
      new_userdata = JSON.parse(json);

      if (!userdata.userName && !persistent_login) { // not logged in, try login cookie
        // TODO: would be nicer if we only try that if there is a login cookie
        get_user(true);
      }
    }
    catch(e) {
      new_userdata = {};
    }

    if (new_userdata.userName != userdata.userName) {
      userdata = new_userdata;
      apiPort.postMessage({topic: "social.user-profile", data: userdata});
    }

    if (!userdata.userName) broadcast("notify", null); // reset sidebar if not logged in
  };

  xhr.onerror = function(e) {
    new_userdata = {};

    if (new_userdata.userName != userdata.userName) {
      userdata = new_userdata;
      apiPort.postMessage({topic: "social.user-profile", data: userdata});
    }

    if (!userdata.userName) broadcast("notify", null); // reset sidebar if not logged in
  };

  var data = '';
  if (persistent_login) {
    data += "get-login-cookie=1";
    data += "&auth-params=login";
    data += "&try-login-cookie=1";
  }
  xhr.send(data);
}

function get_notifications() {
  if (!apiPort) return;
  if (!userdata.userName) return;

  var xhr = new XMLHttpRequest();
  xhr.open("GET", baselocation + "../../ping", true);
  xhr.onerror = function(e) {
    broadcast("notify", null);
  };
  xhr.onload = function(e) {
    // update notifications in sidebar
    broadcast("notify", xhr.responseText);

    // update counters - TODO: home counter?
    var xml = xhr.responseXML;

    var network = xml.getElementsByTagName("net")[0].firstChild.nodeValue;
    network = parseInt(network);
    apiPort.postMessage({topic: 'social.ambient-notification', data: {
      name: "net",
      iconURL: baselocation + "notifications.png",
      counter: network,
      contentPanel: baselocation + "statusPanel.html"
    }});

    var mail = xml.getElementsByTagName("mail")[0].firstChild.nodeValue;
    mail = parseInt(mail);
    apiPort.postMessage({topic: 'social.ambient-notification', data: {
      name: "mail",
      iconURL: baselocation + "messages.png",
      counter: mail,
      contentPanel: baselocation + "statusPanel.html"
    }});

    var intro = xml.getElementsByTagName("intro")[0].firstChild.nodeValue;
    intro = parseInt(intro);
    apiPort.postMessage({topic: 'social.ambient-notification', data: {
      name: "intro",
      iconURL: baselocation + "contacts.png",
      counter: intro,
      contentPanel: baselocation + "statusPanel.html"
    }});

    var notify = xml.getElementsByTagName("notif")[0].getAttribute("count");
    notify = parseInt(notify);
    apiPort.postMessage({topic: 'social.ambient-notification', data: {
      name: "notify",
      iconURL: baselocation + "notify.png",
      counter: notify,
      contentPanel: baselocation + "statusPanel.html"
    }});
  };
  xhr.send();
}
