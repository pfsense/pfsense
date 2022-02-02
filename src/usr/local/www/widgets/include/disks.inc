<?php
/*
 * disks.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2022 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once('vendor/autoload.php');

use pfSense\Services\Filesystem\{
	Filesystem,
	Filesystems,
	Provider\SystemProvider,
};

use Nette\Utils\Html;

$disks_title = gettext('Disks');

$disks_types = array('cd9660', 'tmpfs', 'ufs', 'zfs');

$disks_widget_defaults = array(
				'disk_filter' => null,
				'autoshow_threshold' => 75);

// Initialize the Filesystems service		
$filesystems = new Filesystems();

function disks_do_widget_settings_post($post, $user_settings) {
	global $disks_types, $disks_widget_defaults, $filesystems;

	// start with widget defaults
	$pconfig = $disks_widget_defaults;

	if (isset($post['widgetkey'])) {
		$widgetkey 		= $post['widgetkey'];

		$autoshow_threshold	= $post["{$widgetkey}_autoshow_threshold"];

		$disk_filter		= $post["{$widgetkey}_disk_filter"];

		// Check if the posted threshold is valid, if so set it...
		if (isset($autoshow_threshold)
		    && is_numericint($autoshow_threshold)
		    && in_array($autoshow_threshold, range(0, 100))) {
			$pconfig['autoshow_threshold'] = $autoshow_threshold;
		}

		// Check if the posted disk_filter is valid, if so set it...
		if (!empty($disk_filter) && is_array($disk_filter)) {
			$valid_mounts = $filesystems->getMounts($disks_types);

			// Filter out any invalid mounts
			$disk_filter = array_filter($disk_filter, function($mount) use ($valid_mounts) {
				return in_array($mount, $valid_mounts);
			});

			$pconfig['disk_filter'] = implode(',', $disk_filter);
		}

		// Sync with the configuration backend...
		$user_settings['widgets'][$widgetkey] = $pconfig;

		// Save the widget settings...
		save_widget_settings($_SESSION['Username'], $user_settings['widgets'], gettext('Updated disk widget settings via dashboard.'));
	}
}

function disks_compose_health_icon(Filesystem $fs, $autoshow_threshold) {
	$used_percent = $fs->getUsedPercent();

	$isAlert = (($autoshow_threshold != 0) && ($used_percent >= $autoshow_threshold));

	$class = $isAlert ? 'fa fa-exclamation-triangle' : 'fa fa-thumb-tack';

	$title = $isAlert ? gettext('Alert') : gettext('Pinned');

	return disk_compose_icon_link($fs->getPath(), $title, $class);
}

function disks_compose_widget_table($widget_config) {
	$table = Html::el(null);

	$table->addHtml(disks_compose_widget_header());

	$table->addHtml(disks_compose_widget_user_body($widget_config));

	$table->addHtml(disks_compose_widget_tree_body());
	
	return $table->toHtml();
}

function disks_compose_widget_header() {
	$thead = Html::el('thead');

	$thead->addHtml(
		Html::el('th')
			->setText(gettext('Mount')));

	$thead->addHtml(
		Html::el('th')
			->setText(gettext('Used')));

	$thead->addHtml(
		Html::el('th')
			->setText(gettext('Size')));

	$thead->addHtml(
		Html::el('th')
			->setText(gettext('Usage'))
			->style('width', '50%'));

	return $thead;
}

function disks_compose_progressbar($now, $min = 0, $max = 100) {
	$thresholds = array(
			75 => 'progress-bar progress-bar-danger progress-bar-striped',
			50 => 'progress-bar progress-bar-warning progress-bar-striped',
			0 => 'progress-bar progress-bar-success progress-bar-striped');

	$class = current(array_filter($thresholds, function($threshold) use ($now) {
		return ($now >= $threshold);
	}, ARRAY_FILTER_USE_KEY));

	$progressbar = Html::el('div');

	$progressbar->setAttribute('class', $class);

	$progressbar->setAttribute('aria-valuenow', $now);

	$progressbar->setAttribute('aria-valuemin', $min);

	$progressbar->setAttribute('aria-valuemax', $max);

	$progressbar->style('width', "{$now}%");

	return Html::el('div')->setAttribute('class', 'progress')->setHtml($progressbar);
}

function disk_compose_icon_link($text, $title, $icon_class) {
	$i = Html::el('i');

	$i->setAttribute('id', 'root_expand');

	$i->setAttribute('class', $icon_class);

	$i->setAttribute('title', $title);

	$i->style('vertical-align', 'middle');

	$i->style('padding-right', '5px');

	return Html::el(null)->addHtml($i)->addText($text);
}

function disk_compose_root_filesystem_row() {
	global $filesystems;

	$fs = $filesystems->getRootFilesystem();

	$tr = Html::el('tr');

	$tr->setAttribute('class', "treegrid-root");

	$tr->addHtml(
		Html::el('td')
			->setText($fs->getPath()));

	$tr->addHtml(
		Html::el('td')
			->setText($fs->getUsed()));

	$tr->addHtml(
		Html::el('td')
			->setText($fs->getSize()));

	$tr->addHtml(
		Html::el('td')
			->addHtml(disks_compose_progressbar($fs->getUsedPercent()))
			->addHtml(
				Html::el('span')
					->setText("{$fs->getUsedPercent()}% of {$fs->getSize()} ({$fs->getType()})")));

	return $tr;
}

function disks_compose_widget_user_body($widget_config) {
	global $disks_types, $filesystems;

	$has_user_row = false;

	$tbody = Html::el('tbody');

	$disk_filter = explode(',', $widget_config['disk_filter']);

	$autoshow_threshold = $widget_config['autoshow_threshold'];
	
	foreach ($filesystems->getNonRootFilesystems($disks_types) as $fs) {
		if (in_array($fs->getPath(), $disk_filter)
		    || (($autoshow_threshold != 0) && ($fs->getUsedPercent() >= $autoshow_threshold))) {
			$has_user_row = true;
		
			$tr = Html::el('tr');

			$tr->addHtml(
				Html::el('td')
					->addHtml(disks_compose_health_icon($fs, $autoshow_threshold)));

			$tr->addHtml(
				Html::el('td')
					->setText($fs->getUsed()));

			$tr->addHtml(
				Html::el('td')
					->setText($fs->getSize()));

			$tr->addHtml(
				Html::el('td')
					->addHtml(disks_compose_progressbar($fs->getUsedPercent()))
					->addHtml(
						Html::el('span')
							->setText("{$fs->getUsedPercent()}% of {$fs->getSize()} ({$fs->getType()})")));
			
			$tbody->addHtml($tr);
		}
	}

	return $has_user_row ? $tbody : null;
}

function disks_compose_widget_tree_body() {
	global $disks_types, $filesystems;

	$tbody = Html::el('tbody');

	$tbody->addHtml(disk_compose_root_filesystem_row());

	foreach ($filesystems->getNonRootFilesystems($disks_types) as $fs) {
		$tr = Html::el('tr');

		$tr->setAttribute('class', "treegrid-{$fs->getHtmlClass()} treegrid-parent-{$fs->getParentHtmlClass()}");

		$tr->addHtml(
			Html::el('td')
				->setHtml($fs->getPath()));

		$tr->addHtml(
			Html::el('td')
				->setText($fs->getUsed()));

		$tr->addHtml(
			Html::el('td')
				->setText($fs->getSize()));

		$tr->addHtml(
			Html::el('td')
				->addHtml(disks_compose_progressbar($fs->getUsedPercent()))
				->addHtml(
					Html::el('span')
						->setText("{$fs->getUsedPercent()}% of {$fs->getSize()} ({$fs->getType()})")));

		$tbody->addHtml($tr);
	}

	return $tbody;
}

function disks_get_nonroot_filesystems() {
	global $disks_types, $filesystems;

	return $filesystems->getNonRootFilesystems($disks_types);
}

function disks_cache_invalidate($force = false, $chancef = 0.1) {
	global $filesystems;

	if (((rand() / getrandmax()) < $chancef) || $force) {
		$filesystems->flushProviderCache();
	}
}

?>