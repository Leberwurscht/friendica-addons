var userdata = {};

var end = location.href.indexOf("worker.js");
var baselocation = location.href.substr(0, end);

var apiPort;
var ports = [];

onconnect = function(e) {
    var port = e.ports[0];
    ports.push(port);
    port.onmessage = function (msgEvent)
    {
        var msg = msgEvent.data;
        if (msg.topic == "social.port-closing") {
            if (port == apiPort) {
                apiPort.close();
                apiPort = null;
            }
            return;
        }
        if (msg.topic == "social.initialize") {
            apiPort = port;
        }
        if (msg.topic == "social.user-recommend-prompt") { return;
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

function get_user() {
  if (!apiPort) return;

  var xhr = new XMLHttpRequest();
  xhr.open("GET", baselocation + "../../mozsocial/userdata", true);
  xhr.onload = function(e) {
    try {
      var json = xhr.responseText;
      userdata = JSON.parse(json);
    }
    catch(e) {
      userdata = {};
    }

    apiPort.postMessage({topic: "social.user-profile", data: userdata});
  };
  xhr.onerror = function(e) {
    userdata = {};
    apiPort.postMessage({topic: "social.user-profile", data: userdata});
  };

  xhr.send();
}

function get_notifications() {
  if (!apiPort) return;

  var xhr = new XMLHttpRequest();
  xhr.open("GET", baselocation + "../../ping", true);
  xhr.onerror = function(e) {
    broadcast("notify", null);
  };
  xhr.onload = function(e) {
    if (userdata.userName) {
      // if logged in, update notifications in sidebar
      broadcast("notify", xhr.responseText);
    }
    else {
      // if not logged in, reset sidebar
      broadcast("notify", null);
      return;
    }

    var xml = xhr.responseXML;

    // TODO: home counter?

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

var poll_interval = 40*1000;
//poll_interval = 5*1000; // for debugging

setInterval(get_notifications, poll_interval);
setInterval(get_user, poll_interval);
