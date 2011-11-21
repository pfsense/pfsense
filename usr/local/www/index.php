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
/*
	pfSense_BUILDER_BINARIES:	/sbin/ifconfig
	pfSense_MODULE:	interfaces
*/

##|+PRIV
##|*IDENT=page-system-login/logout
##|*NAME=System: Login / Logout page / Dashboard
##|*DESCR=Allow access to the 'System: Login / Logout' page and Dashboard.
##|*MATCH=index.php*
##|-PRIV

// Turn off csrf for the dashboard
$nocsrf = true; 

// Turn on buffering to speed up rendering
ini_set('output_buffering','true');

// Start buffering with a cache size of 100000
ob_start(null, "1000");

## Load Essential Includes
require_once('functions.inc');
require_once('guiconfig.inc');
require_once('notices.inc');

if($g['disablecrashreporter'] != true) {
	// Check to see if we have a crash report
	$crash = glob("/var/crash/*");
	$x = 0;
	$skip_files = array(".", "..", "minfree", "");
	if(is_array($crash)) {
		foreach($crash as $c) {
			if (!in_array(basename($c), $skip_files))
				$x++;
		}
		if($x > 0) 
			$savemsg = "{$g['product_name']} has detected a crash report.  Click <a href='crash_reporter.php'>here</a> for more information.";
	}
}

##build list of widgets
$directory = "/usr/local/www/widgets/widgets/";
$dirhandle  = opendir($directory);
$filename = "";
$widgetnames = array();
$widgetfiles = array();
$widgetlist = array();

while (false !== ($filename = readdir($dirhandle))) {
	$periodpos = strpos($filename, ".");
	$widgetname = substr($filename, 0, $periodpos);
	$widgetnames[] = $widgetname;
	if ($widgetname != "system_information")
		$widgetfiles[] = $filename;
}

##sort widgets alphabetically
sort($widgetfiles);

##insert the system information widget as first, so as to be displayed first
array_unshift($widgetfiles, "system_information.widget.php");

##if no config entry found, initialize config entry
if (!is_array($config['widgets'])) {
	$config['widgets'] = array();
}

	if ($_POST && $_POST['submit']) {
		$config['widgets']['sequence'] = $_POST['sequence'];

		foreach ($widgetnames as $widget){
			if ($_POST[$widget . '-config']){
				$config['widgets'][$widget . '-config'] = $_POST[$widget . '-config'];
			}
		}

		write_config(gettext("Widget configuration has been changed."));
		header("Location: index.php");
		exit;
	}

	## Load Functions Files
	require_once('includes/functions.inc.php');
	
	## Check to see if we have a swap space,
	## if true, display, if false, hide it ...
	if(file_exists("/usr/sbin/swapinfo")) {
		$swapinfo = `/usr/sbin/swapinfo`;
		if(stristr($swapinfo,'%') == true) $showswap=true;
	}

	## User recently restored his config.
	## If packages are installed lets resync
	if(file_exists('/conf/needs_package_sync')) {
		if($config['installedpackages'] <> '' && is_array($config['installedpackages']['package'])) {
			if($g['platform'] == "pfSense" || $g['platform'] == "nanobsd") {
				header('Location: pkg_mgr_install.php?mode=reinstallall');
				exit;
			}
		} else {
			conf_mount_rw();
			@unlink('/conf/needs_package_sync');
			conf_mount_ro();
		}
	}

	## If it is the first time webConfigurator has been
	## accessed since initial install show this stuff.
	if(file_exists('/conf/trigger_initial_wizard')) {
		echo <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
	<title>{$g['product_name']}.localdomain - {$g['product_name']} first time setup</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<link rel="stylesheet" type="text/css" href="/niftycssprintCode.css" media="print" />
	<script type="text/javascript">var theme = "nervecenter"</script>
	<script type="text/javascript" src="/themes/nervecenter/loader.js"></script>
		
EOF;

		echo "<body link=\"#0000CC\" vlink=\"#0000CC\" alink=\"#0000CC\">\n";

		if(file_exists("/usr/local/www/themes/{$g['theme']}/wizard.css")) 
			echo "<link rel=\"stylesheet\" href=\"/themes/{$g['theme']}/wizard.css\" media=\"all\" />\n";
		else 
			echo "<link rel=\"stylesheet\" href=\"/themes/{$g['theme']}/all.css\" media=\"all\" />";

		echo "<form>\n";
		echo "<center>\n";
		echo "<img src=\"/themes/{$g['theme']}/images/logo.gif\" border=\"0\"><p>\n";
		echo "<div \" style=\"width:700px;background-color:#ffffff\" id=\"nifty\">\n";
		echo sprintf(gettext("Welcome to %s!\n"),$g['product_name']) . "<p>";
		echo gettext("One moment while we start the initial setup wizard.") . "<p>\n";
		echo gettext("Embedded platform users: Please be patient, the wizard takes a little longer to run than the normal GUI.") . "<p>\n";
		echo sprintf(gettext("To bypass the wizard, click on the %s logo on the initial page."),$g['product_name']) . "\n";
		echo "</div>\n";
		echo "<meta http-equiv=\"refresh\" content=\"1;url=wizard.php?xml=setup_wizard.xml\">\n";
		echo "<script type=\"text/javascript\">\n";
		echo "NiftyCheck();\n";
		echo "Rounded(\"div#nifty\",\"all\",\"#AAA\",\"#FFFFFF\",\"smooth\");\n";
		echo "</script>\n";
		exit;
	}


	## Find out whether there's hardware encryption or not
	unset($hwcrypto);
	$fd = @fopen("{$g['varlog_path']}/dmesg.boot", "r");
	if ($fd) {
		while (!feof($fd)) {
			$dmesgl = fgets($fd);
			if (preg_match("/^hifn.: (.*?),/", $dmesgl, $matches) or preg_match("/.*(VIA Padlock)/", $dmesgl, $matches) or preg_match("/^safe.: (\w.*)/", $dmesgl, $matches) or preg_match("/^ubsec.: (.*?),/", $dmesgl, $matches) or preg_match("/^padlock.: <(.*?)>,/", $dmesgl, $matches) or preg_match("/^glxsb.: (.*?),/", $dmesgl, $matches)) {
				$hwcrypto = $matches[1];
				break;
			}
		}
		fclose($fd);
	}

##build widget saved list information
if ($config['widgets'] && $config['widgets']['sequence'] != "") {
	$pconfig['sequence'] = $config['widgets']['sequence'];
	
	$widgetlist = $pconfig['sequence'];
	$colpos = array();
	$savedwidgetfiles = array();
	$widgetname = "";
	$widgetlist = explode(",",$widgetlist);
	
	##read the widget position and display information
	foreach ($widgetlist as $widget){
		$dashpos = strpos($widget, "-");		
		$widgetname = substr($widget, 0, $dashpos);
		$colposition = strpos($widget, ":");		
		$displayposition = strrpos($widget, ":");
		$colpos[] = substr($widget,$colposition+1, $displayposition - $colposition-1);
		$displayarray[] = substr($widget,$displayposition+1);
		$savedwidgetfiles[] = $widgetname . ".widget.php";
	}
	
	##add widgets that may not be in the saved configuration, in case they are to be displayed later
    foreach ($widgetfiles as $defaultwidgets){         
         if (!in_array($defaultwidgets, $savedwidgetfiles)){
             $savedwidgetfiles[] = $defaultwidgets;
         }
     }   
	
	##find custom configurations of a particular widget and load its info to $pconfig
	foreach ($widgetnames as $widget){
        if ($config['widgets'][$widget . '-config']){
            $pconfig[$widget . '-config'] = $config['widgets'][$widget . '-config'];
        }
    }   
	
	$widgetlist = $savedwidgetfiles;	
} else{
	// no saved widget sequence found, build default list.
	$widgetlist = $widgetfiles;
}

##build list of php include files
$phpincludefiles = array();
$directory = "/usr/local/www/widgets/include/";
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

##begin AJAX
$jscriptstr = <<<EOD
<script language="javascript" type="text/javascript">


function widgetAjax(widget) {	
	uri = "widgets/widgets/" + widget + ".widget.php";
	var opt = {
	    // Use GET
	    method: 'get',
		evalScripts: 'true',
	    asynchronous: true,
	    // Handle 404
	    on404: function(t) {
	        alert('Error 404: location "' + t.statusText + '" was not found.');
	    },
	    // Handle other errors
	    onFailure: function(t) {
	        alert('Error ' + t.status + ' -- ' + t.statusText);
	    },
		onSuccess: function(t) {
			widget2 = widget + "-loader";
			Effect.Fade(widget2, {queue:'front'});
			Effect.Appear(widget, {queue:'end'});			
	    }	
	}
	new Ajax.Updater(widget, uri, opt);
}


function addWidget(selectedDiv){	
	selectedDiv2 = selectedDiv + "-container";
	d = document;
	textlink = d.getElementById(selectedDiv2);
	Effect.Appear(selectedDiv2, {duration:1});
	if (textlink.style.display != "none")
	{
		Effect.Shake(selectedDiv2);	
	}
	else
	{
		widgetAjax(selectedDiv);
		selectIntLink = selectedDiv2 + "-input";
		textlink = d.getElementById(selectIntLink);
		textlink.value = "show";	
		showSave();
	}
}

function configureWidget(selectedDiv){
	selectIntLink = selectedDiv + "-settings";	
	d = document;
	textlink = d.getElementById(selectIntLink);
	if (textlink.style.display == "none")
		Effect.BlindDown(selectIntLink, {duration:1});
	else
		Effect.BlindUp(selectIntLink, {duration:1});
}

function showWidget(selectedDiv,swapButtons){
	//appear element
    Effect.BlindDown(selectedDiv, {duration:1});      
    showSave();    
	d = document;	
    if (swapButtons){
	    selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";
	    
	    
	    selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";

    }
	selectIntLink = selectedDiv + "-container-input";
	textlink = d.getElementById(selectIntLink);
	textlink.value = "show";	
    
}
	
function minimizeWidget(selectedDiv,swapButtons){
	//fade element
    Effect.BlindUp(selectedDiv, {duration:1});      
    showSave();
	d = document;	
    if (swapButtons){
	    selectIntLink = selectedDiv + "-open";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "inline";	    
	    
	    selectIntLink = selectedDiv + "-min";
		textlink = d.getElementById(selectIntLink);
		textlink.style.display = "none";
    }  		
	selectIntLink = selectedDiv + "-container-input";
	textlink = d.getElementById(selectIntLink);
	textlink.value = "hide";	  
    
}

function closeWidget(selectedDiv){	
	showSave();
	selectedDiv = selectedDiv + "-container";
	Effect.Fade(selectedDiv, {duration:1});
	d = document;
	selectIntLink = selectedDiv + "-input";
	textlink = d.getElementById(selectIntLink);
	textlink.value = "close";	
}

function showSave(){
	d = document;
	selectIntLink = "submit";
	textlink = d.getElementById(selectIntLink);
	textlink.style.display = "inline";	
}

function updatePref(){	
	var widgets = document.getElementsByClassName('widgetdiv');
	var widgetSequence = "";
	var firstprint = false;	
	d = document;
	for (i=0; i<widgets.length; i++){
		if (firstprint)
			widgetSequence += ",";
		var widget = widgets[i].id;
		widgetSequence += widget + ":" + widgets[i].parentNode.id + ":";
		widget = widget + "-input";
		textlink = d.getElementById(widget).value;
		widgetSequence += textlink;
		firstprint = true;		
	}
	selectLink = "sequence";
	textlink = d.getElementById(selectLink);
	textlink.value = widgetSequence;
	return true;	
}

function hideAllWidgets(){		
		Effect.Fade('niftyOutter', {to: 0.2});
}

function showAllWidgets(){		
		Effect.Fade('niftyOutter', {to: 1.0});
}


function changeTabDIV(selectedDiv){
	var dashpos = selectedDiv.indexOf("-");
	var tabclass = selectedDiv.substring(0,dashpos);
	d = document;

	//get deactive tabs first
	tabclass = tabclass + "-class-tabdeactive"; 
	var tabs = document.getElementsByClassName(tabclass);
	var incTabSelected = selectedDiv + "-deactive";
	for (i=0; i<tabs.length; i++){
		var tab = tabs[i].id;
		dashpos = tab.lastIndexOf("-");
		var tab2 = tab.substring(0,dashpos) + "-deactive";
		if (tab2 == incTabSelected){
			tablink = d.getElementById(tab2);
			tablink.style.display = "none";
			tab2 = tab.substring(0,dashpos) + "-active";
			tablink = d.getElementById(tab2);
			tablink.style.display = "table-cell";
			
			//now show main div associated with link clicked
			tabmain = d.getElementById(selectedDiv);
			tabmain.style.display = "block";
		}
		else
		{	
			tab2 = tab.substring(0,dashpos) + "-deactive";
			tablink = d.getElementById(tab2);
			tablink.style.display = "table-cell";
			tab2 = tab.substring(0,dashpos) + "-active";
			tablink = d.getElementById(tab2);
			tablink.style.display = "none";		
			
			//hide sections we don't want to see
			tab2 = tab.substring(0,dashpos);
			tabmain = d.getElementById(tab2);
			tabmain.style.display = "none";
				
		}
	}	
}

</script>
EOD;
$closehead = false;

## Set Page Title and Include Header
$pgtitle = array(gettext("Status: Dashboard"));
include("head.inc");

echo "\t<script type=\"text/javascript\" src=\"javascript/domTT/domLib.js\"></script>\n";
echo "\t<script type=\"text/javascript\" src=\"javascript/domTT/domTT.js\"></script>\n";
echo "\t<script type=\"text/javascript\" src=\"javascript/domTT/behaviour.js\"></script>\n";
echo "\t<script type=\"text/javascript\" src=\"javascript/domTT/fadomatic.js\"></script>\n";

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<script language="javascript" type="text/javascript">
// <![CDATA[
columns = ['col1','col2'];
// ]]>

</script>

<?php
include("fbegin.inc");
echo $jscriptstr;
	if(!file_exists("/usr/local/www/themes/{$g['theme']}/no_big_logo"))
		echo "<center><img src=\"./themes/".$g['theme']."/images/logobig.jpg\"></center><br>";

if ($savemsg) 
	print_info_box($savemsg); 

pfSense_handle_custom_code("/usr/local/pkg/dashboard/pre_dashboard");

?>
<div id="widgetcontainer" style="display:none">
		<div id="content1"><h1><?=gettext("Available Widgets"); ?></h1><p><?php
			$widgetfiles_add = $widgetfiles;
			sort($widgetfiles_add);
			foreach($widgetfiles_add as $widget) {			
				if(!stristr($widget, "widget.php"))
					continue;		
				
				$periodpos = strpos($widget, ".");
				$widgetname = substr($widget, 0, $periodpos);
				$nicename = $widgetname;
				$nicename = str_replace("_", " ", $nicename);
				//make the title look nice
				$nicename = ucwords($nicename);
				
				$widgettitle = $widgetname . "_title";
				$widgettitlelink = $widgetname . "_title_link";
					if ($$widgettitle != "")
					{
						//echo widget title 
						?>
						<span style="cursor: pointer;" onclick='return addWidget("<?php echo $widgetname; ?>")'>
						<u><?php echo $$widgettitle; ?></u></span><br>
						<?php 
					}
					else {?>
						<span style="cursor: pointer;" onclick='return addWidget("<?php echo $widgetname; ?>")'>
						<u><?php echo $nicename; ?></u></span><br><?php
					}
			}
		?>
		</p>
	</div>
</div>

<div id="welcomecontainer" style="display:none">
		<div id="welcome-container">
			<h1>
				<div style="float:left;width:80%;padding: 2px">
					<?=gettext("Welcome to the Dashboard page"); ?>!
				</div>
				<div onclick="domTT_close(this);showAllWidgets();" style="float:right;width:8%; cursor:pointer;padding: 5px;" >
					<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_close.gif" />
				</div>
				<div style="clear:both;"></div>
			</h1>
			<p>
			<?=gettext("This page allows you to customize the information you want to be displayed!");?><br/>
			<?=gettext("To get started click the");?> <img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"> <?=gettext("icon to add widgets.");?><br/>
			<br/>
			<?=gettext("You can move any widget around by clicking and dragging the title.");?>			
			</p>
	</div>
</div>

<form action="index.php" method="post">
<input type="hidden" value="" name="sequence" id="sequence">
<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" alt="<?=gettext("Click here to add widgets"); ?>" style="cursor: pointer;" onmouseup="domTT_activate(this, event, 'content', document.getElementById('content1'), 'type', 'velcro', 'delay', 0, 'fade', 'both', 'fadeMax', 100, 'styleClass', 'niceTitle');" />

<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_info_pkg.gif" alt="<?=gettext("Click here for help"); ?>" style="cursor: help;" onmouseup="hideAllWidgets();domTT_activate(this, event, 'content', document.getElementById('welcome-container'), 'type', 'sticky', 'closeLink', '','delay', 0, 'fade', 'both', 'fadeMax', 100, 'styleClass', 'niceTitle');" />


&nbsp;&nbsp;&nbsp;
		<input id="submit" name="submit" type="submit" style="display:none" onclick="return updatePref();" class="formbtn" value="<?=gettext("Save Settings");?>" />
</p>
</form>
<div id="niftyOutter">
	<?php
	$totalwidgets = count($widgetfiles);
	$halftotal = $totalwidgets / 2 - 2;
	$widgetcounter = 0;
	$directory = "/usr/local/www/widgets/widgets/";
	$printed = false;
	$firstprint = false;
	?> 
	<div id="col1" style="float:left;width:49%;padding-bottom:40px">		
	<?php	
		
	foreach($widgetlist as $widget) {
		
		if(!stristr($widget, "widget.php"))
					continue;
		$periodpos = strpos($widget, ".");
		$widgetname = substr($widget, 0, $periodpos);	
		if ($widgetname != ""){
			$nicename = $widgetname;
			$nicename = str_replace("_", " ", $nicename);
			
			//make the title look nice
			$nicename = ucwords($nicename);
		}
		
		if ($config['widgets'] && $pconfig['sequence'] != ""){
			switch($displayarray[$widgetcounter]){
				case "show":
					$divdisplay = "block";
					$display = "block";
					$inputdisplay = "show";					
					$showWidget = "none";
					$mindiv = "inline";
					break;
				case "hide":
					$divdisplay = "block";
					$display = "none";
					$inputdisplay = "hide";		
					$showWidget = "inline";
					$mindiv = "none";
					break;
				case "close":
					$divdisplay = "none";
					$display = "block";
					$inputdisplay = "close";			
					$showWidget = "none";
					$mindiv = "inline";
					break;
				default:
					$divdisplay = "none";
					$display = "block";
					$inputdisplay = "none";
					$showWidget = "none";
					$mindiv = "inline";
					break;
			}
		} else {
			if ($firstprint == false){
				$divdisplay = "block";
				$display = "block";
				$inputdisplay = "show";
				$showWidget = "none";
				$mindiv = "inline";
				$firstprint = true;
			} else {
				switch ($widget) {
					case "interfaces.widget.php":
					case "traffic_graphs.widget.php":
						$divdisplay = "block";
						$display = "block";
						$inputdisplay = "show";
						$showWidget = "none";
						$mindiv = "inline";
						break;
					default:
						$divdisplay = "none";
						$display = "block";
						$inputdisplay = "close";
						$showWidget = "none";
						$mindiv = "inline";
						break;
				}
			}
		}
		
		if ($config['widgets'] && $pconfig['sequence'] != ""){
			if ($colpos[$widgetcounter] == "col2" && $printed == false)
			{
				$printed = true;
				?>
				</div>
				<div id="col2" style="float:right;width:49%;padding-bottom:40px">		
				<?php
			}
		}
		else if ($widgetcounter >= $halftotal && $printed == false){
			$printed = true;
			?>
			</div>
			<div id="col2" style="float:right;width:49%;padding-bottom:40px">		
			<?php
		}
		
		?>
		<div style="clear:both;"></div>
		<div  id="<?php echo $widgetname;?>-container" class="widgetdiv" style="display:<?php echo $divdisplay; ?>;">
			<input type="hidden" value="<?php echo $inputdisplay;?>" id="<?php echo $widgetname;?>-container-input" name="<?php echo $widgetname;?>-container-input">
			<div id="<?php echo $widgetname;?>-topic" class="widgetheader" style="cursor:move">
				<div style="float:left;">
					<?php 
					
					$widgettitle = $widgetname . "_title";
					$widgettitlelink = $widgetname . "_title_link";
					if ($$widgettitle != "")
					{
						//only show link if defined
						if ($$widgettitlelink != "") {?>						
						<u><span onClick="location.href='/<?php echo $$widgettitlelink;?>'" style="cursor:pointer">
						<?php }
							//echo widget title
							echo $$widgettitle; 
						if ($$widgettitlelink != "") { ?>
						</span></u>						
						<?php }
					}
					else{		
						if ($$widgettitlelink != "") {?>						
						<u><span onClick="location.href='/<?php echo $$widgettitlelink;?>'" style="cursor:pointer">
						<?php }
						echo $nicename;
							if ($$widgettitlelink != "") { ?>
						</span></u>						
						<?php }
					}
					?>
				</div>
				<div align="right" style="float:right;">	
					<div id="<?php echo $widgetname;?>-configure" onclick='return configureWidget("<?php echo $widgetname;?>")' style="display:none; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_configure.gif" /></div>									
					<div id="<?php echo $widgetname;?>-open" onclick='return showWidget("<?php echo $widgetname;?>",true)' style="display:<?php echo $showWidget;?>; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_open.gif" /></div>	
					<div id="<?php echo $widgetname;?>-min" onclick='return minimizeWidget("<?php echo $widgetname;?>",true)' style="display:<?php echo $mindiv;?>; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_minus.gif"/></div>												
					<div id="<?php echo $widgetname;?>-close" onclick='return closeWidget("<?php echo $widgetname;?>",true)' style="display:inline; cursor:pointer" ><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_close.gif" /></div>	
				</div>
				<div style="clear:both;"></div>
			</div>
			<?php if ($divdisplay != "block") { ?>
			<div id="<?php echo $widgetname;?>-loader" style="display:<?php echo $display; ?>;">
				<br>	
					<center>
						<img src="./themes/<?= $g['theme']; ?>/images/misc/widget_loader.gif" width=25 height=25 alt="<?=gettext("Loading selected widget"); ?>...">
					</center>	
				<br>
			</div> <?php } if ($divdisplay != "block") $display = none; ?>
			<div id="<?php echo $widgetname;?>" style="display:<?php echo $display; ?>;">				
				<?php 
					if ($divdisplay == "block")
					{
						include($directory . $widget);
					}	
				 ?>
			</div>
			<div style="clear:both;"></div>
		</div>
		<?php 	
	$widgetcounter++;
		
	}//end foreach	
	?>			
		</div>
	<div style="clear:both;"></div>
</div>

<?php include("fend.inc"); ?>
	    
<script type="text/javascript">
	document.observe('dom:loaded', function(in_event)
	{		
			Sortable.create("col1", {tag:'div',dropOnEmpty:true,containment:columns,handle:'widgetheader',constraint:false,only:'widgetdiv',onChange:showSave});	
			Sortable.create("col2", {tag:'div',dropOnEmpty:true,containment:columns,handle:'widgetheader',constraint:false,only:'widgetdiv',onChange:showSave});		
	<?php if (!$config['widgets']  && $pconfig['sequence'] != ""){ ?>
			hideAllWidgets();		    
			domTT_activate('welcome1', null, 'x', 287, 'y', 107, 'content', document.getElementById('welcome-container'), 'type', 'sticky', 'closeLink', '','delay', 1000, 'fade', 'both', 'fadeMax', 100, 'styleClass', 'niceTitle');		
	<?php } ?>
	});
</script>
<?php
	//build list of javascript include files
	$jsincludefiles = array();
	$directory = "widgets/javascript/";
	$dirhandle  = opendir($directory);
	$filename = "";
	while (false !== ($filename = readdir($dirhandle))) {
   		$jsincludefiles[] = $filename;
	}
	foreach($jsincludefiles as $jsincludename) {
		if(!stristr($jsincludename, ".js"))
			continue;
		echo "<script src='{$directory}{$jsincludename}' type='text/javascript'></script>\n";	
	}
?>
</script>

</body>
</html>
