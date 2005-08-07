function AddServerToPool(form) {
	var ServerPort=form.ipaddr.value;
	if(form.type.selectedIndex == 2)
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