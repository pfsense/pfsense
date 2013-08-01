//<![CDATA[
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

if (browser == 'IE' && version < 7) {
	document.write('<script type="text/javascript" src="/themes/pfsense_ng/javascript/ie7/ie7-standard-p.js"></script>');
}

document.write('<script type="text/javascript" src="/themes/pfsense_ng/javascript/niftyjsCode.js"></script>'); 

///////////////////////////////////////////
// jQuery code for columns / widgets part 1
///////////////////////////////////////////

var noCols = 2;
var printed3 = false;
var printed4 = false;
var printed5 = false;
var printed6 = false;
var printed7 = false;
var printed8 = false;
var printed9 = false;
var printed10 = false;

function correctWidgetDisplay(noCols){
	var percent = ( 100 / noCols ) - 0.1;
	var percentStr = percent.toString() + '%';

	// set all column widths
	jQuery('.ui-sortable').width(percentStr);
}

// function to insert a new column we can place content into (from saved state)
function printColumn(newNum){
	if(newNum > noCols)
		noCols = newNum;

	document.write("</div><div id=\"col" + newNum + "\" style=\"float:left; padding-bottom:40px\" class=\"ui-sortable\">");
	correctWidgetDisplay(noCols);

	// connect new column with other columns 
	jQuery('.ui-sortable').sortable({connectWith: '.ui-sortable', dropOnEmpty: true, handle: '.widgetheader', change: showSave});
}

function createColumn(colPos){
	if (colpos == "col3" && printed3 == false){
		printColumn(3);
		printed3=true;
	}
	else if (colpos == "col4" && printed4 == false){
		printColumn(4);
		printed4=true;
	}
	else if (colpos == "col5" && printed5 == false){
		printColumn(5);
		printed5=true;
	}
	else if (colpos == "col6" && printed6 == false){
		printColumn(6);
		printed6=true;
	}
	else if (colpos == "col7" && printed7 == false){
		printColumn(7);
		printed7=true;
	}
	else if (colpos == "col8" && printed8 == false){
		printColumn(8);
		printed8=true;
	}
	else if (colpos == "col9" && printed9 == false){
		printColumn(9);
		printed9=true;
	}
	else if (colpos == "col10" && printed10 == false){
		printColumn(10);
		printed10=true;
	}
}
///////////////// end widget code part 1 /////////////////////////

// jQuery function to define dropdown menu size
jQuery(document).ready(function () {
    var hwindow  = '';
    hwindow = (jQuery(window).height()-35);
    // Force the size dropdown menu 
    jQuery('#navigation ul li ul').css('max-height', hwindow);
    
    ///////////////////////////////////////////
    // jQuery code for columns / widgets part 2    
    ///////////////////////////////////////////

    jQuery('#col2').css("float","left");

    // insert add/delete column buttons
    jQuery('<br/><br/><div id=\"columnModifier\"><div style=\"float:left\"><div id =\"addCol\" style=\"float:left\"><img src=\"./themes/pfsense_ng_fs/images/icons/icon_plus.gif\" style=\"cursor:pointer\" alt=\"Click here to add a column\"/></div>&nbsp;Add column&nbsp;</div><div style=\"float:left\"><div id =\"delCol\" style=\"float:left\"><img src=\"./themes/pfsense_ng_fs/images/icons/icon_x.gif\" style=\"cursor:pointer\" alt=\"Click here to delete a column\"/></div>&nbsp;Delete column</div><div id=\"columnWarningText\" style=\"float:left; margin-left:5em\"></div><br/><br/>').insertBefore('#niftyOutter.fakeClass');
	
    // on click add a new column and change column widths
    jQuery('#addCol').click(function(){
	if( noCols < 10 ){
		var colAfter = noCols;
		noCols++;

		// insert new column
		jQuery('#col' + (colAfter).toString() ).after("<div id=\"col" + noCols + "\" style=\"float: left; padding-bottom: 40px\" class=\"ui-sortable\"> </div>");

		correctWidgetDisplay(noCols);
		
		// connect new column with other columns 
		jQuery('.ui-sortable').sortable({connectWith: '.ui-sortable', dropOnEmpty: true, handle: '.widgetheader', change: showSave});
	}
	else{
		jQuery('#columnWarningText').html('<b>Maximum number of columns reached</b>').show().delay(1000).fadeOut(1000);
	}
    });

    // on click delete a columns and change column widths
    jQuery('#delCol').click(function(){
	if( noCols > 2 ){
		var colToDel = noCols;
		noCols -= 1;

		correctWidgetDisplay(noCols);

		// get column contents before deletion
		var colContent = jQuery('#col' + colToDel ).html();

		// remove column
		jQuery('#col' + colToDel ).remove();

		// append deleted columns content to preceeding column
		jQuery(colContent).appendTo('#col' + noCols );
			
		showSave();           
	}
	else{
		jQuery('#columnWarningText').html('<b>Minimum number of columns reached</b>').show().delay(1000).fadeOut(1000);
	}
    });
});
//]]>
