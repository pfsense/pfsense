<?php
include 'globals.inc';
?>
<pre>
When working with <?= $g['product_name'] ?> based schedules, the logic is a bit different from the normal <?= $g['product_name'] ?> rules.

For example, the rules are evaluated from top to bottom.   

If you have a pass rule and the rule is outside of the schedule, the traffic will be BLOCKED regardless 
of pass rules that occur after this rule.

In these cases you will want to change the pass rule to a block style rule to get the needed functionality.
</pre>