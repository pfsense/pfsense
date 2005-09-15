#!/usr/local/bin/php
<?
        if(Connection_Aborted()) {
                exit;
        }
	
	require("../includes/sajax.class.php");
	require("../includes/functions.inc.php");

	$oSajax = new sajax();
	$oSajax->sajax_export("mem_usage","cpu_usage","get_uptime","get_pfstate", "get_temp");
	$oSajax->sajax_handle_client_request();
?>