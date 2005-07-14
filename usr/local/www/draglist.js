// ----------------------------------------------------------------------------
// (c) Copyright, DTLink, LLC 1997-2005
//     http://www.dtlink.com
//
// DragList - Drag and Drop Ordered Lists
//
// Javascript Support file for formVista <draglist> fvml tag.
//
// For more information please see:
//
//    http://www.formvista.com/otherprojects/draglist.html
//
// For questions or comments please contact us at:
//
//     http://www.formvista.com/contact.html
//
// LICENSE: This file is governed by the new BSD license. For more information
// please see the LICENSE.txt file accompanying this package. 
//
// REVISION HISTORY:
//
// 2004-11-12 YmL:
//	.	initial revision.
//
// 2005-05-28 YmL:
//	.	pulled out of formVista, relicensed and packaged as a standalone implementation.
//
// 2005-06-02 mtmosier:
//	.	added horizontal dragging support.
//
// ------------------------

/**
* constructor for dragList class
*/

function fv_dragList( name )
	{

	// name of this dragList. Must match the id of the root DIV tag.

	this.dragListRootId = name;

	// array of item offsets

	this.offsetsX = new Array();
	this.offsetsY = new Array();

	}

// ----------------------------------------------

/**
* setup the draglist prior to use
*
* @param string orientation defaults to vert. if set to "horz" renders horizontally.
* @param string itemTagName. if null defaults to "div". Can be "span".
*/

fv_dragList.prototype.setup = function( orientation, itemTagName )
	{

	var horizontal;

	if ( orientation == "horz" )
		horizontal = true;
	else
		horizontal = false;

	this.listRoot = document.getElementById( this.dragListRootId );
	this.listItems = this.getListItems( itemTagName );

	for (var i = 0; i < this.listItems.length; i++) 
		{

		if ( this.listItems[i] == undefined )
			continue;

		if ( horizontal )
			{
			Drag.init(this.listItems[i], null, null, null, 0, 0);
			}
		else
			{
			Drag.init(this.listItems[i], null, 0, 0, null, null);
			}

		// ---------------------------------------------------
		// on drag method

		this.listItems[i].onDrag = function( x, y, thisElem ) 
			{

			x = thisElem.offsetLeft;
			y = thisElem.offsetTop;

			// this is a callback from the dom-drag code. From within this
			// function "this" does not refer to the fv_draglist function.

			draglist = getDragList( thisElem );

			draglist.recalcOffsets( itemTagName );

			var pos = draglist.getCurrentOffset( thisElem, itemTagName );

			//var listItems = this.getListItems( itemTagName );

			// if bottom edge is below top of lower item.

			var testMoveUp;
			var testMoveDown;
			if ( horizontal )
				{
				testMoveUp = (x + draglist.getDivWidth(thisElem) > draglist.offsetsX[pos + 1] + draglist.getDivWidth( draglist.listItems[pos + 1] ));
				testMoveDown = x < draglist.offsetsX[pos - 1];
				}
			else
				{
				testMoveUp = (y + draglist.getDivHeight(thisElem) > draglist.offsetsY[pos + 1] + draglist.getDivHeight( draglist.listItems[pos + 1] ));
				testMoveDown = y < draglist.offsetsY[pos - 1];
				}

			if (( pos != draglist.listItems.length - 1) && testMoveUp )
				{ 
				draglist.listRoot.removeChild(thisElem);

				if ( pos + 1 == draglist.listItems.length )
					{
					draglist.listRoot.appendChild( thisElem );
					}
				else
					{
					draglist.listRoot.insertBefore(thisElem, draglist.listItems[pos+1]);
					}

				thisElem.style["top"] = "0px";
				thisElem.style["left"] = "0px";
				}
			else if ( pos != 0 && testMoveDown ) 
				{ 
				draglist.listRoot.removeChild(thisElem);
				draglist.listRoot.insertBefore(thisElem, draglist.listItems[pos-1]);
				thisElem.style["top"] = "0px";
				thisElem.style["left"] = "0px";
				}

			};

		this.listItems[i].onDragEnd = function(x,y,thisElem) 
			{
			thisElem.style["top"] = "0px";
			thisElem.style["left"] = "0px";
			}

		}	// end of for loop.

	this.recalcOffsets( itemTagName );

	}	// end of setup.

// ----------------------------------------------


/**
* update the order value fields and submit the form.
*/

fv_dragList.prototype.do_submit = function( formName, dragListRootId )
	{

	var listOrderItems = this.listRoot.getElementsByTagName("input");

	for (var i = 0; i < listOrderItems.length; i++) 
		{
		listOrderItems[i].value = i;
		}

	expr = "document." + formName + ".submit()";

	eval( expr );
	}

// ----------------------------------------------
// "Private" methods.
// ----------------------------------------------

fv_dragList.prototype.recalcOffsets = function( itemTagName ) 
	{
	var listItems = this.getListItems( itemTagName );

	for (var i = 0; i < listItems.length; i++) 
		{
		this.offsetsX[i] = listItems[i].offsetLeft;
		this.offsetsY[i] = listItems[i].offsetTop;
		}
	}

fv_dragList.prototype.getCurrentOffset = function(elem, itemTagName) 
	{ 
	var listItems = this.getListItems( itemTagName );

	for (var i = 0; i < listItems.length; i++) 
		{
		if (listItems[i] == elem) 
			{ 
			return i;
			}
		}
	}

fv_dragList.prototype.getDivWidth = function(elem) 								  
	{

	if (( elem == undefined) || ( elem.offsetWidth == undefined ))
		return( 0 );

	value = elem.offsetWidth;
	if (isNaN(value))
		{
		value = 0;
		}

	return( value );
	}

fv_dragList.prototype.getDivHeight = function(elem) 
	{

	if (( elem == undefined) || ( elem.offsetHeight == undefined ))
		return( 0 );

	value = elem.offsetHeight;
	if (isNaN(value))
		{
		value = 25;
		}

	return( value );
	}

/**
* return list of draggable items
*/

fv_dragList.prototype.getListItems = function( itemTagName )
	{
	if ( itemTagName == undefined )
		{
		itemTagName = "div";
		}

	var listItems = this.listRoot.getElementsByTagName( itemTagName );

	return( listItems );
	}

// end of draglist class definition.

// -------------------------------------

/**
* add a new dragList to the list of draglists on this page
*
* This implementatoin supports multiple managed draglists on
* a single page. The index is contained in a global dragListIndex
* array that must be declared in the page.
*/

function addDragList( draglist )
	{
	dragListIndex[ draglist.dragListRootId ] = draglist;
	}

// -------------------------------------------------------

/**
* given a draggable div element, return the draglist it belongs to
*
* @see fv_draglist.prototype.setup
* @todo this should probably be a method inside the draglist class.
*/

function getDragList( elem )
	{

	// given a list item return the drag list it belongs to.

	var draglistContainer = elem.parentNode;

	var draglist = dragListIndex[ draglistContainer.id ];

	return( draglist );
	}

// END
