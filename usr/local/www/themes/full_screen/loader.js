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
	document.write('<script type="text/javascript" src="/themes/pfsense_ng/javascript/ie7/ie7-standard-p.js"></scr'+'ipt>');
}

document.write('<script type="text/javascript" src="/themes/pfsense_ng/javascript/niftyjsCode.js"></scr'+'ipt>'); 

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
        
        // connect new column with other columns 
        jQuery('.ui-sortable').sortable({connectWith: '.ui-sortable', dropOnEmpty: true, handle: '.widgetheader', change: showSave});	
    }
   
    // function to insert a new column we can place content into (from saved state)
    function createColumn(newNum){
        
        if(newNum > noCols)
        	noCols = newNum;
        
        document.write("</div><div id=\"col" + newNum + "\" style=\"float:left; padding-bottom:40px\" class=\"ui-sortable\">");
        
        correctWidgetDisplay(noCols);
    }
   
/////////////////end widget code/////////////////////////

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
    jQuery('form').append( "<br/><br/><div><div id =\"addCol\" style=float:left><img src=\"./themes/full_screen/images/icons/icon_plus.gif\"/>&nbsp;Add column&nbsp;</div><div id =\"delCol\" style=float:left><img src=\"./themes/full_screen/images/icons/icon_x.gif\"/>&nbsp;Delete column</div><br/><br/></div> ");	
  
    // on click add a new column and change column widths
    jQuery('#addCol').click(function(){
        console.log("added column");
	var colAfter = noCols;
        noCols++;
        var percent = ( 100 / noCols ) - 0.1;
        var percentStr = percent.toString() + '%';
        
        // insert new column
        jQuery('#col' + (colAfter).toString() ).after("<div id=\"col" + noCols + "\" style=\"float: left; padding-bottom: 40px\" class=\"ui-sortable\"> </div>");
        // set all column widths
        jQuery('.ui-sortable').width(percentStr);
        // connect new column with other columns 
        jQuery('.ui-sortable').sortable({connectWith: '.ui-sortable', dropOnEmpty: true, handle: '.widgetheader', change: showSave});		  
    });
	
    // on click delete a columns and change column widths
    jQuery('#delCol').click(function(){
        if( noCols >= 3 ){
            var colToDel = noCols;
            noCols -= 1;
            var percent = ( 100 / noCols ) - 0.1;
            var percentStr = percent.toString() + '%';
            
            // set all column widths
            jQuery('.ui-sortable').width(percentStr);
            
            // get column contents before deletion
            var colContent = jQuery('#col' + colToDel ).html();
            // remove column
            jQuery('#col' + colToDel ).remove();
            // append deleted columns content to preceeding column
            jQuery(colContent).appendTo('#col' + noCols );
            
            
	}
    });
});
//]]>

