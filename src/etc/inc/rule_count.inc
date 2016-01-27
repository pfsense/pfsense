<?php
foreach (pfSense_get_pf_rules() as $line){
	foreach ($line as $key=>$value){
		switch ($key){
			case 'bytes':
			case 'states':
			case 'packets':
			case 'evaluations':
				$rules_count_array[$line['tracker']][$key] += $value;
				break;
			case 'id':
				if (isset($rules_count_array[$line['tracker']][RuleId])) {
					$rules_count_array[$line['tracker']][RuleId] .= "|{$value}";
				} else {
					$rules_count_array[$line['tracker']][RuleId] = $value;
				}
				break;
			case 'label':
				$rules_count_array[$line['tracker']][$key] = $value;
				break;
		}
	}
}
if ($_POST['action'] != "KillRuleStates") {?>
	<div id="RuleStates" class="modal fade" role="dialog">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>

					<h3 class="modal-title" id="myModalLabel"><?=gettext("Rule States")?></h3>
				</div>

				<div class="modal-body" id="RuleStates-modal-body">
				</div>
			</div>
		</div>
	</div>
<?php }?>

