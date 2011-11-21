/*
	pfSense_MODULE:	shell
*/

jQuery(document).ready(
	function() {
		jQuery("#fbOpen").click(
			function() {
				jQuery("#fbBrowser").fadeIn(750);
				fbBrowse(jQuery("#fbTarget").val());
			}
		);
	}
);

function fbBrowse(path) {
	jQuery("#fileContent").fadeOut();

	if(jQuery("#fbCurrentDir"))
		jQuery("#fbCurrentDir").html("Loading ...");

	jQuery.ajax(
		"/filebrowser/browser.php?path=" + encodeURI(path ? path : "/"),
		{ type: "get", complete: fbComplete }
	);
	
}

function fbComplete(req) {
	jQuery("#fbBrowser").html(req.responseText);

	var actions = {
		fbHome:  function() { fbBrowse("/");                                },
		fbClose: function() { jQuery("#fbBrowser").fadeOut(750); },
		fbDir:   function() { fbBrowse(this.id);                            },
		fbFile:  function() { jQuery("#fbTarget").val(this.id);             }
	}

	for(var type in actions) {
		jQuery("#fbBrowser ." + type).each(
			function() {
				jQuery(this).click(actions[type]);
				jQuery(this).css("cursor","pointer");
			}
		);
	}
}
