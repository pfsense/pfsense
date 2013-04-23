<?php


require_once './classes/CrashReporter.inc';
require_once 'PHPUnit/Autoload.php';
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of CrashReporterTest
 *
 * @author vpereira
 */
class CrashReporterTest extends PHPUnit_Framework_TestCase {

    public function testNULL() {
        $this->assertNotNull(new CrashReporter());
    }
    
    public function testOutputHTML() {
       $cr = new CrashReporter();
       $this->assertNotNull($cr->output_html());
    }
    
    public function testPrepareReportHeader() {
        $cr = new CrashReporter();
        $this->assertNotNull($cr->prepare_report_header());
    }
    public function testPrepareReport() {
        $cr = new CrashReporter();
        $this->assertNotNull($cr->prepare_report());
    }
    public function testGetCrashFiles() {
        $cr = new CrashReporter();
        $this->assertInternalType('array', $cr->get_crash_files());
    }
}

?>
