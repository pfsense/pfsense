<?php

require_once("guiconfig.inc");

/*
	pfSense_MODULE:	shell
*/
// Fetch a list of directories and files inside a given directory
function get_content($dir) {
	$dirs  = array();
	$files = array();

	clearstatcache();
	$fd = @opendir($dir);

	while($entry = @readdir($fd)) {
		if($entry == ".")                 continue;
		if($entry == ".." && $dir == "/") continue;

		if(is_dir("{$dir}/{$entry}"))
			array_push($dirs, $entry);
		else
			array_push($files, $entry);
	}

	@closedir($fd);

	natsort($dirs);
	natsort($files);

	return array($dirs, $files);
}

$path = realpath(strlen($_GET['path']) > 0 ? $_GET['path'] : "/");
if(is_file($path))
	$path = dirname($path);

// ----- header -----
?>
<table width="100%">
	<tr>
		<td class="fbHome" width="25px" align="left">
			<img onClick="jQuery('#fbTarget').val('<?=$realDir?>'); fbBrowse('/');" src="/filebrowser/images/icon_home.gif" alt="Home" title="Home" />
		</td>
		<td><b><?=$path;?></b></td>
		<td class="fbClose" align="right">
			<img onClick="jQuery('#fbBrowser').fadeOut();" border="0" src="/filebrowser/images/icon_cancel.gif" alt="Close" title="Close" />
		</td>
	</tr>
	<tr>
		<td id="fbCurrentDir" colspan="3" class="vexpl" align="left">
<?php

// ----- read contents -----
if(is_dir($path)) {
	list($dirs, $files) = get_content($path);
?>
			
		</td>
	</tr>
<?php
}
else {
?>
			Directory does not exist.
		</td>
	</tr>
</table>
<?php
	exit;
}

// ----- directories -----
foreach($dirs as $dir):
	$realDir = realpath("{$path}/{$dir}");
?>
	<tr>
		<td></td>
		<td class="fbDir vexpl" id="<?=$realDir;?>" align="left">
			<div onClick="jQuery('#fbTarget').val('<?=$realDir?>'); fbBrowse('<?=$realDir?>');">
				<img src="/filebrowser/images/folder_generic.gif" />
				&nbsp;<?=$dir;?>
			</div>
		</td>
		<td></td>
	</tr>
<?php
endforeach;

// ----- files -----
foreach($files as $file):
	$ext = strrchr($file, ".");

	switch ($ext) {
	   case ".css":
	   case ".html":
	   case ".xml":
		$type = "code";
		break;
	   case ".rrd":
		$type = "database";
		break;
	   case ".gif":
	   case ".jpg":
	   case ".png":
		$type = "image";
		break;
	   case ".js":
		 $type = "js";
		break;
	   case ".pdf":
		$type = "pdf";
		break;
	   case ".inc":
	   case ".php":
		$type = "php";
		break;
	   case ".conf":
	   case ".pid":
	   case ".sh":
		$type = "system";
		break;
	   case ".bz2":
	   case ".gz":
	   case ".tgz":
	   case ".zip":
		$type = "zip";
		break;
	   default:
		$type = "generic";
	}

	$fqpn = "{$path}/{$file}";

	if(is_file($fqpn)) {
		$fqpn = realpath($fqpn);
		$size = sprintf("%.2f KiB", filesize($fqpn) / 1024);
	}
	else
		$size = "";

?>
	<tr>
		<td></td>
		<td class="fbFile vexpl" id="<?=$fqpn;?>" align="left">
			<?php $filename = str_replace("//","/", "{$path}/{$file}"); ?>
			<div onClick="jQuery('#fbTarget').val('<?=$filename?>'); loadFile(); jQuery('#fbBrowser').fadeOut();">
				<img src="/filebrowser/images/file_<?=$type;?>.gif" alt="" title="">
				&nbsp;<?=$file;?>
			</div>
		</td>
		<td align="right" class="vexpl">
			<?=$size;?>
		</td>
	</tr>
<?php
endforeach;
?>
</table>
