<?php
/* $Id$ */
/*
    index.php
    Copyright (C) 2004, 2005 Scott Ullrich
    All rights reserved.

    Originally part of m0n0wall (http://m0n0.ch/wall)
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
    oR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

	## Load Essential Includes
	require_once('guiconfig.inc');
	require_once('notices.inc');

	## Load Functions Files
	require_once('includes/functions.inc.php');


	## Load AJAX, Initiate Class ###############################################
	require_once('includes/sajax.class.php');

	## Initiate Class and Set location of ajax file containing 
	## the information that we need for this page. Also set functions
	## that SAJAX will be using.
	$oSajax = new sajax();
	$oSajax->sajax_remote_uri = 'sajax/index.sajax.php';
	$oSajax->sajax_request_type = 'POST';
	$oSajax->sajax_export("get_stats");
	$oSajax->sajax_handle_client_request();
	############################################################################


	## Check to see if we have a swap space,
	## if true, display, if false, hide it ...
	if(file_exists("/usr/sbin/swapinfo")) {
		$swapinfo = `/usr/sbin/swapinfo`;
		if(stristr($swapinfo,'%') == true) $showswap=true;
	}


	## User recently restored his config.
	## If packages are installed lets resync
	if(file_exists('/conf/needs_package_sync')) {
		if($config['installedpackages'] <> '') {
			conf_mount_rw();
			unlink('/conf/needs_package_sync');
			header('Location: pkg_mgr_install.php?mode=reinstallall');
			exit;
		}
	}


	## If it is the first time webGUI has been
	## accessed since initial install show this stuff.
	if(file_exists('/conf/trigger_initial_wizard')) {

		$pgtitle = 'pfSense first time setup';
		include('head.inc');

		echo "<body link=\"#0000CC\" vlink=\"#0000CC\" alink=\"#0000CC\">\n";
		echo "<form>\n";
		echo "<center>\n";
		echo "<img src=\"/themes/{$g['theme']}/images/logo.gif\" border=\"0\"><p>\n";
		echo "<div \" style=\"width:700px;background-color:#ffffff\" id=\"nifty\">\n";
		echo "Welcome to pfSense!<p>\n";
		echo "One moment while we start the initial setup wizard.<p>\n";
		echo "Embedded platform users: Please be patient, the wizard takes a little longer to run than the normal gui.<p>\n";
		echo "To bypass the wizard, click on the pfSense wizard on the initial page.\n";
		echo "</div>\n";
		echo "<meta http-equiv=\"refresh\" content=\"1;url=wizard.php?xml=setup_wizard.xml\">\n";
		echo "<script type=\"text/javascript\">\n";
		echo "NiftyCheck();\n";
		echo "Rounded(\"div#nifty\",\"all\",\"#000\",\"#FFFFFF\",\"smooth\");\n";
		echo "</script>\n";
		exit;
	}


	## Find out whether there's hardware encryption or not
	unset($hwcrypto);
	$fd = @fopen("{$g['varlog_path']}/dmesg.boot", "r");
	if ($fd) {
		while (!feof($fd)) {
			$dmesgl = fgets($fd);
			if (preg_match("/^hifn.: (.*?),/", $dmesgl, $matches)) {
				$hwcrypto = $matches[1];
				break;
			}
		}
		fclose($fd);
	}
	/*
if (!is_array($config['widgets']['widget']))
$config['widgets']['widget'] = array();

$a_schedules = &$config['widgets']['widget'];
	
if ($_POST){
	
}
*/
	
if ($config['widgets'])
{
	foreach ($config['widgets'] as $widget)
	{
		
	}
}
else
{
	//build list of widgets
	$directory = "widgets/widgets/";
	$dirhandle  = opendir($directory);
	$filename = "";
	while (false !== ($filename = readdir($dirhandle))) {
   		$periodpos = strpos($filename, ".");
		$widgetname = substr($filename, 0, $periodpos);	
   		if ($widgetname != "system_information")
   			$widgetfiles[] = $filename;   		
	}
	sort($widgetfiles);
	array_unshift($widgetfiles, "system_information.widget.php");
}
	
	

	
	//build list of php include files
	$phpincludefiles = Array();
	$directory = "widgets/include/";
	$dirhandle  = opendir($directory);
	$filename = "";
	while (false !== ($filename = readdir($dirhandle))) {
   		$phpincludefiles[] = $filename;
	}
	foreach($phpincludefiles as $includename) {
		if(!stristr($includename, ".inc"))
			continue;	
		include($directory . $includename);
	}
	
	

$jscriptstr = <<<EOD
<script language="javascript" type="text/javascript">


function showDiv(selectedDiv,swapButtons){
		//appear element
    Effect.BlindDown(selectedDiv, {duration:1});      
    
    if (swapButtons){
	    d = document;	
	    selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";
	    
	    
	    selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
		
		selectIntLink = selectedDiv + "-input";
		textlink = d.getElementById(selectIntLink);
		textlink.value = "show";	
    }
    updatePref();
}
	
function minimizeDiv(selectedDiv,swapButtons){
	//fade element
    Effect.BlindUp(selectedDiv, {duration:1});      
    if (swapButtons){
	    d = document;	
	    selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";	    
	    
	    selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
		
		selectIntLink = selectedDiv + "-input";
		textlink = d.getElementById(selectIntLink);
		textlink.value = "hide";	
    }
    updatePref();
}

function closeDiv(selectedDiv){
	selectedDiv = selectedDiv + "div";
	Effect.Fade(selectedDiv, {duration:1}); 
	selectIntLink = selectedDiv + "-input";
	textlink = d.getElementById(selectIntLink);
	textlink.value = "close";	
	updatePref();
}

function updatePref(){
	Effect.Appear('submitpref',{duration:1});
	Sortable.serialize('col1');
	Sortable.serialize('col2');
}


</script>
EOD;
$closehead = false;

## Set Page Title and Include Header
$pgtitle = "pfSense webGUI";
include("head.inc");
	
	
echo "<script type=\"text/javascript\" language=\"javascript\" src=\"/javascript/domTT/domLib.js\"></script>";
echo "<script type=\"text/javascript\" language=\"javascript\" src=\"/javascript/domTT/domTT.js\"></script>";
echo "<script type=\"text/javascript\" language=\"javascript\" src=\"/javascript/domTT/behaviour.js\"></script>";
echo "<script type=\"text/javascript\" language=\"javascript\" src=\"/javascript/domTT/fadomatic.js\"></script>";
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<form action="index.php" method="post">
<script language="javascript" type="text/javascript">
// <![CDATA[
columns = ['col1','col2'];
// ]]>

</script>


<script src="/javascript/scriptaculous/prototype.js" type="text/javascript"></script>
<script src="/javascript/scriptaculous/scriptaculous.js" type="text/javascript"></script>

<?php
include("fbegin.inc");
echo $jscriptstr;
	if(!file_exists("/usr/local/www/themes/{$g['theme']}/no_big_logo"))
		echo "<center><img src=\"./themes/".$g['theme']."/images/logobig.jpg\"></center><br>";
?>

<?php 
	?>
<div id="widgetcontainer" style="display:none">
		<div id="content1"><h1>Available Widgets</h1><p><?php
			foreach($widgetfiles as $widget) {
			
				if(!stristr($widget, "widget.php"))
					continue;		
				
				$periodpos = strpos($widget, ".");
				$widgetname = substr($widget, 0, $periodpos);
				$nicename = $widgetname;
				$nicename = str_replace("_", " ", $nicename);
				//make the title look nice
				$nicename = ucwords($nicename);?>
				<span style="cursor: pointer;" onclick='return showDiv("<?php echo $widgetname; ?>div",false)'><u><?php echo $nicename; ?></u></span><br><?php
			}
		?>
		</p>
	</div>
</div>


<p class="pgtitle">System Overview&nbsp;&nbsp;&nbsp;
<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="Click here to add widgets" style="cursor: help;" onmouseup="domTT_activate(this, event, 'content', document.getElementById('content1'), 'type', 'velcro', 'delay', 0, 'fade', 'both', 'fadeMax', 100, 'styleClass', 'niceTitle');" />

<div style="clear:both;"></div>

<div id="submitpref" align="center" style="display:none;width:17%;margin:5px;padding: 5px;background:#CCCCCC">
	<div class="listtopic">
		Save your changes<div style="clear:both;"></div>
	</div>
	<div><center>
		<input id="submit" name="submit" type="submit" onclick="return checkForRanges();" class="formbtn" value="Save" />
		</center>
	</div>
</div></p>

<div style="clear:both;"></div>
<div id="niftyOutter">
	<?php
	$totalwidgets = count($widgetfiles);
	$halftotal = $totalwidgets / 2;
	$widgetcounter = 1;
	$directory = "widgets/widgets/";
	$printed = false;
	$firstprint = false;
	?> 
	<div id="col1" style="float:left;width:49%;padding: 2px;padding-bottom:40px">		
	<?php
	
	foreach($widgetfiles as $widget) {
	
		if(!stristr($widget, "widget.php"))
			continue;		
		
		$periodpos = strpos($widget, ".");
		$widgetname = substr($widget, 0, $periodpos);	
		$nicename = $widgetname;
		$nicename = str_replace("_", " ", $nicename);
			
		//make the title look nice
		$nicename = ucwords($nicename);
		
		$display = "block";
		
		
		if ($widgetcounter >= $halftotal && $printed == false){
			$printed = true;
			?>
			</div>
			<div id="col2" style="float:right;width:49%;padding: 2px;padding-bottom:40px">		
			<?php
		}			
		?>		
		<div style="clear:both;"></div>
		<div  id="<?php echo $widgetname;?>div" class="widgetdiv" style="display:<?php echo $display; ?>;">
			<input type="hidden" value="" id="<?php echo $widgetname;?>-input">
			<div id="<?php echo $widgetname;?>topic" class="widgetheader" style="cursor:move">
				<div style="float:left;">
					<?php echo $nicename;?>
				</div>
				<div align="right" style="float:right;">					
					<div id="<?php echo $widgetname;?>-open" onclick='return showDiv("<?php echo $widgetname;?>",true)' style="display:none; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_open.gif" /></div>	
					<div id="<?php echo $widgetname;?>-min" onclick='return minimizeDiv("<?php echo $widgetname;?>",true)' style="display:inline; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_minus.gif"/></div>												
					<div id="<?php echo $widgetname;?>-close" onclick='return closeDiv("<?php echo $widgetname;?>",true)' style="display:inline; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_close.gif" /></div>	
				</div>
				<div style="clear:both;"></div>
			</div>
			<div id="<?php echo $widgetname;?>">
				<?php include($directory . $widget); ?>
			</div>
			<div style="clear:both;"></div>
		</div>
		<?php 	
	$widgetcounter++;
	
	}//end foreach	
	?>			
	</div><!-- end col -->
	<div style="clear:both;"></div>
</div>

<?php include("fend.inc"); ?>
	    
<script type="text/javascript">

	// <![CDATA[
	Sortable.create("col1", {tag:'div',dropOnEmpty:true,containment:columns,handle:'widgetheader',constraint:false,only:'moveable',onChange:updatePref});	
	Sortable.create("col2", {tag:'div',dropOnEmpty:true,containment:columns,handle:'widgetheader',constraint:false,only:'moveable',onChange:updatePref});		
	// ]]>	
	
	<?php
	//build list of javascript include files
	$jsincludefiles = Array();
	$directory = "widgets/javascript/";
	$dirhandle  = opendir($directory);
	$filename = "";
	while (false !== ($filename = readdir($dirhandle))) {
   		$jsincludefiles[] = $filename;
	}
	foreach($jsincludefiles as $jsincludename) {
		if(!stristr($jsincludename, ".js"))
			continue;	
		include($directory . $jsincludename);
	}
	?>
</script>
</form>
</body>
</html>