$(document).ready(
	function() {
		$("#fbOpen").click(
			function() {
				$(".alert").remove();
				$("#fbBrowser").fadeIn(750);
				fbBrowse($("#fbTarget").val());
			}
		);
	}
);

function fbBrowse(path) {
	$("#fileContent").fadeOut();

	if ($("#fbCurrentDir")) {
		$("#fbCurrentDir").html("Loading ...");
	}

	$.ajax(
		"/vendor/filebrowser/browser.php?path=" + encodeURI(path ? path : "/"),
		{ type: "get", complete: fbComplete }
	);

}

function fbComplete(req) {
	$("#fbBrowser").html(req.responseText);

	var actions = {
		fbHome:  function() { fbBrowse("/");                                },
		fbClose: function() { $("#fbBrowser").fadeOut(750); },
		fbDir:   function() { fbBrowse(this.id);                            },
		fbFile:  function() { $("#fbTarget").val(this.id);             }
	}

	for (var type in actions) {
		$("#fbBrowser ." + type).each(
			function() {
				$(this).click(actions[type]);
				$(this).css("cursor","pointer");
			}
		);
	}
}
