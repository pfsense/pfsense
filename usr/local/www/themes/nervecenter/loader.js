<!-- 
var browser     = '';
var version     = '';
var entrance    = '';
var cond        = '';

// BROWSER?
if (browser == '') {
	if (navigator.appName.indexOf('Microsoft') != -1)
		browser = 'IE'
	else if (navigator.appName.indexOf('Netscape') != -1)
		browser = 'Netscape'
	else
		browser = 'IE';
}
if (version == '') {
	version= navigator.appVersion;
	paren = version.indexOf('(');
	whole_version = navigator.appVersion.substring(0,paren-1);
	version = parseInt(whole_version);
}

if (version < 7) {
	document.write('<script type="text/javascript" src="/themes/nervecenter/javascript/ie7/ie7-standard-p.js"></script>');
}

document.write('<script type="text/javascript" src="/themes/nervecenter/javascript/niftyjsCode.js"></script>'); 

// -->
