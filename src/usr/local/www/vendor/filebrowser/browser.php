<?php

require_once("guiconfig.inc");

/*
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2013-2020 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 */
// Fetch a list of directories and files inside a given directory
function get_content($dir) {
	$dirs  = array();
	$files = array();

	clearstatcache();
	$fd = @opendir($dir);

	while ($entry = @readdir($fd)) {
		if ($entry == ".") {
			continue;
		}
		if ($entry == ".." && $dir == "/") {
			continue;
		}
		if (is_dir("{$dir}/{$entry}")) {
			array_push($dirs, $entry);
		} else {
			array_push($files, $entry);
		}
	}

	@closedir($fd);

	natsort($dirs);
	natsort($files);

	return array($dirs, $files);
}

$path = realpath(strlen($_GET['path']) > 0 ? $_GET['path'] : "/");
if (is_file($path)) {
	$path = dirname($path);
}

// ----- header -----
?>
<table width="100%">
	<tr>
		<td class="fbHome text-left" width="25px">
			<img onClick="$('#fbTarget').val('<?=$realDir?>'); fbBrowse('/');" src="/vendor/filebrowser/images/icon_home.gif" alt="Home" title="Home" />
		</td>
		<td><b><?=$path;?></b></td>
		<td class="fbClose text-right">
			<img onClick="$('#fbBrowser').fadeOut();" border="0" src="/vendor/filebrowser/images/icon_cancel.gif" alt="Close" title="Close" />
		</td>
	</tr>
	<tr>
		<td id="fbCurrentDir" colspan="3" class="vexpl text-left">
<?php

// ----- read contents -----
if (is_dir($path)) {
	list($dirs, $files) = get_content($path);
?>

		</td>
	</tr>
<?php
} else {
?>
			Directory does not exist.
		</td>
	</tr>
</table>
<?php
	exit;
}

// ----- directories -----
foreach ($dirs as $dir):
	$realDir = realpath("{$path}/{$dir}");
?>
	<tr>
		<td></td>
		<td class="fbDir vexpl text-left" id="<?=$realDir;?>">
			<div onClick="$('#fbTarget').val('<?=$realDir?>'); fbBrowse('<?=$realDir?>');">
				<img src="/vendor/filebrowser/images/folder_generic.gif" />
				&nbsp;<?=$dir;?>
			</div>
		</td>
		<td></td>
	</tr>
<?php
endforeach;

// ----- files -----
foreach ($files as $file):
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

	if (is_file($fqpn)) {
		$fqpn = realpath($fqpn);
		$size = sprintf("%.2f KiB", filesize($fqpn) / 1024);
	} else {
		$size = "";
	}

?>
	<tr>
		<td></td>
		<td class="fbFile vexpl text-left" id="<?=$fqpn;?>">
			<?php $filename = htmlspecialchars(addslashes(str_replace("//","/", "{$path}/{$file}"))); ?>
			<div onClick="$('#fbTarget').val('<?=$filename?>'); loadFile(); $('#fbBrowser').fadeOut();">
				<img src="/vendor/filebrowser/images/file_<?=$type;?>.gif" alt="" title="">
				&nbsp;<?=$file;?>
			</div>
		</td>
		<td class="vexpl text-right">
			<?=$size;?>
		</td>
	</tr>
<?php
endforeach;
?>
</table>
