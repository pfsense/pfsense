
<?php

//require_once("guiconfig.inc");
//require_once("functions.inc");
//require_once("captiveportal.inc");
define("FILE_SIZE", 450000);

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of CrashReporter
 *
 * @author vpereira
 */
class CrashReporter {
    
    private $crash_report_header;
    public $crash_files;
    private $php_errors;
    private $crash_reports;
        
    function get_php_errors()
    {
        exec("/usr/bin/grep -vi warning /tmp/PHP_errors.log", $this->php_errors);
    }

    function get_crash_files()
    {
        $this->crash_files = glob("/var/crash/*");
        return $this->crash_files;
    }
    
    function submit_form()
    {
        if (!is_dir("/var/crash"))
            mwexec("/bin/mkdir -p /var/crash");
        
        @file_put_contents("/var/crash/crashreport_header.txt", $this->crash_report_header);
	
        if(file_exists("/tmp/PHP_errors.log"))
            exec("cp /tmp/PHP_errors.log /var/crash/");
	
        //we gzip each file from /var/crash but then we glob everything.
        //shouldnt we glob just *.gz and delete all ungzipped?
        exec("/usr/bin/gzip /var/crash/*");
	
        $this->get_crash_files();
        
    }
    
    function prepare_report_header()
    {
        /* @var $crash_report_header type string */
        $this->crash_report_header = '';
        $this->crash_report_header = "Crash report begins.  
            Anonymous machine information:\n\n";
        $this->crash_report_header .= php_uname("m") . "\n";
        $this->crash_report_header .= php_uname("r") . "\n";
        $this->crash_report_header .= php_uname("v") . "\n";
        $this->crash_report_header .= "\nCrash report details:\n";
        return $this->crash_report_header;
    }
    
    function prepare_report()
    {
        if (count($this->php_errors) > 0) {
			$this->crash_reports .= "\nPHP Errors:\n";
			$this->crash_reports .= implode("\n", $this->php_errors) 
                                . "\n\n";
		}
		if(is_array($this->crash_files))	{
			foreach($this->crash_files as $cf) {
				if(filesize($cf) < FILE_SIZE) {
					$this->crash_reports .= "\nFilename: {$cf}\n";
					$this->crash_reports .= file_get_contents($cf);
				}
			}
		} else { 
                        $this->crash_reports .= "Could not locate any crash data."; 
		}
        return $this->crash_reports;
    }
    function purge_crash_logs()
    {
        exec("rm /var/crash/*");
	// Erase the contents of the PHP error log
	fclose(fopen("/tmp/PHP_errors.log", 'w'));
    }
    
    function upload() {
	global $g;
	$post = array();
	$counter = 0;
	foreach($this->files_to_upload as $file) {
		$post["file{$counter}"] = "@{$file}";
		$counter++;
	}
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
        curl_setopt($ch, CURLOPT_URL, $g['crashreporterurl']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $response = curl_exec($ch);
        return $response;
    }
    function output_html() {
        //TODO move it to a template
	$ret_str = "<strong>" . gettext("Unfortunately we have detected a programming bug.") . "</strong></p>"
            . gettext("Would you like to submit the programming debug logs to the pfSense developers for inspection?") . "</p>"
            ."<p>"
            ."<i>" . gettext("Please double check the contents to ensure you are comfortable sending this information before clicking Yes.") . "</i><br/>"
            . "<p>"
            . gettext("Contents of crash reports") . ":<br/>"
            ."<textarea readonly rows='40' cols='65' name='crashreports'>{$this->crash_reports}</textarea>"
            . "<p/>"
            ."<input name=\"Submit\" type=\"submit\" class=\"formbtn\" value=\"" . gettext("Yes") .  "\">" . gettext(" - Submit this to the developers for inspection")
            . "<p/><input name=\"Submit\" type=\"submit\" class=\"formbtn\" value=\"" . gettext("No") .  "\">" . gettext(" - Just delete the crash report and take me back to the Dashboard")
            . "<p/>" .
            "</form>";
            return $ret_str;
    }
}

?>
