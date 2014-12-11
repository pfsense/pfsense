//<![CDATA[
var browser     = '';
var version     = '';
var entrance    = '';
var cond        = '';

// BROWSER?
if (browser == '')
{
	if (navigator.appName.indexOf('Microsoft') != -1)
		browser = 'IE';
	else if (navigator.appName.indexOf('Netscape') != -1)
		browser = 'Netscape';
	else
		browser = 'IE';
}
if (version == '')
{
	version= navigator.appVersion;
	paren = version.indexOf('(');
	whole_version = navigator.appVersion.substring(0,paren-1);
	version = parseInt(whole_version);
}

if (browser == 'IE' && version < 7)
	document.write('<script type="text/javascript" src="/themes/pfsense_ng/javascript/ie7/ie7-standard-p.js"></script>');

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
var id;
var noColsOnLoad = noCols; // holds a reference to the number of displayed columns on load
var existing =[]; // array to hold each columns contents
var specifiedColWidth = 350; // width of columns for resizing

///////////////////////////////////////////
// jQuery Widget functions
///////////////////////////////////////////

// function to connect all columns to each other to allow jQuery interaction (drag and droppable)
function connectColumns()
{
	jQuery('.ui-sortable').sortable({connectWith: '.ui-sortable', dropOnEmpty: true, handle: '.widgetheader', change: showSave});
}

// function to add columns due to a window resize
function resizeAddColumns()
{
	if(noColsOnLoad > noCols) // if a column has previously been deleted
	{
		var maxCols = maxColsToDisplay(); // the maximum we can display
		
		if(noColsOnLoad < maxCols) // if the number of columns on load is less then the maximum number of columns we can display
			maxCols = noColsOnLoad; // change the maximum number of columns as we do not want to display them all
		if( (maxCols - noCols) > 0 ) // if we need to add more columns
		{
			replaceColumn();
			
			for(var i=noCols; i<maxCols; i++)
			{
				var addCols = i +1;
				jQuery('#col' + (i).toString() ).after("<div id=\"col" + addCols + "\" style=\"float: left; padding-bottom: 40px\" class=\"ui-sortable\"> </div>"); 
				jQuery(existing[i]).appendTo('#col' + addCols ); // append onLoad contents
			}
			noCols = maxCols;
			for(var i=noCols; i<noColsOnLoad ; i++)
			{
				jQuery(existing[i]).appendTo('#col' + maxCols ); // append widgets from stored array to columns
			}
		correctWidgetDisplay(noCols);
		connectColumns();
		showSave();
		}
	}
}


// function to remove columns due to a window resize 
function resizeRmColumns()
{
	if( noCols > 1 ) // keep at least 1 column displayed at all times
	{
		var maxCols = maxColsToDisplay();
		var noColsToDel = noCols - maxCols;
		
		if(noColsToDel>0) // if columns need deleteing
		{
			for(var i=(noCols-noColsToDel); i<noColsOnLoad; i++)
			{
				jQuery(existing[i]).appendTo('#col' + maxCols ); // append widgets from stored array to columns
			}
			for(var i=0; i<noColsToDel; i++ )
			{
				var del = noCols -i;
				jQuery('#col' + del ).remove(); // remove columns
			}
		noCols = maxCols;
		correctWidgetDisplay(noCols);
		showSave();
		}
	}
};


// functions to removes the highest value column current displayed and replaces it with the same column number on load ie before any resizing took place
function replaceColumn()
{
	var tmpReplace = noCols -1;
	jQuery('#col' + noCols ).remove();

	// prepend column1 as we can't add it AFTER a column as none will exist           
	if(tmpReplace==0)
		jQuery("#niftyOutter").prepend("<div id=\"col1\" style=\"float: left; padding-bottom: 40px\" class=\"ui-sortable\"> </div>");
	else
		jQuery('#col' + (tmpReplace).toString() ).after("<div id=\"col" + noCols + "\" style=\"float: left; padding-bottom: 40px\" class=\"ui-sortable\"> </div>");
	jQuery(existing[tmpReplace]).appendTo('#col' +  noCols);
}


// function to calculate & return the maximum number of columns we can display
function maxColsToDisplay()
{
	var niftyWidth = jQuery('#niftyOutter.fakeClass').width();
	return Math.round(niftyWidth / specifiedColWidth);	    
}

// function to amend the widget width  
function correctWidgetDisplay(noCols)
{
	var percent = ( 100 / noCols ) - 0.1;
	var percentStr = percent.toString() + '%';

	// set all column widths
	jQuery('.ui-sortable').width(percentStr);
}

// function to insert a new column we can place content into (from saved state)
function printColumn(newNum)
{
	if(newNum > noCols)
        {
		noCols = newNum;
		noColsOnLoad = noCols;
	}
	
	document.write("</div><div id=\"col" + newNum + "\" style=\"float:left; padding-bottom:40px\" class=\"ui-sortable\">");
	correctWidgetDisplay(noCols);
	connectColumns();
}

// function to create the columns
function createColumn(colPos)
{
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

// function which is called when the broswer window is resized
jQuery( window ).resize(function()
{  
    // stop resize firing twice: http://stackoverflow.com/questions/4298612/jquery-how-to-call-resize-event-only-once-its-finished-resizing
    clearTimeout(id);
    id = setTimeout(finishedResizing, 500);
});

// function called after the browser has finished resizing
function finishedResizing()
{
	var colWidth = jQuery("#col1").width();
	if( colWidth < specifiedColWidth ) // Columns width is too small to display all the columns properly so we delete some columns and resize the remaining columns    
		resizeRmColumns(); // Check if we can delete any columns
	else if( colWidth > specifiedColWidth ) // Columns width COULD display more columns properly    
		resizeAddColumns(); // Check if we can add any columns
}

///////////////// end widget code part 1 /////////////////////////

// jQuery function to define dropdown menu size
jQuery(document).ready(function ()
{
    var hwindow  = '';
    hwindow = (jQuery(window).height()-35);
    // Force the size dropdown menu 
    jQuery('#navigation ul li ul').css('max-height', hwindow);
    
    ///////////////////////////////////////////
    // jQuery code for columns / widgets part 2    
    ///////////////////////////////////////////
    
    // insert add/delete column buttons
    jQuery('<br /><br /><div id=\"columnModifier\"><div style=\"float:left\"><div id =\"addCol\" style=\"float:left\"><img src=\"./themes/pfsense_ng_fs/images/icons/icon_plus.gif\" style=\"cursor:pointer\" alt=\"Click here to add a column\"/></div>&nbsp;Add column&nbsp;</div><div style=\"float:left\"><div id =\"delCol\" style=\"float:left\"><img src=\"./themes/pfsense_ng_fs/images/icons/icon_x.gif\" style=\"cursor:pointer\" alt=\"Click here to delete a column\"/></div>&nbsp;Delete column</div><div id=\"columnWarningText\" style=\"float:left; margin-left:5em\"></div><br /><br />').insertBefore('#niftyOutter.fakeClass');
    
    if ( jQuery('#columnModifier').length > 0 ) // only perform resizing on the dashboard page
    {
        // correct the css for column 2
        jQuery('#col2').css("float","left");

        // Make a copy of the current state of columns on page load
        for ( var i = 1; i <= noCols; i = i + 1 )
        {
	    var contents = jQuery('#col' + i ).html();
	    existing.push( contents );
        }  
    
        finishedResizing(); // on page load correct display of columns to fit
    }
    
    // on click add a new column and change column widths
    jQuery('#addCol').click(function()
    {
	var maxCols = maxColsToDisplay();
	if( (noCols < maxCols) && (noCols < 10) )
        {
		var colAfter = noCols;
		noCols++;

		// insert new column
		jQuery('#col' + (colAfter).toString() ).after("<div id=\"col" + noCols + "\" style=\"float: left; padding-bottom: 40px\" class=\"ui-sortable\"> </div>");

		correctWidgetDisplay(noCols);
		connectColumns();
	}
	else
		jQuery('#columnWarningText').html('<b>Maximum number of columns reached for the current window size</b>').show().delay(1000).fadeOut(1000);
    });

    // on click delete a columns and change column widths
    jQuery('#delCol').click(function()
    {
	if( noCols > 1 )
        {
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
	else
		jQuery('#columnWarningText').html('<b>Minimum number of columns reached for the current window size</b>').show().delay(1000).fadeOut(1000);
    });
});
//]]>
