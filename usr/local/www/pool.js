/*
        pool.js
        part of pfSense (http://www.pfsense.com/)

        Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
        All rights reserved.

        Redistribution and use in source and binary forms, with or without
        modification, are permitted provided that the following conditions are met:

        1. Redistributions of source code must retain the above copyright notice,
           this list of conditions and the following disclaimer.

        2. Redistributions in binary form must reproduce the above copyright
           notice, this list of conditions and the following disclaimer in the
           documentation and/or other materials provided with the distribution.

        THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
        INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
        AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
        AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
        OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
        SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
        INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
        CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
        ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
        POSSIBILITY OF SUCH DAMAGE.
*/

function AddServerToPool(form) {

	var theSel = form['servers[]'];
	for(i=theSel.length-1; i>=0; i--)
	{
		if(theSel.options[i].value == form.ipaddr.value) {
			alert("IP Address Already In List");
			return true;
		}
	}

	if (form.type.selectedIndex == 1) {
		if (!form.monitorip.value) {
			alert("Monitor IP Required First!");
			return true;
		}
	}

	var ServerPort=form.ipaddr.value;
	if(form.type.selectedIndex == 0)
		var ServerPort=form.ipaddr.value;
	else
		var ServerPort=form.ipaddr.value + "|" + form.monitorip.value;
	form['servers[]'].options[form['servers[]'].options.length] = new Option(ServerPort,ServerPort);
}


function AllServers(id, selectAll) {
   var opts = document.getElementById(id).getElementsByTagName('option');
   for (i = 0; i < opts.length; i++)
   {
       opts[i].selected = selectAll;
   }
}


function RemoveServerFromPool(form)
{
	var theSel = form['servers[]'];
	var selIndex = theSel.selectedIndex;
	if (selIndex != -1) {
		for(i=theSel.length-1; i>=0; i--)
		{
			if(theSel.options[i].selected)
			{
				theSel.options[i] = null;
			}
		}
		if (theSel.length > 0) {
			theSel.selectedIndex = selIndex == 0 ? 0 : selIndex - 1;
		}
	}
}
