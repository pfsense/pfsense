function AddServerToPool(form) {
	var ServerPort=form.ipaddr.value;
	if(form.type.selectedIndex == 2)
		var ServerPort=form.ipaddr.value;
	else
		var ServerPort=form.ipaddr.value + "|" + form.monitorip.value;
	form['servers[]'].options[form['servers[]'].options.length] = new Option(ServerPort,ServerPort);
}

function RemoveServerFromPool(form) {
	form.ipaddr=form['servers[]'].options[form['servers[]'].selectedIndex].value;
	form['servers[]'].options[form['servers[]'].selectedIndex] = null;
}

function AllServers(id, selectAll) {
   var opts = document.getElementById(id).getElementsByTagName('option');
   for (i = 0; i < opts.length; i++)
   {
       opts[i].selected = selectAll;
   }
}
