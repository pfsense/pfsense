<table width="100%" border="0" cellspacing="0" cellpadding="0">
	<tbody>
        <tr>
            <td width="15%" class="vncellt"><?=gettext("Current User");?></td>
            <td width="75%" class="listr"><?= $HTTP_SERVER_VARS['AUTH_USER']; ?></td>
        </tr>
		<tr>
			<td width="15%" class="vncellt"><?=gettext("Name");?></td>
			<td width="75%" class="listr"><?php echo $config['system']['hostname'] . "." . $config['system']['domain']; ?></td>
		</tr>
		<tr>
			<td width="15%" valign="top" class="vncellt"><?=gettext("Version");?></td>
			<td width="75%" class="listr">
				<strong><?php readfile("/etc/version"); ?></strong>
				<br />
				built on <?php readfile("/etc/version.buildtime"); ?>
			</td>
		</tr>
		<tr>
			<td width="15%" class="vncellt"><?=gettext("Platform");?></td>
			<td width="75%" class="listr"><?=htmlspecialchars($g['platform']);?></td>
		</tr>
		<tr>
			<td width="15%" class="vncellt"><?=gettext("CPU Type");?></td>
			<td width="75%" class="listr">
			<?php 
				$cpumodel = "";
				exec("/sbin/sysctl -n hw.model", $cpumodel);
				$cpumodel = implode(" ", $cpumodel);
				echo (htmlspecialchars($cpumodel)); ?>
			</td>
		</tr>
		<?php if ($hwcrypto): ?>
		<tr>
			<td width="15%" class="vncellt"><?=gettext("Hardware crypto");?></td>
			<td width="75%" class="listr"><?=htmlspecialchars($hwcrypto);?></td>
		</tr>
		<?php endif; ?>
        <tr>
            <td width="15%" class="vncellt"><?=gettext("Uptime");?></td>
            <td width="75%" class="listr">
                <div id="uptime"><?= htmlspecialchars(get_uptime()); ?></div>
            </td>
        </tr>			
		 <tr>
             <td width="30%" class="vncellt"><?=gettext("DNS server(s)");?></td>
             <td width="70%" class="listr">
					<?php
						$dns_servers = get_dns_servers();
						foreach($dns_servers as $dns) {
							echo "{$dns}<br>";
						}
					?>
			</td>
		</tr>	
		<?php if ($config['revision']): ?>
		<tr>
			<td width="15%" class="vncellt"><?=gettext("Last config change");?></td>
			<td width="75%" class="listr"><?= htmlspecialchars(date("D M j G:i:s T Y", $config['revision']['time']));?></td>
		</tr>
		<?php endif; ?>
        <tr>
            <td width="15%" class="vncellt"><?=gettext("State table size");?></td>
            <td width="75%" class="listr">
                <div id="pfstate"><?= htmlspecialchars(get_pfstate()); ?></div>
                <a href="diag_dump_states.php"><?=gettext("Show states");?></a>
            </td>
        </tr>
		<tr>
            <td width="15%" class="vncellt"><?=gettext("CPU usage history");?></td>
            <td width="75%" class="listr">
                <div class="GraphLink" id="GraphOutput"></div>
            </td>
        </tr>
        <tr>
            <td width="15%" class="vncellt"><?=gettext("CPU usage");?></td>
            <td width="75%" class="listr">
                <?php $cpuUsage = "0"; ?>
                <img src="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif" height="15" name="cpuwidtha" id="cpuwidtha" width="<?= $cpuUsage; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" height="15" name="cpuwidthb" id="cpuwidthb" width="<?= (100 - $cpuUsage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
                &nbsp;
                <div id="cpumeter">(<?=gettext("Updating in 5 seconds");?>)</div>
            </td>
        </tr>
        <tr>
            <td width="15%" class="vncellt"><?=gettext("Memory usage");?></td>
            <td width="75%" class="listr">
                <?php $memUsage = mem_usage(); ?>
                <img src="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif" height="15" name="memwidtha" id="memwidtha" width="<?= $memUsage; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" height="15" name="memwidthb" id="memwidthb" width="<?= (100 - $memUsage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
                &nbsp;
                <div id="memusagemeter"><?= $memUsage.'%'; ?></div>
            </td>
        </tr>
		<?php if($showswap == true): ?>
		<tr>
			<td width="15%" class="vncellt"><?=gettext("SWAP usage");?></td>
			<td width="75%" class="listr">
				<?php $swapusage = swap_usage(); ?>
				<img src="./themes/<?= $g['theme']; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif" height="15" width="<?= $swapUsage; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif" height="15" width="<?= (100 - $swapUsage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g['theme']; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="swapusagemeter" id="swapusagemeter" value="<?= $swapusage.'%'; ?>" />
			</td>
		</tr>
		<?php endif; ?>
<?php
		if(has_temp()):
?>
		<tr>
			<td width='15%' class='vncellt'><?=gettext("Temperature");?></td>
			<td width='75%' class='listr'>
				<?php $temp = get_temp(); ?>
				<img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_blue.gif" height="15" name="tempwidtha" id="tempwidtha" width="<?= $temp; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_gray.gif" height="15" name="tempwidthb" id="tempwidthb" width="<?= (100 - $temp); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
				&nbsp;
				<input style="border: 0px solid white;" size="30" name="tempmeter" id="tempmeter" value="<?= $temp."C"; ?>" />
			</td>
		</tr>
		<?php endif; ?>
        <tr>
            <td width="15%" class="vncellt"><?=gettext("Disk usage");?></td>
            <td width="75%" class="listr">
                <?php $diskusage = disk_usage(); ?>
                <img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_left.gif" height="15" width="4" border="0" align="middle" alt="left bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_blue.gif" height="15" name="diskwidtha" id="diskwidtha" width="<?= $diskusage; ?>" border="0" align="middle" alt="red bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_gray.gif" height="15" name="diskwidthb" id="diskwidthb" width="<?= (100 - $diskusage); ?>" border="0" align="middle" alt="gray bar" /><img src="./themes/<?= $g["theme"]; ?>/images/misc/bar_right.gif" height="15" width="5" border="0" align="middle" alt="right bar" />
                &nbsp;
                <div id="diskusagemeter"><?= $diskusage.'%'; ?></div>
            </td>
        </tr> 
		<tr>
            <td width="15%" class="vncellt"><?=gettext("webConfigurator lock");?></td>
            <td width="75%" class="listr">
                <?php
                    file_exists("{$g['tmp_path']}/webconfigurator.lock") ? $lock = "{$g['tmp_path']}/webconfigurator.lock" : $lock = "none";
                ?>
                <?= $lock ?>&nbsp;&nbsp;
                <?php if(hasLockAbility($HTTP_SERVER_VARS['AUTH_USER']) && $lock <> "none"): ?>
                <input type="button" title="Delete" name="delete_lock" id="delete_lock" class="formbtn" value="Delete" onclick="deleteLock();" />
                <?php else: ?>
                <input type="button" title="Creat Lock" name="create_lock" id="create_lock" class="formbtn" value="Create" onclick="createLock();" />
                <?php endif; ?>
            </td>
        </tr>
	</tbody>
</table>
