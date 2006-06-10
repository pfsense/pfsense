#!/usr/local/bin/php -q
<?php

$last_text = "";

while(!stristr($status, "Done")) {
        $status = get_status();
        if($status <> "") {
                echo $status . "\n";
        }
        sleep(1);
}

function get_status() {
        global $last_text;
        $status = file_get_contents("/var/run/filter_reload_status");
        $status = str_replace("...", "", $status);
        $status .= "...";
        if($status <> $last_text) {
                $last_text = $status;
                return $status;
        }
        return "";
}

?>
