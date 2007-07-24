function updateIpsec(){
	selectIntLink = "ipsecDetailed";
	ipsecsettings = "ipsecDetail=";
	ipsecsettings += d.getElementById(selectIntLink).checked;
	
    selectIntLink = "ipsec-config";
	textlink = d.getElementById(selectIntLink);
	textlink.value = ipsecsettings;
}