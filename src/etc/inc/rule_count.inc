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
?>
