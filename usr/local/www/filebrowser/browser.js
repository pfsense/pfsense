/*
	pfSense_MODULE:	shell
*/

Event.observe(
	window, "load",
	function() {
		Event.observe(
			"fbOpen", "click",
			function() {
				Effect.Appear("fbBrowser", { duration: 0.75 });
				fbBrowse($("fbTarget").value);
			}
		);
	}
);

function fbBrowse(path) {
	new Effect.Fade("fileContent");

	if($("fbCurrentDir"))
		$("fbCurrentDir").innerHTML = "Loading ...";

	new Ajax.Request(
		"/filebrowser/browser.php?path=" + encodeURI(path ? path : "/"),
		{ method: "get", onComplete: fbComplete }
	);
	
}

function fbComplete(req) {
	$("fbBrowser").innerHTML = req.responseText;

	var actions = {
		fbHome:  function() { fbBrowse("/");                                },
		fbClose: function() { Effect.Fade("fbBrowser", { duration: 0.75 }); },
		fbDir:   function() { fbBrowse(this.id);                            },
		fbFile:  function() { $("fbTarget").value = this.id;                }
	}

	for(var type in actions) {
		$A(Element.getElementsByClassName("fbBrowser", type)).each(
			function(element) {
				Event.observe(element, "click", actions[type]);
				element.style.cursor = "pointer";
			}
		);
	}
}
