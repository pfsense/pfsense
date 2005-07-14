/**************************************************
 * dom-drag.js
 * 09.25.2001
 * www.youngpup.net
 **************************************************
 * 10.28.2001 - fixed minor bug where events
 * sometimes fired off the handle, not the root.
 **************************************************
 * 05.30.2005 - added a workaround for firefox
 * activating links when finished dragging.
 * mmosier@astrolabe.com
 **************************************************/
<?php
/*
    The DragList drag and drop ordered lists implementation is available under the terms of the new BSD license.
   
   Copyright (c) 2005, DTLink, LLC
   All rights reserved.
                                                                                  
   Redistribution  and  use  in  source  and binary forms, with or
   without modification, are permitted provided that the following
   conditions are met:
                                                                                  
   *   Redistributions  of  source  code  must  retain  the  above
   copyright  notice,  this  list  of conditions and the following
   disclaimer.
                                                                                  
   *  Redistributions  in  binary  form  must  reproduce the above
   copyright  notice,  this  list  of conditions and the following
   disclaimer in the documentation and/or other materials provided
   with the distribution.
    
   *  Neither  the  name  of  DTLink,  LLC  nor  the  names of its
   contributors may be used to endorse or promote products derived
   from this software without specific prior written permission.
    
   THIS   SOFTWARE  IS  PROVIDED  BY  THE  COPYRIGHT  HOLDERS  AND
   CONTRIBUTORS  "AS  IS"  AND  ANY EXPRESS OR IMPLIED WARRANTIES,
   INCLUDING,  BUT  NOT  LIMITED  TO,  THE  IMPLIED  WARRANTIES OF
   MERCHANTABILITY  AND  FITNESS  FOR  A  PARTICULAR  PURPOSE  ARE
   DISCLAIMED.   IN   NO   EVENT  SHALL  THE  COPYRIGHT  OWNER  OR
   CONTRIBUTORS  BE  LIABLE  FOR ANY DIRECT, INDIRECT, INCIDENTAL,
   SPECIAL,  EXEMPLARY,  OR  CONSEQUENTIAL DAMAGES (INCLUDING, BUT
   NOT  LIMITED  TO,  PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
   LOSS  OF  USE,  DATA,  OR  PROFITS;  OR  BUSINESS INTERRUPTION)
   HOWEVER  CAUSED  AND  ON  ANY  THEORY  OF LIABILITY, WHETHER IN
   CONTRACT,  STRICT  LIABILITY,  OR TORT (INCLUDING NEGLIGENCE OR
   OTHERWISE)  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
   EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
?>

var Drag = {

	obj : null,

	init : function(o, oRoot, minX, maxX, minY, maxY, bSwapHorzRef, bSwapVertRef, fXMapper, fYMapper)
	{
		o.onmousedown	= Drag.start;

		o.hmode			= bSwapHorzRef ? false : true ;
		o.vmode			= bSwapVertRef ? false : true ;

		o.root = oRoot && oRoot != null ? oRoot : o ;

		if (o.hmode  && isNaN(parseInt(o.root.style.left  ))) o.root.style.left   = "0px";
		if (o.vmode  && isNaN(parseInt(o.root.style.top   ))) o.root.style.top    = "0px";
		if (!o.hmode && isNaN(parseInt(o.root.style.right ))) o.root.style.right  = "0px";
		if (!o.vmode && isNaN(parseInt(o.root.style.bottom))) o.root.style.bottom = "0px";

		o.minX	= typeof minX != 'undefined' ? minX : null;
		o.minY	= typeof minY != 'undefined' ? minY : null;
		o.maxX	= typeof maxX != 'undefined' ? maxX : null;
		o.maxY	= typeof maxY != 'undefined' ? maxY : null;

		o.xMapper = fXMapper ? fXMapper : null;
		o.yMapper = fYMapper ? fYMapper : null;

		o.root.onDragStart	= new Function();
		o.root.onDragEnd	= new Function();
		o.root.onDrag		= new Function();
	},

	start : function(e)
	{
		var o = Drag.obj = this;
		e = Drag.fixE(e);
		var y = parseInt(o.vmode ? o.root.style.top  : o.root.style.bottom);
		var x = parseInt(o.hmode ? o.root.style.left : o.root.style.right );
		o.root.onDragStart(x, y);

		o.startX		= x;
		o.startY		= y;
		o.lastMouseX	= e.clientX;
		o.lastMouseY	= e.clientY;

		if (o.hmode) {
			if (o.minX != null)	o.minMouseX	= e.clientX - x + o.minX;
			if (o.maxX != null)	o.maxMouseX	= o.minMouseX + o.maxX - o.minX;
		} else {
			if (o.minX != null) o.maxMouseX = -o.minX + e.clientX + x;
			if (o.maxX != null) o.minMouseX = -o.maxX + e.clientX + x;
		}

		if (o.vmode) {
			if (o.minY != null)	o.minMouseY	= e.clientY - y + o.minY;
			if (o.maxY != null)	o.maxMouseY	= o.minMouseY + o.maxY - o.minY;
		} else {
			if (o.minY != null) o.maxMouseY = -o.minY + e.clientY + y;
			if (o.maxY != null) o.minMouseY = -o.maxY + e.clientY + y;
		}

		document.onmousemove	= Drag.drag;
		document.onmouseup		= Drag.end;

		if (o.linkDisabled) {
			var hrefs = o.root.getElementsByTagName("a");
			for (var i = 0; i < hrefs.length; i++) {
				hrefs[i].onclick = hrefs[i].prevOnclick;
				hrefs[i].prevOnclick = null;
			}
			o.linkDisabled = false;
		}

		return false;
	},

	drag : function(e)
	{
		e = Drag.fixE(e);
		var o = Drag.obj;

		var ey	= e.clientY;
		var ex	= e.clientX;
		var y = parseInt(o.vmode ? o.root.style.top  : o.root.style.bottom);
		var x = parseInt(o.hmode ? o.root.style.left : o.root.style.right );
		var nx, ny;

		if (o.minX != null) ex = o.hmode ? Math.max(ex, o.minMouseX) : Math.min(ex, o.maxMouseX);
		if (o.maxX != null) ex = o.hmode ? Math.min(ex, o.maxMouseX) : Math.max(ex, o.minMouseX);
		if (o.minY != null) ey = o.vmode ? Math.max(ey, o.minMouseY) : Math.min(ey, o.maxMouseY);
		if (o.maxY != null) ey = o.vmode ? Math.min(ey, o.maxMouseY) : Math.max(ey, o.minMouseY);

		nx = x + ((ex - o.lastMouseX) * (o.hmode ? 1 : -1));
		ny = y + ((ey - o.lastMouseY) * (o.vmode ? 1 : -1));

		if (o.xMapper)		nx = o.xMapper(y)
		else if (o.yMapper)	ny = o.yMapper(x)

		Drag.obj.root.style[o.hmode ? "left" : "right"] = nx + "px";
		Drag.obj.root.style[o.vmode ? "top" : "bottom"] = ny + "px";
		Drag.obj.lastMouseX	= ex;
		Drag.obj.lastMouseY	= ey;

		var threshold = 4;
		if (!o.linkDisabled) {
			if (Math.abs(nx - o.startX) > threshold || Math.abs(ny - o.startY) > threshold) {
				var hrefs = o.root.getElementsByTagName("a");
				for (var i = 0; i < hrefs.length; i++) {
					hrefs[i].prevOnclick = hrefs[i].onclick;
					hrefs[i].onclick = function() { return false; };
				}
				o.linkDisabled = true;
			}
		}

		Drag.obj.root.onDrag(nx, ny, Drag.obj.root);
		return false;
	},

	end : function()
	{
		document.onmousemove = null;
		document.onmouseup   = null;
		Drag.obj.root.onDragEnd(	parseInt(Drag.obj.root.style[Drag.obj.hmode ? "left" : "right"]), 
									parseInt(Drag.obj.root.style[Drag.obj.vmode
                                    ? "top" : "bottom"]), Drag.obj.root);
		Drag.obj = null;
	},

	fixE : function(e)
	{
		if (typeof e == 'undefined') e = window.event;
		if (typeof e.layerX == 'undefined') e.layerX = e.offsetX;
		if (typeof e.layerY == 'undefined') e.layerY = e.offsetY;
		return e;
	}
};
