<div class="log-header">
    <span class="log-action-mini-header">Act</span>
    <span class="log-interface-mini-header">IF</span>
    <span class="log-source-mini-header">Source</span>
    <span class="log-destination-mini-header">Destination</span>
    <span class="log-protocol-mini-header">Proto</span>
</div>
<?php $counter=0; foreach ($filterlog as $filterent): ?>
<?php
	if(isset($config['syslog']['reverse'])) {
		/* honour reverse logging setting */
		if($counter == 0)
			$activerow = " id=\"firstrow\"";
		else
			$activerow = "";

	} else {
		/* non-reverse logging */
		if($counter == count($filterlog))
			$activerow = " id=\"firstrow\"";
		else
			$activerow = "";
	}
?>
<div class="log-entry-mini" <?php echo $activerow; ?> style="clear:both;">
	<span class="log-action-mini" nowrap>
	<?php
		if (strstr(strtolower($filterent['act']), "p"))
			$img = "/themes/metallic/images/icons/icon_pass.gif";
		else if(strstr(strtolower($filterent['act']), "r"))
			$img = "/themes/metallic/images/icons/icon_reject.gif";
		else
			$img = "/themes/metallic/images/icons/icon_block.gif";
	?>
	&nbsp;<img border="0" src="<?=$img;?>">&nbsp;</span>
	<span class="log-interface-mini" ><?=htmlspecialchars(convert_real_interface_to_friendly_interface_name($filterent['interface']));?></span>
	<span class="log-source-mini" ><?=htmlspecialchars($filterent['src']);?></span>
	<span class="log-destination-mini" ><?=htmlspecialchars($filterent['dst']);?></span>
	<span class="log-protocol-mini" ><?=htmlspecialchars($filterent['proto']);?></span>
</div>
<?php $counter++; endforeach; ?>