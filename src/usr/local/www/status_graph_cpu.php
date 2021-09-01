<?php
/*
 * status_graph_cpu.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2021 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

##|+PRIV
##|*IDENT=page-status-cpuload
##|*NAME=Status: CPU load
##|*DESCR=Allow access to the 'Status: CPU load' page.
##|*MATCH=status_graph_cpu.php*
##|-PRIV

$pgtitle = array(gettext("Status"), gettext("CPU Load Graph"));
require_once("guiconfig.inc");
include("head.inc");

?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("CPU Load Graph");?></h2></div>
	<div class="panel-body text-center">
		<embed src="graph_cpu.php" type="image/svg+xml"
			   width="550" height="275" pluginspage="https://www.adobe.com/svg/viewer/install/auto" />
	</div>

	<p class="text-center">
		<strong><?=gettext("Note"); ?>:</strong>
		<?=sprintf(gettext('If the graph cannot be seen, the %1$sAdobe SVG viewer%2$s may need to be installed'), '<a href="https://www.adobe.com/svg/viewer/install/" target="_blank">', '</a>')?>
	</p>
</div>

<?php
include("foot.inc");
