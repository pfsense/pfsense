<?php
/*
 * csrf_error.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2020 Rubicon Communications, LLC (Netgate)
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

header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');

require_once('auth.inc');
$pgtitle = array(gettext("CSRF Error"));

$data = '';
foreach (csrf_flattenpost($_POST) as $key => $value) {
	if ($key == $GLOBALS['csrf']['input-name']) continue;
	$data .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'" />';
}

$logincssfile = "#770101";
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="/vendor/font-awesome/css/font-awesome.min.css?v=<?=filemtime('/usr/local/www/vendor/font-awesome/css/font-awesome.min.css')?>">
		<link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css" type="text/css">
		<link rel="stylesheet" href="/css/login.css?v=<?=filemtime('/usr/local/www/css/login.css')?>" type="text/css">
		<title><?=gettext("CSRF Error"); ?></title>
		<script type="text/javascript">
			//<![CDATA{
			var events = events || [];
			//]]>
		</script>
	</head>

	<body id="error" >
		<div id="total">
			<header>
				<div id="headerrow">
					<div class="row">
						<div class="col-sm-4">
							<div id="logodiv" style="text-align:center" class="nowarning">
								<?php include("/usr/local/www/logo.svg"); ?>
							</div>
						</div>
						<div class="col-sm-4 nowarning msgbox text-center text-danger">
							CSRF check failed
						</div>
					</div>
				</div>
			</header>

			<div style="background: <?=$logincssfile?>;" class="pagebodywarn">
				<div class="col-sm-2"></div>
				<div class="col-sm-6 offset-md-4 logoCol">
					<div class="loginCont center-block">
						<p>Missing or expired CSRF token</p>
						<p>Form session may have expired, cookies may not be enabled, or possible CSRF-based attack.</p>
						<p>Resubmitting this request may put the firewall at risk or lead to unintended behavior.</p>
						<form method='post' action=''>
							<?=$data?>
							<input type="checkbox" id="bypass" /> I understand this warning and wish to resubmit the form data.
							<br/>
							<button class="btn btn-danger btn-sm" type="submit" name="submit" id="submit" value="<?=gettext("Try again")?>" disabled>
								<i class="fa fa-exclamation-triangle icon-embed-btn"></i>
								<?=gettext("Resubmit Request with New Token")?>
							</button>
						</form>
						<?php if (!empty($tokens)): ?>
						<p>Debug: <?= $tokens ?></p>
						<?php endif; ?>
					</div>
				</div>
				<div class="col-sm-2"></div>
			</div>

			<footer id="3">
			<div id="footertext">
					<p class="text-muted">
						<?=print_credit()?>
					</p>
				</div>
			</footer>
		</div>
	<script type="text/javascript">
	//<![CDATA[
	events.push(function() {
		$('#bypass').click(function () {
			enable = ! $('#bypass').prop('checked');
			disableInput('submit', enable);
		});
	});
	//]]>
	</script>
	<script src="/vendor/jquery/jquery-3.5.1.min.js?v=<?=filemtime('/usr/local/www/vendor/jquery/jquery-3.5.1.min.js')?>"></script>
	<script src="/vendor/jquery-ui/jquery-ui-1.12.1.min.js?v=<?=filemtime('/usr/local/www/vendor/jquery-ui/jquery-ui-1.12.1.min.js')?>"></script>
	<script src="/vendor/bootstrap/js/bootstrap.min.js?v=<?=filemtime('/usr/local/www/vendor/bootstrap/js/bootstrap.min.js')?>"></script>
	<script src="/js/pfSense.js?v=<?=filemtime('/usr/local/www/js/pfSense.js')?>"></script>
	<script src="/js/pfSenseHelpers.js?v=<?=filemtime('/usr/local/www/js/pfSenseHelpers.js')?>"></script>
	</body>
</html>
