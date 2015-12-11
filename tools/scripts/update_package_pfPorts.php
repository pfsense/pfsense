#!/usr/local/bin/php -q
<?php
/* ====================================================================
 *  Copyright (c) 2004-2015 Electric Sheep Fencing, LLC. All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *  3. All advertising materials mentioning features or use of this software
 *     must display the following acknowledgment:
 *     "This product includes software developed by the pfSense Project
 *     for use in the pfSense® software distribution. (http://www.pfsense.org/).
 *
 *  4. The names "pfSense" and "pfSense Project" must not be used to
 *     endorse or promote products derived from this software without
 *     prior written permission. For written permission, please contact
 *     coreteam@pfsense.org.
 *
 *  5. Products derived from this software may not be called "pfSense"
 *     nor may "pfSense" appear in their names without prior written
 *     permission of the Electric Sheep Fencing, LLC.
 *
 *  6. Redistributions of any form whatsoever must retain the following
 *     acknowledgment:
 *
 *  "This product includes software developed by the pfSense Project
 *  for use in the pfSense software distribution (http://www.pfsense.org/).
 *
 *  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
 *  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 *  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
 *  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 *  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 *  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 *  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 *  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 *  OF THE POSSIBILITY OF SUCH DAMAGE.
 *  ====================================================================
 */

$opts  = "x:"; // Path to XML file
$opts .= "p:"; // Package name to build (optional)
$opts .= "t:"; // Path to ports tree repo

$options = getopt($opts);

if (!isset($options['x']))
	usage();

// Set the XML filename that we are processing
$xml_filename = $options['x'];

if (!file_exists($xml_filename))
	die("Package XML file not found");

$scripts_dir = realpath(dirname($argv[0]));
$tools_dir = realpath($scripts_dir . '/..');
$builder_dir = realpath($tools_dir . '/..');
$src_dir = realpath($builder_dir . '/src');
$packages_dir = dirname(realpath($xml_filename));
$ports_dir = $options['t'];

if (!file_exists($ports_dir) || !is_dir($ports_dir))
	die("Ports tree {$ports_dir} not found!\n");

$product_name = trim(shell_exec("cd {$builder_dir} && ./build.sh -V PRODUCT_NAME"));

if (is_dir("{$src_dir}/etc/inc")) {
	require_once("{$src_dir}/etc/inc/util.inc");
	require_once("{$src_dir}/etc/inc/pfsense-utils.inc");
	require_once("{$src_dir}/etc/inc/xmlparse.inc");
} else {
	die("Missing include directories\n");
}

$pkgs = parse_xml_config_pkg($xml_filename, "pfsensepkgs");
if (!$pkgs) {
	echo "!!! An error occurred while trying to process {$xml_filename}.  Exiting.\n";
	exit;
}

unset($pkg_list);
if (isset($options['p'])) {
	$pkg_list = array();
	if (is_string($options['p']))
		$pkg_list[] = strtolower($options['p']);
	else if (is_array($options['p']))
		$pkg_list = array_map('strtolower', $options['p']);
}

$pfs_version = trim(file_get_contents("{$src_dir}/etc/version"));

foreach ($pkgs['packages']['package'] as $pkg) {
	if (isset($pkg_list) && !in_array(strtolower($pkg['name']), $pkg_list))
		continue;

	if (isset($pkg['maximum_version']) && !empty($pkg['maximum_version'])) {
		if (version_compare_numeric($pfs_version, $pkg['maximum_version']) > 0) {
			echo "!!! Ignoring {$pkg['name']}, maximum version is {$pkg['maximum_version']}\n";
			continue;
		}
	}

	if (isset($pkg['required_version']) && !empty($pkg['required_version'])) {
		if (version_compare_numeric($pfs_version, $pkg['required_version']) < 0) {
			echo "!!! Ignoring {$pkg['name']}, required version is {$pkg['required_version']}\n";
			continue;
		}
	}

	create_port($pkg);
}

function fix_php_calls($file) {
	if (!file_exists($file)) {
		return;
	}

	if (!preg_match('/\.(php|inc)$/', $file)) {
		return;
	}

	$content = file_get_contents($file);
	$new_content = preg_replace('/\/usr\/local\/bin\/php/', '/usr/local/bin/php-cgi', $content);
	file_put_contents($file, $new_content);
	unset($content, $new_content);
}

function create_port($pkg) {
	global $ports_dir, $tools_dir, $packages_dir, $product_name;

	if (isset($pkg['internal_name'])) {
		$pkg_name = $pkg['internal_name'];
	} else {
		$pkg_name = $pkg['name'];
	}

	if (empty($pkg_name)) {
		echo "!!! Error: Package name cannot be empty\n";
		exit(1);
	}

	if (!preg_match('/^[a-zA-Z0-9\.\-_]+$/', $pkg_name)) {
		echo "!!! Error: Package name '{$pkg_name}' is invalid\n";
		exit(1);
	}

	if (isset($pkg['port_category']) && !empty($pkg['port_category']))
		$category = $pkg['port_category'];
	else
		$category = 'sysutils';

	$port_name_prefix = $product_name . '-pkg-';
	$port_name = $port_name_prefix . $pkg_name;
	$port_path = $ports_dir . '/' . $category . '/' . $port_name;

	if (is_dir($port_path)) {
		$_gb = exec("rm -rf {$port_path}");
	}

	mkdir($port_path . "/files", 0755, true);

	if (isset($pkg['descr']) && !empty($pkg['descr'])) {
		$pkg_descr = $pkg['descr'];
	} else {
		/* provide a generic description when it's not available */
		$pkg_descr = "{$pkg_name} {$product_name} package";
	}

	if (isset($pkg['pkginfolink']) && !empty($pkg['pkginfolink'])) {
		$pkg_descr .= "\n\nWWW: {$pkg['pkginfolink']}";
	}
	$pkg_descr .= "\n";

	file_put_contents($port_path . "/pkg-descr.tmp", $pkg_descr);
	unset($pkg_descr);

	$_gb = exec("/usr/bin/fmt -w 80 {$port_path}/pkg-descr.tmp > {$port_path}/pkg-descr 2>/dev/null");
	@unlink("{$port_path}/pkg-descr.tmp");

	if (isset($pkg['after_install_info']) && !empty($pkg['after_install_info'])) {
		file_put_contents($port_path . "/pkg-message", $pkg['after_install_info'] . "\n");
	}

	$pkg_install = file_get_contents($tools_dir . "/templates/pkg-install.in");
	file_put_contents($port_path . "/files/pkg-install.in", $pkg_install);
	unset($pkg_install);

	$pkg_deinstall = file_get_contents($tools_dir . "/templates/pkg-deinstall.in");
	file_put_contents($port_path . "/files/pkg-deinstall.in", $pkg_deinstall);
	unset($pkg_deinstall);

	$config_file = preg_replace('/^https*:\/\/[^\/]+\/packages\//', '', $pkg['config_file']);

	if (!file_exists($packages_dir . '/' . $config_file)) {
		echo "!!! Error, config file {$config_file} not found\n";
		exit(1);
	}

	$pkg_config = parse_xml_config_pkg($packages_dir . '/' . $config_file, "packagegui");

	if (empty($pkg_config)) {
		echo "!!! Error, config file {$config_file} is invalid\n";
		exit(1);
	}

	if (!is_dir($port_path . '/files/usr/local/pkg')) {
		mkdir($port_path . '/files/usr/local/pkg', 0755, true);
	}
	copy($packages_dir . '/' . $config_file, $port_path . '/files/usr/local/pkg/' . basename($config_file));

	$plist_files = array('pkg/' . basename($config_file));
	$plist_dirs = array();
	$mkdirs = array('${MKDIR} ${STAGEDIR}${PREFIX}/pkg');
	$install = array('${INSTALL_DATA} -m 0644 ${FILESDIR}${PREFIX}/pkg/' . basename($config_file) . " \\\n\t\t" . '${STAGEDIR}${PREFIX}/pkg');
	if (!empty($pkg_config['additional_files_needed'])) {
		foreach ($pkg_config['additional_files_needed'] as $item) {
			if (is_array($item['item'])) {
				$item['item'] = $item['item'][0];
			}
			if (isset($item['do_not_add_to_port']))
				continue;
			$file_relpath = preg_replace('/^https*:\/\/[^\/]+\/packages\//', '', $item['item']);
			if (!file_exists($packages_dir . '/' . $file_relpath)) {
				echo "!!! Error: Additional file needed {$file_relpath} not found\n";
				exit(1);
			}

			if (!is_dir($port_path . '/files' . $item['prefix'])) {
				mkdir($port_path . '/files' . $item['prefix'], 0755, true);
			}

			copy($packages_dir . '/' . $file_relpath, $port_path . '/files' . $item['prefix'] . '/' . basename($file_relpath));
			fix_php_calls($port_path . '/files' . $item['prefix'] . '/' . basename($file_relpath));
			/* Remove /usr/local/ from prefix */
			$plist_entry = preg_replace('/^\/usr\/local\//', '', $item['prefix']);
			$plist_entry = preg_replace('/\/*$/', '', $plist_entry);

			if (substr($plist_entry, 0, 1) == '/' &&
			    !in_array("@dir {$plist_entry}", $plist_dirs)) {
				$plist_dirs[] = "@dir {$plist_entry}";
			}

			$plist_entry .= '/' . basename($item['item']);
			if (!in_array($plist_entry, $plist_files)) {
				$plist_files[] = $plist_entry;
			}
			unset($plist_entry);

			if (preg_match('/^\/usr\/local\//', $item['prefix'])) {
				$mkdirs_entry = preg_replace('/^\/usr\/local\//', '${PREFIX}/', $item['prefix']);
			} else {
				$mkdirs_entry = $item['prefix'];
			}
			$mkdirs_entry = preg_replace('/\/*$/', '', $mkdirs_entry);

			$install_entry = '${INSTALL_DATA} ';

			if (isset($item['chmod']) && !empty($item['chmod'])) {
				$install_entry .= "-m {$item['chmod']} ";
			}

			$install_entry .= '${FILESDIR}' . $mkdirs_entry . '/' . basename($item['item']) . " \\\n\t\t";
			$install_entry .= '${STAGEDIR}' . $mkdirs_entry;
			$mkdirs_entry = '${MKDIR} ${STAGEDIR}' . $mkdirs_entry;

			if (!in_array($mkdirs_entry, $mkdirs)) {
				$mkdirs[] = $mkdirs_entry;
			}
			if (!in_array($install_entry, $install)) {
				$install[] = $install_entry;
			}

			unset($install_entry, $mkdirs_entry);
		}
	}

	if (!is_dir($port_path . '/files/usr/local/share/' . $port_name)) {
		mkdir($port_path . '/files/usr/local/share/' . $port_name, 0755, true);
	}

	$info['package'][] = $pkg;
	$info_xml = dump_xml_config($info, 'pfsensepkgs');
	file_put_contents($port_path . '/files/usr/local/share/' . $port_name . '/info.xml', $info_xml);
	unset($info, $info_xml);
	$plist_files[] = '%%DATADIR%%/info.xml';
	$mkdirs[] = '${MKDIR} ${STAGEDIR}${DATADIR}';
	$install[] = '${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml ' . "\\\n\t\t" . '${STAGEDIR}${DATADIR}';

	$version = $pkg['version'];

	/* Detect PORTEPOCH */
	if (($pos = strpos($version, ',')) != FALSE) {
		$epoch = substr($version, $pos+1);
		$version = substr($version, 0, $pos);
	}

	/* Detect PORTREVISION */
	if (($pos = strpos($version, '_')) != FALSE) {
		$revision = substr($version, $pos+1);
		$version = substr($version, 0, $pos);
	}

	$makefile = array();
	$makefile[] = '# $FreeBSD$';
	$makefile[] = '';
	$makefile[] = "PORTNAME=\t{$port_name}";
	$makefile[] = "PORTVERSION=\t{$version}";
	if (isset($revision)) {
		$makefile[] = "PORTREVISION=\t{$revision}";
	}
	if (isset($epoch)) {
		$makefile[] = "PORTEPOCH=\t{$epoch}";
	}
	// XXX: use categories from xml */
	$makefile[] = "CATEGORIES=\t{$category}";
	$makefile[] = "MASTER_SITES=\t# empty";
	$makefile[] = "DISTFILES=\t# empty";
	$makefile[] = "EXTRACT_ONLY=\t# empty";
	$makefile[] = "";
	$makefile[] = "MAINTAINER=\tcoreteam@pfsense.org";
	// XXX: Provide comment on xml */
	$makefile[] = "COMMENT=\t{$product_name} package {$pkg_name}";
	if (isset($pkg['run_depends']) && !empty($pkg['run_depends'])) {
		$run_depends = array();
		foreach (preg_split('/\s+/', trim($pkg['run_depends'])) as $depend) {
			list($file_depend, $port_depend) = explode(':', $depend);
			$file_depend = '${LOCALBASE}/' . $file_depend;
			$port_depend = '${PORTSDIR}/' . $port_depend;
			$run_depends[] = $file_depend . ':' . $port_depend;
		}
		if (!empty($run_depends)) {
			$makefile[] = "";
			$first = true;
			foreach ($run_depends as $run_depend) {
				if ($first) {
					$makefile_entry = "RUN_DEPENDS=\t" . $run_depend;
					$first = false;
				} else {
					$makefile_entry .= " \\\n\t\t" . $run_depend;
				}
			}
			$makefile[] = $makefile_entry;
			unset($makefile_entry);
		}
		unset($run_depends);
	}
	if (isset($pkg['lib_depends']) && !empty($pkg['lib_depends'])) {
		$lib_depends = array();
		foreach (preg_split('/\s+/', trim($pkg['lib_depends'])) as $depend) {
			list($lib_depend, $port_depend) = explode(':', $depend);
			$port_depend = '${PORTSDIR}/' . $port_depend;
			$lib_depends[] = $lib_depend . ':' . $port_depend;
		}
		if (!empty($lib_depends)) {
			$makefile[] = "";
			$first = true;
			foreach ($lib_depends as $lib_depend) {
				if ($first) {
					$makefile_entry = "LIB_DEPENDS=\t" . $lib_depend;
					$first = false;
				} else {
					$makefile_entry .= " \\\n\t\t" . $lib_depend;
				}
			}
			$makefile[] = $makefile_entry;
			unset($makefile_entry);
		}
		unset($run_depends);
	}
	if (isset($pkg['port_uses']) && !empty($pkg['port_uses'])) {
		$makefile[] = "";
		foreach (preg_split('/\s+/', trim($pkg['port_uses'])) as $port_use) {
			$port_use = preg_replace('/=/', "=\t", $port_use);
			$makefile[] = $port_use;
		}
	}
	$conflicts = '';
	if (isset($pkg['noembedded'])) {
		$conflicts = $product_name . '-base-nanobsd-[0-9]*';
	}
	if (isset($pkg['conflicts']) && !empty($pkg['conflicts'])) {
		foreach (preg_split('/[\s\t]+/', $pkg['conflicts']) as $conflict) {
			$conflicts = trim($conflicts . ' ' . $port_name_prefix . $conflict . '-[0-9]*');
		}
	}
	if (!empty($conflicts)) {
		$makefile[] = "";
		$makefile[] = "CONFLICTS=\t" . $conflicts;
	}
	$makefile[] = "";
	$makefile[] = "NO_BUILD=\tyes";
	$makefile[] = "NO_MTREE=\tyes";
	$makefile[] = "";
	$makefile[] = "SUB_FILES=\tpkg-install pkg-deinstall";
	$makefile[] = "SUB_LIST=\tPORTNAME=\${PORTNAME}";
	$makefile[] = "";
	$makefile[] = "do-extract:";
	$makefile[] = "\t\${MKDIR} \${WRKSRC}";
	$makefile[] = "";
	$makefile[] = "do-install:";
	foreach ($mkdirs as $item) {
		$makefile[] = "\t" . $item;
	}
	foreach ($install as $item) {
		$makefile[] = "\t" . $item;
	}
	$makefile[] = "";
	$makefile[] = ".include <bsd.port.mk>";

	file_put_contents($port_path . '/Makefile', implode("\n", $makefile) . "\n");
	unset($makefile);

	file_put_contents($port_path . '/pkg-plist', implode("\n", $plist_files) . "\n");
	if (!empty($plist_dirs)) {
		file_put_contents($port_path . '/pkg-plist', implode("\n", $plist_dirs) . "\n", FILE_APPEND);
	}
	unset($plist_files, $plist_dirs);
}

function usage() {
	global $argv;
	echo "Usage: {$argv[0]} -x <path to pkg xml> -t <path to ports tree> [-p <package name>]\n";
	exit;
}
?>
