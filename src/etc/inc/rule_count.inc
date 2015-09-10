<?php
$rules_count_array=array();
exec("/sbin/pfctl -vvsr | /usr/bin/grep -A1 ' label '",$cnt_pfctl);
if (!empty($cnt_pfctl)) {
        foreach($cnt_pfctl as $line) {
                if (preg_match('/@(\d+)\W\d+.*label "(USER_RULE: |)(.*)"/',$line,$m1)){
                        $cr=$m1[3];
                        $rl=$m1[2].$m1[3];
                        $rid=$m1[1];
                        continue;
                }
                if ($cr != "" && preg_match("/Evaluations:\s+(\d+)\s+Packets:\s+(\d+)\s+Bytes:\s+(\d+)\s+States:\s+(\d+)\s+/",$line,$m2)){
                        if (isset($rules_count_array[$cr][Packets])){
                                $rules_count_array[$cr]['Packets'] += $m2[2];
                                $rules_count_array[$cr]['Evaluations'] += $m2[1];
                                $rules_count_array[$cr]['Bytes'] += $m2[3];
                                $rules_count_array[$cr]['States'] += $m2[4];
                                $rules_count_array[$cr]['RuleId'] .= "|$rid";
                        }else {
                                
$rules_count_array[$cr]=array('Evaluations'=>$m2[1],'Packets'=>$m2[2],'Bytes'=>$m2[3],'States'=>$m2[4],'Label'=>$rl,'RuleId'=>$rid);
                        }
                }
        }
}
?>
