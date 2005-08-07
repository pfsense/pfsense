function AddServerToPool(form) {
	var ServerPort=form.ipaddr.value;
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
