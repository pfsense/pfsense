/** $Id: domTT.js 2324 2006-06-12 07:06:39Z dallen $ */
// {{{ license

/*
 * Copyright 2002-2005 Dan Allen, Mojavelinux.com (dan.allen@mojavelinux.com)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// }}}
// {{{ intro

/**
 * Title: DOM Tooltip Library
 * Version: 0.7.3
 *
 * Summary:
 * Allows developers to add custom tooltips to the webpages.  Tooltips are
 * generated using the domTT_activate() function and customized by setting
 * a handful of options.
 *
 * Maintainer: Dan Allen <dan.allen@mojavelinux.com>
 * Contributors:
 * 		Josh Gross <josh@jportalhome.com>
 *		Jason Rust <jason@rustyparts.com>
 *
 * License: Apache 2.0
 * However, if you use this library, you earn the position of official bug
 * reporter :) Please post questions or problem reports to the newsgroup:
 *
 *   http://groups-beta.google.com/group/dom-tooltip
 *
 * If you are doing this for commercial work, perhaps you could send me a few
 * Starbucks Coffee gift dollars or PayPal bucks to encourage future
 * developement (NOT REQUIRED).  E-mail me for my snail mail address.

 *
 * Homepage: http://www.mojavelinux.com/projects/domtooltip/
 *
 * Newsgroup: http://groups-beta.google.com/group/dom-tooltip
 *
 * Freshmeat Project: http://freshmeat.net/projects/domtt/?topic_id=92
 *
 * Updated: 2005/07/16
 *
 * Supported Browsers:
 * Mozilla (Gecko), IE 5.5+, IE on Mac, Safari, Konqueror, Opera 7
 *
 * Usage:
 * Please see the HOWTO documentation.
**/

// }}}
// {{{ settings (editable)

// IE mouse events seem to be off by 2 pixels
var domTT_offsetX = (domLib_isIE ? -2 : 0);
var domTT_offsetY = (domLib_isIE ? 4 : 2);
var domTT_direction = 'southeast';
var domTT_mouseHeight = domLib_isIE ? 13 : 19;
var domTT_closeLink = 'X';
var domTT_closeAction = 'hide';
var domTT_activateDelay = 500;
var domTT_maxWidth = false;
var domTT_styleClass = 'domTT';
var domTT_fade = 'neither';
var domTT_lifetime = 0;
var domTT_grid = 0;
var domTT_trailDelay = 200;
var domTT_useGlobalMousePosition = true;
var domTT_postponeActivation = false;
var domTT_tooltipIdPrefix = '[domTT]';
var domTT_screenEdgeDetection = true;
var domTT_screenEdgePadding = 4;
var domTT_oneOnly = false;
var domTT_cloneNodes = false;
var domTT_detectCollisions = true;
var domTT_bannedTags = ['OPTION'];
var domTT_draggable = false;
if (typeof(domTT_dragEnabled) == 'undefined')
{
	domTT_dragEnabled = false;
}

// }}}
// {{{ globals (DO NOT EDIT)

var domTT_predefined = new Hash();
// tooltips are keyed on both the tip id and the owner id,
// since events can originate on either object
var domTT_tooltips = new Hash();
var domTT_lastOpened = 0;
var domTT_documentLoaded = false;
var domTT_mousePosition = null;

// }}}
// {{{ document.onmousemove

if (domLib_useLibrary && domTT_useGlobalMousePosition)
{
	document.onmousemove = function(in_event)
	{
		if (typeof(in_event) == 'undefined') { in_event = window.event; }

		domTT_mousePosition = domLib_getEventPosition(in_event);
		if (domTT_dragEnabled && domTT_dragMouseDown)
		{
			domTT_dragUpdate(in_event);
		}
	}
}

// }}}
// {{{ domTT_activate()

function domTT_activate(in_this, in_event)
{
	if (!domLib_useLibrary || (domTT_postponeActivation && !domTT_documentLoaded)) { return false; }

	// make sure in_event is set (for IE, some cases we have to use window.event)
	if (typeof(in_event) == 'undefined') { in_event = window.event;	}

	// don't allow tooltips on banned tags (such as OPTION)
	if (in_event != null) {
		var target = in_event.srcElement ? in_event.srcElement : in_event.target;
		if (target != null && (',' + domTT_bannedTags.join(',') + ',').indexOf(',' + target.tagName + ',') != -1)
		{
			return false;
		}
	}

	var owner = document.body;
	// we have an active event so get the owner
	if (in_event != null && in_event.type.match(/key|mouse|click|contextmenu/i))
	{
		// make sure we have nothing higher than the body element
		if (in_this.nodeType && in_this.nodeType != document.DOCUMENT_NODE)
		{
			owner = in_this;
		}
	}
	// non active event (make sure we were passed a string id)
	else
	{
		if (typeof(in_this) != 'object' && !(owner = domTT_tooltips.get(in_this)))
		{
			// NOTE: two steps to avoid "flashing" in gecko
			var embryo = document.createElement('div');
			owner = document.body.appendChild(embryo);
			owner.style.display = 'none';
			owner.id = in_this;
		}
	}

	// make sure the owner has a unique id
	if (!owner.id)
	{
		owner.id = '__autoId' + domLib_autoId++;
	}

	// see if we should only be opening one tip at a time
	// NOTE: this is not "perfect" yet since it really steps on any other
	// tip working on fade out or delayed close, but it get's the job done
	if (domTT_oneOnly && domTT_lastOpened)
	{
		domTT_deactivate(domTT_lastOpened);
	}

	domTT_lastOpened = owner.id;

	var tooltip = domTT_tooltips.get(owner.id);
	if (tooltip)
	{
		if (tooltip.get('eventType') != in_event.type)
		{
			if (tooltip.get('type') == 'greasy')
			{
				tooltip.set('closeAction', 'destroy');
				domTT_deactivate(owner.id);
			}
			else if (tooltip.get('status') != 'inactive')
			{
				return owner.id;
			}
		}
		else
		{
			if (tooltip.get('status') == 'inactive')
			{
				tooltip.set('status', 'pending');
				tooltip.set('activateTimeout', domLib_setTimeout(domTT_runShow, tooltip.get('delay'), [owner.id, in_event]));

				return owner.id;
			}
			// either pending or active, let it be
			else
			{
				return owner.id;
			}
		}
	}

	// setup the default options hash
	var options = new Hash(
		'caption',		'',
		'content',		'',
		'clearMouse',	true,
		'closeAction',	domTT_closeAction,
		'closeLink',	domTT_closeLink,
		'delay',		domTT_activateDelay,
		'direction',	domTT_direction,
		'draggable',	domTT_draggable,
		'fade',			domTT_fade,
		'fadeMax',		100,
		'grid',			domTT_grid,
		'id',			domTT_tooltipIdPrefix + owner.id,
		'inframe',		false,
		'lifetime',		domTT_lifetime,
		'offsetX',		domTT_offsetX,
		'offsetY',		domTT_offsetY,
		'parent',		document.body,
		'position',		'absolute',
		'styleClass',	domTT_styleClass,
		'type',			'greasy',
		'trail',		false,
		'lazy',			false
	);

	// load in the options from the function call
	for (var i = 2; i < arguments.length; i += 2)
	{
		// load in predefined
		if (arguments[i] == 'predefined')
		{
			var predefinedOptions = domTT_predefined.get(arguments[i + 1]);
			for (var j in predefinedOptions.elementData)
			{
				options.set(j, predefinedOptions.get(j));
			}
		}
		// set option
		else
		{
			options.set(arguments[i], arguments[i + 1]);
		}
	}

	options.set('eventType', in_event != null ? in_event.type : null);

	// immediately set the status text if provided
	if (options.has('statusText'))
	{
		try { window.status = options.get('statusText'); } catch(e) {}
	}

	// if we didn't give content...assume we just wanted to change the status and return
	if (!options.has('content') || options.get('content') == '' || options.get('content') == null)
	{
		if (typeof(owner.onmouseout) != 'function')
		{
			owner.onmouseout = function(in_event) { domTT_mouseout(this, in_event); };
		}

		return owner.id;
	}

	options.set('owner', owner);

	domTT_create(options);

	// determine the show delay
	options.set('delay', (in_event != null && in_event.type.match(/click|mousedown|contextmenu/i)) ? 0 : parseInt(options.get('delay')));
	domTT_tooltips.set(owner.id, options);
	domTT_tooltips.set(options.get('id'), options);
	options.set('status', 'pending');
	options.set('activateTimeout', domLib_setTimeout(domTT_runShow, options.get('delay'), [owner.id, in_event]));

	return owner.id;
}

// }}}
// {{{ domTT_create()

function domTT_create(in_options)
{
	var tipOwner = in_options.get('owner');
	var parentObj = in_options.get('parent');
	var parentDoc = parentObj.ownerDocument || parentObj.document;

	// create the tooltip and hide it
	// NOTE: two steps to avoid "flashing" in gecko
	var embryo = parentDoc.createElement('div');
	var tipObj = parentObj.appendChild(embryo);
	tipObj.style.position = 'absolute';
	tipObj.style.left = '0px';
	tipObj.style.top = '0px';
	tipObj.style.visibility = 'hidden';
	tipObj.id = in_options.get('id');
	tipObj.className = in_options.get('styleClass');

	var contentBlock;
	var tableLayout = false;

	if (in_options.get('caption') || (in_options.get('type') == 'sticky' && in_options.get('caption') !== false))
	{
		tableLayout = true;
		// layout the tip with a hidden formatting table
		var tipLayoutTable = tipObj.appendChild(parentDoc.createElement('table'));
		tipLayoutTable.style.borderCollapse = 'collapse';
		if (domLib_isKHTML)
		{
			tipLayoutTable.cellSpacing = 0;
		}

		var tipLayoutTbody = tipLayoutTable.appendChild(parentDoc.createElement('tbody'));

		var numCaptionCells = 0;
		var captionRow = tipLayoutTbody.appendChild(parentDoc.createElement('tr'));
		var captionCell = captionRow.appendChild(parentDoc.createElement('td'));
		captionCell.style.padding = '0px';
		var caption = captionCell.appendChild(parentDoc.createElement('div'));
		caption.className = 'caption';
		if (domLib_isIE50)
		{
			caption.style.height = '100%';
		}

		if (in_options.get('caption').nodeType)
		{
			caption.appendChild(domTT_cloneNodes ? in_options.get('caption').cloneNode(1) : in_options.get('caption'));
		}
		else
		{
			caption.innerHTML = in_options.get('caption');
		}

		if (in_options.get('type') == 'sticky')
		{
			var numCaptionCells = 2;
			var closeLinkCell = captionRow.appendChild(parentDoc.createElement('td'));
			closeLinkCell.style.padding = '0px';
			var closeLink = closeLinkCell.appendChild(parentDoc.createElement('div'));
			closeLink.className = 'caption';
			if (domLib_isIE50)
			{
				closeLink.style.height = '100%';
			}

			closeLink.style.textAlign = 'right';
			closeLink.style.cursor = domLib_stylePointer;
			// merge the styles of the two cells
			closeLink.style.borderLeftWidth = caption.style.borderRightWidth = '0px';
			closeLink.style.paddingLeft = caption.style.paddingRight = '0px';
			closeLink.style.marginLeft = caption.style.marginRight = '0px';
			if (in_options.get('closeLink').nodeType)
			{
				closeLink.appendChild(in_options.get('closeLink').cloneNode(1));
			}
			else
			{
				closeLink.innerHTML = in_options.get('closeLink');
			}

			closeLink.onclick = function()
			{
				domTT_deactivate(tipOwner.id);
			};
			closeLink.onmousedown = function(in_event)
			{
				if (typeof(in_event) == 'undefined') { in_event = window.event; }
				in_event.cancelBubble = true;
			};
			// MacIE has to have a newline at the end and must be made with createTextNode()
			if (domLib_isMacIE)
			{
				closeLinkCell.appendChild(parentDoc.createTextNode("\n"));
			}
		}

		// MacIE has to have a newline at the end and must be made with createTextNode()
		if (domLib_isMacIE)
		{
			captionCell.appendChild(parentDoc.createTextNode("\n"));
		}

		var contentRow = tipLayoutTbody.appendChild(parentDoc.createElement('tr'));
		var contentCell = contentRow.appendChild(parentDoc.createElement('td'));
		contentCell.style.padding = '0px';
		if (numCaptionCells)
		{
			if (domLib_isIE || domLib_isOpera)
			{
				contentCell.colSpan = numCaptionCells;
			}
			else
			{
				contentCell.setAttribute('colspan', numCaptionCells);
			}
		}

		contentBlock = contentCell.appendChild(parentDoc.createElement('div'));
		if (domLib_isIE50)
		{
			contentBlock.style.height = '100%';
		}
	}
	else
	{
		contentBlock = tipObj.appendChild(parentDoc.createElement('div'));
	}

	contentBlock.className = 'contents';

	var content = in_options.get('content');
	// allow content has a function to return the actual content
	if (typeof(content) == 'function') {
		content = content(in_options.get('id'));
	}

	if (content != null && content.nodeType)
	{
		contentBlock.appendChild(domTT_cloneNodes ? content.cloneNode(1) : content);
	}
	else
	{
		contentBlock.innerHTML = content;
	}

	// adjust the width if specified
	if (in_options.has('width'))
	{
		tipObj.style.width = parseInt(in_options.get('width')) + 'px';
	}

	// check if we are overridding the maxWidth
	// if the browser supports maxWidth, the global setting will be ignored (assume stylesheet)
	var maxWidth = domTT_maxWidth;
	if (in_options.has('maxWidth'))
	{
		if ((maxWidth = in_options.get('maxWidth')) === false)
		{
			tipObj.style.maxWidth = domLib_styleNoMaxWidth;
		}
		else
		{
			maxWidth = parseInt(in_options.get('maxWidth'));
			tipObj.style.maxWidth = maxWidth + 'px';
		}
	}

	// HACK: fix lack of maxWidth in CSS for KHTML and IE
	if (maxWidth !== false && (domLib_isIE || domLib_isKHTML) && tipObj.offsetWidth > maxWidth)
	{
		tipObj.style.width = maxWidth + 'px';
	}

	in_options.set('offsetWidth', tipObj.offsetWidth);
	in_options.set('offsetHeight', tipObj.offsetHeight);

	// konqueror miscalcuates the width of the containing div when using the layout table based on the
	// border size of the containing div
	if (domLib_isKonq && tableLayout && !tipObj.style.width)
	{
		var left = document.defaultView.getComputedStyle(tipObj, '').getPropertyValue('border-left-width');
		var right = document.defaultView.getComputedStyle(tipObj, '').getPropertyValue('border-right-width');
		
		left = left.substring(left.indexOf(':') + 2, left.indexOf(';'));
		right = right.substring(right.indexOf(':') + 2, right.indexOf(';'));
		var correction = 2 * ((left ? parseInt(left) : 0) + (right ? parseInt(right) : 0));
		tipObj.style.width = (tipObj.offsetWidth - correction) + 'px';
	}

	// if a width is not set on an absolutely positioned object, both IE and Opera
	// will attempt to wrap when it spills outside of body...we cannot have that
	if (domLib_isIE || domLib_isOpera)
	{
		if (!tipObj.style.width)
		{
			// HACK: the correction here is for a border
			tipObj.style.width = (tipObj.offsetWidth - 2) + 'px';
		}

		// HACK: the correction here is for a border
		tipObj.style.height = (tipObj.offsetHeight - 2) + 'px';
	}

	// store placement offsets from event position
	var offsetX, offsetY;

	// tooltip floats
	if (in_options.get('position') == 'absolute' && !(in_options.has('x') && in_options.has('y')))
	{
		// determine the offset relative to the pointer
		switch (in_options.get('direction'))
		{
			case 'northeast':
				offsetX = in_options.get('offsetX');
				offsetY = 0 - tipObj.offsetHeight - in_options.get('offsetY');
			break;
			case 'northwest':
				offsetX = 0 - tipObj.offsetWidth - in_options.get('offsetX');
				offsetY = 0 - tipObj.offsetHeight - in_options.get('offsetY');
			break;
			case 'north':
				offsetX = 0 - parseInt(tipObj.offsetWidth/2);
				offsetY = 0 - tipObj.offsetHeight - in_options.get('offsetY');
			break;
			case 'southwest':
				offsetX = 0 - tipObj.offsetWidth - in_options.get('offsetX');
				offsetY = in_options.get('offsetY');
			break;
			case 'southeast':
				offsetX = in_options.get('offsetX');
				offsetY = in_options.get('offsetY');
			break;
			case 'south':
				offsetX = 0 - parseInt(tipObj.offsetWidth/2);
				offsetY = in_options.get('offsetY');
			break;
		}

		// if we are in an iframe, get the offsets of the iframe in the parent document
		if (in_options.get('inframe'))
		{
			var iframeObj = domLib_getIFrameReference(window);
			if (iframeObj)
			{
				var frameOffsets = domLib_getOffsets(iframeObj);
				offsetX += frameOffsets.get('left');
				offsetY += frameOffsets.get('top');
			}
		}
	}
	// tooltip is fixed
	else
	{
		offsetX = 0;
		offsetY = 0;
		in_options.set('trail', false);
	}

	// set the direction-specific offsetX/Y
	in_options.set('offsetX', offsetX);
	in_options.set('offsetY', offsetY);
	if (in_options.get('clearMouse') && in_options.get('direction').indexOf('south') != -1)
	{
		in_options.set('mouseOffset', domTT_mouseHeight);
	}
	else
	{
		in_options.set('mouseOffset', 0);
	}

	if (domLib_canFade && typeof(Fadomatic) == 'function')
	{
		if (in_options.get('fade') != 'neither')
		{
			var fadeHandler = new Fadomatic(tipObj, 10, 0, 0, in_options.get('fadeMax'));
			in_options.set('fadeHandler', fadeHandler);
		}
	}
	else
	{
		in_options.set('fade', 'neither');
	}

	// setup mouse events
	if (in_options.get('trail') && typeof(tipOwner.onmousemove) != 'function')
	{
		tipOwner.onmousemove = function(in_event) { domTT_mousemove(this, in_event); };
	}

	if (typeof(tipOwner.onmouseout) != 'function')
	{
		tipOwner.onmouseout = function(in_event) { domTT_mouseout(this, in_event); };
	}

	if (in_options.get('type') == 'sticky')
	{
		if (in_options.get('position') == 'absolute' && domTT_dragEnabled && in_options.get('draggable'))
		{
			if (domLib_isIE)
			{
				captionRow.onselectstart = function() { return false; };
			}

			// setup drag
			captionRow.onmousedown = function(in_event) { domTT_dragStart(tipObj, in_event);  };
			captionRow.onmousemove = function(in_event) { domTT_dragUpdate(in_event); };
			captionRow.onmouseup = function() { domTT_dragStop(); };
		}
	}
	else if (in_options.get('type') == 'velcro')
	{
		/* can use once we have deactivateDelay
		tipObj.onmouseover = function(in_event)
		{
			if (typeof(in_event) == 'undefined') { in_event = window.event; }
			var tooltip = domTT_tooltips.get(tipObj.id);
			if (in_options.get('lifetime')) {
				domLib_clearTimeout(in_options.get('lifetimeTimeout');
			}
		};
		*/
		tipObj.onmouseout = function(in_event)
		{
			if (typeof(in_event) == 'undefined') { in_event = window.event; }
			if (!domLib_isDescendantOf(in_event[domLib_eventTo], tipObj, domTT_bannedTags)) {
				domTT_deactivate(tipOwner.id);
			}
		};
		// NOTE: this might interfere with links in the tip
		tipObj.onclick = function(in_event)
		{
			domTT_deactivate(tipOwner.id);
		};
	}

	if (in_options.get('position') == 'relative')
	{
		tipObj.style.position = 'relative';
	}

	in_options.set('node', tipObj);
	in_options.set('status', 'inactive');
}

// }}}
// {{{ domTT_show()

// in_id is either tip id or the owner id
function domTT_show(in_id, in_event)
{

	// should always find one since this call would be cancelled if tip was killed
	var tooltip = domTT_tooltips.get(in_id);
	var status = tooltip.get('status');
	var tipObj = tooltip.get('node');

	if (tooltip.get('position') == 'absolute')
	{
		var mouseX, mouseY;

		if (tooltip.has('x') && tooltip.has('y'))
		{
			mouseX = tooltip.get('x');
			mouseY = tooltip.get('y');
		}
		else if (!domTT_useGlobalMousePosition || domTT_mousePosition == null || status == 'active' || tooltip.get('delay') == 0)
		{
			var eventPosition = domLib_getEventPosition(in_event);
			var eventX = eventPosition.get('x');
			var eventY = eventPosition.get('y');
			if (tooltip.get('inframe'))
			{
				eventX -= eventPosition.get('scrollX');
				eventY -= eventPosition.get('scrollY');
			}

			// only move tip along requested trail axis when updating position
			if (status == 'active' && tooltip.get('trail') !== true)
			{
				var trail = tooltip.get('trail');
				if (trail == 'x')
				{
					mouseX = eventX;
					mouseY = tooltip.get('mouseY');
				}
				else if (trail == 'y')
				{
					mouseX = tooltip.get('mouseX');
					mouseY = eventY;
				}
			}
			else
			{
				mouseX = eventX;
				mouseY = eventY;
			}
		}
		else
		{
			mouseX = domTT_mousePosition.get('x');
			mouseY = domTT_mousePosition.get('y');
			if (tooltip.get('inframe'))
			{
				mouseX -= domTT_mousePosition.get('scrollX');
				mouseY -= domTT_mousePosition.get('scrollY');
			}
		}

		// we are using a grid for updates
		if (tooltip.get('grid'))
		{
			// if this is not a mousemove event or it is a mousemove event on an active tip and
			// the movement is bigger than the grid
			if (in_event.type != 'mousemove' || (status == 'active' && (Math.abs(tooltip.get('lastX') - mouseX) > tooltip.get('grid') || Math.abs(tooltip.get('lastY') - mouseY) > tooltip.get('grid'))))
			{
				tooltip.set('lastX', mouseX);
				tooltip.set('lastY', mouseY);
			}
			// did not satisfy the grid movement requirement
			else
			{
				return false;
			}
		}

		// mouseX and mouseY store the last acknowleged mouse position,
		// good for trailing on one axis
		tooltip.set('mouseX', mouseX);
		tooltip.set('mouseY', mouseY);

		var coordinates;
		if (domTT_screenEdgeDetection)
		{
			coordinates = domTT_correctEdgeBleed(
				tooltip.get('offsetWidth'),
				tooltip.get('offsetHeight'),
				mouseX,
				mouseY,
				tooltip.get('offsetX'),
				tooltip.get('offsetY'),
				tooltip.get('mouseOffset'),
				tooltip.get('inframe') ? window.parent : window
			);
		}
		else
		{
			coordinates = {
				'x' : mouseX + tooltip.get('offsetX'),
				'y' : mouseY + tooltip.get('offsetY') + tooltip.get('mouseOffset')
			};
		}

		// update the position
		tipObj.style.left = coordinates.x + 'px';
		tipObj.style.top = coordinates.y + 'px';

		// increase the tip zIndex so it goes over previously shown tips
		tipObj.style.zIndex = domLib_zIndex++;
	}

	// if tip is not active, active it now and check for a fade in
	if (status == 'pending')
	{
		// unhide the tooltip
		tooltip.set('status', 'active');
		tipObj.style.display = '';
		tipObj.style.visibility = 'visible';

		var fade = tooltip.get('fade');
		if (fade != 'neither')
		{
			var fadeHandler = tooltip.get('fadeHandler');
			if (fade == 'out' || fade == 'both')
			{
				fadeHandler.haltFade();
				if (fade == 'out')
				{
					fadeHandler.halt();
				}
			}

			if (fade == 'in' || fade == 'both')
			{
				fadeHandler.fadeIn();
			}
		}

		if (tooltip.get('type') == 'greasy' && tooltip.get('lifetime') != 0)
		{
			tooltip.set('lifetimeTimeout', domLib_setTimeout(domTT_runDeactivate, tooltip.get('lifetime'), [tipObj.id]));
		}
	}

	if (tooltip.get('position') == 'absolute' && domTT_detectCollisions)
	{
		// utilize original collision element cache
		domLib_detectCollisions(tipObj, false, true);
	}
}

// }}}
// {{{ domTT_close()

// in_handle can either be an child object of the tip, the tip id or the owner id
function domTT_close(in_handle)
{
	var id;
	if (typeof(in_handle) == 'object' && in_handle.nodeType)
	{
		var obj = in_handle;
		while (!obj.id || !domTT_tooltips.get(obj.id))
		{
			obj = obj.parentNode;
	
			if (obj.nodeType != document.ELEMENT_NODE) { return; }
		}

		id = obj.id;
	}
	else
	{
		id = in_handle;
	}

	domTT_deactivate(id);
}

// }}}
// {{{ domTT_closeAll()

// run through the tooltips and close them all
function domTT_closeAll()
{
	// NOTE: this will iterate 2x # of tooltips
	for (var id in domTT_tooltips.elementData) {
		domTT_close(id);
	}
}

// }}}
// {{{ domTT_deactivate()

// in_id is either the tip id or the owner id
function domTT_deactivate(in_id)
{
	var tooltip = domTT_tooltips.get(in_id);
	if (tooltip)
	{
		var status = tooltip.get('status');
		if (status == 'pending')
		{
			// cancel the creation of this tip if it is still pending
			domLib_clearTimeout(tooltip.get('activateTimeout'));
			tooltip.set('status', 'inactive');
		}
		else if (status == 'active')
		{
			if (tooltip.get('lifetime'))
			{
				domLib_clearTimeout(tooltip.get('lifetimeTimeout'));
			}

			var tipObj = tooltip.get('node');
			if (tooltip.get('closeAction') == 'hide')
			{
				var fade = tooltip.get('fade');
				if (fade != 'neither')
				{
					var fadeHandler = tooltip.get('fadeHandler');
					if (fade == 'out' || fade == 'both')
					{
						fadeHandler.fadeOut();
					}
					else
					{
						fadeHandler.hide();
					}
				}
				else
				{
					tipObj.style.display = 'none';
				}
			}
			else
			{
				tooltip.get('parent').removeChild(tipObj);
				domTT_tooltips.remove(tooltip.get('owner').id);
				domTT_tooltips.remove(tooltip.get('id'));
			}

			tooltip.set('status', 'inactive');
			if (domTT_detectCollisions) {
				// unhide all of the selects that are owned by this object
				// utilize original collision element cache
				domLib_detectCollisions(tipObj, true, true); 
			}
		}
	}
}

// }}}
// {{{ domTT_mouseout()

function domTT_mouseout(in_owner, in_event)
{
	if (!domLib_useLibrary) { return false; }

	if (typeof(in_event) == 'undefined') { in_event = window.event;	}

	var toChild = domLib_isDescendantOf(in_event[domLib_eventTo], in_owner, domTT_bannedTags);
	var tooltip = domTT_tooltips.get(in_owner.id);
	if (tooltip && (tooltip.get('type') == 'greasy' || tooltip.get('status') != 'active'))
	{
		// deactivate tip if exists and we moved away from the owner
		if (!toChild)
		{
			domTT_deactivate(in_owner.id);
			try { window.status = window.defaultStatus; } catch(e) {}
		}
	}
	else if (!toChild)
	{
		try { window.status = window.defaultStatus; } catch(e) {}
	}
}

// }}}
// {{{ domTT_mousemove()

function domTT_mousemove(in_owner, in_event)
{
	if (!domLib_useLibrary) { return false; }

	if (typeof(in_event) == 'undefined') { in_event = window.event;	}

	var tooltip = domTT_tooltips.get(in_owner.id);
	if (tooltip && tooltip.get('trail') && tooltip.get('status') == 'active')
	{
		// see if we are trailing lazy
		if (tooltip.get('lazy'))
		{
			domLib_setTimeout(domTT_runShow, domTT_trailDelay, [in_owner.id, in_event]);
		}
		else
		{
			domTT_show(in_owner.id, in_event);
		}
	}
}

// }}}
// {{{ domTT_addPredefined()

function domTT_addPredefined(in_id)
{
	var options = new Hash();
	for (var i = 1; i < arguments.length; i += 2)
	{
		options.set(arguments[i], arguments[i + 1]);
	}

	domTT_predefined.set(in_id, options);
}

// }}}
// {{{ domTT_correctEdgeBleed()

function domTT_correctEdgeBleed(in_width, in_height, in_x, in_y, in_offsetX, in_offsetY, in_mouseOffset, in_window)
{
	var win, doc;
	var bleedRight, bleedBottom;
	var pageHeight, pageWidth, pageYOffset, pageXOffset;

	var x = in_x + in_offsetX;
	var y = in_y + in_offsetY + in_mouseOffset;

	win = (typeof(in_window) == 'undefined' ? window : in_window);

	// Gecko and IE swaps values of clientHeight, clientWidth properties when
	// in standards compliance mode from documentElement to document.body
	doc = ((domLib_standardsMode && (domLib_isIE || domLib_isGecko)) ? win.document.documentElement : win.document.body);

	// for IE in compliance mode
	if (domLib_isIE)
	{
		pageHeight = doc.clientHeight;
		pageWidth = doc.clientWidth;
		pageYOffset = doc.scrollTop;
		pageXOffset = doc.scrollLeft;
	}
	else
	{
		pageHeight = doc.clientHeight;
		pageWidth = doc.clientWidth;

		if (domLib_isKHTML)
		{
			pageHeight = win.innerHeight;
		}

		pageYOffset = win.pageYOffset;
		pageXOffset = win.pageXOffset;
	}

	// we are bleeding off the right, move tip over to stay on page
	// logic: take x position, add width and subtract from effective page width
	if ((bleedRight = (x - pageXOffset) + in_width - (pageWidth - domTT_screenEdgePadding)) > 0)
	{
		x -= bleedRight;
	}

	// we are bleeding to the left, move tip over to stay on page
	// if tip doesn't fit, we will go back to bleeding off the right
	// logic: take x position and check if less than edge padding
	if ((x - pageXOffset) < domTT_screenEdgePadding)
	{
		x = domTT_screenEdgePadding + pageXOffset;
	}

	// if we are bleeding off the bottom, flip to north
	// logic: take y position, add height and subtract from effective page height
	if ((bleedBottom = (y - pageYOffset) + in_height - (pageHeight - domTT_screenEdgePadding)) > 0)
	{
		y = in_y - in_height - in_offsetY;
	}

	// if we are bleeding off the top, flip to south
	// if tip doesn't fit, we will go back to bleeding off the bottom
	// logic: take y position and check if less than edge padding
	if ((y - pageYOffset) < domTT_screenEdgePadding)
	{
		y = in_y + domTT_mouseHeight + in_offsetY;
	}

	return {'x' : x, 'y' : y};
}

// }}}
// {{{ domTT_isActive()

// in_id is either the tip id or the owner id
function domTT_isActive(in_id)
{
	var tooltip = domTT_tooltips.get(in_id);
	if (!tooltip || tooltip.get('status') != 'active')
	{
		return false;
	}
	else
	{
		return true;
	}
}

// }}}
// {{{ domTT_runXXX()

// All of these domMenu_runXXX() methods are used by the event handling sections to
// avoid the circular memory leaks caused by inner functions
function domTT_runDeactivate(args) { domTT_deactivate(args[0]); }
function domTT_runShow(args) { domTT_show(args[0], args[1]); }

// }}}
// {{{ domTT_replaceTitles()

function domTT_replaceTitles(in_decorator)
{
	var elements = domLib_getElementsByClass('tooltip');
	for (var i = 0; i < elements.length; i++)
	{
		if (elements[i].title)
		{
			var content;
			if (typeof(in_decorator) == 'function')
			{
				content = in_decorator(elements[i]);
			}
			else
			{
				content = elements[i].title;
			}

			content = content.replace(new RegExp('\'', 'g'), '\\\'');
			elements[i].onmouseover = new Function('in_event', "domTT_activate(this, in_event, 'content', '" + content + "')");
			elements[i].title = '';
		}
	}
}

// }}}
// {{{ domTT_update()

// Allow authors to update the contents of existing tips using the DOM
// Unfortunately, the tip must already exist, or else no work is done.
// TODO: make getting at content or caption cleaner
function domTT_update(handle, content, type)
{
	// type defaults to 'content', can also be 'caption'
	if (typeof(type) == 'undefined')
	{
		type = 'content';
	}

	var tip = domTT_tooltips.get(handle);
	if (!tip)
	{
		return;
	}

	var tipObj = tip.get('node');
	var updateNode;
	if (type == 'content')
	{
		// <div class="contents">...
		updateNode = tipObj.firstChild;
		if (updateNode.className != 'contents')
		{
			// <table><tbody><tr>...</tr><tr><td><div class="contents">...
			updateNode = updateNode.firstChild.firstChild.nextSibling.firstChild.firstChild;
		}
	}
	else
	{
		updateNode = tipObj.firstChild;
		if (updateNode.className == 'contents')
		{
			// missing caption
			return;
		}

		// <table><tbody><tr><td><div class="caption">...
		updateNode = updateNode.firstChild.firstChild.firstChild.firstChild;
	}

	// TODO: allow for a DOM node as content
	updateNode.innerHTML = content;
}

// }}}
