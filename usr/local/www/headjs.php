<?php
/*
    headjs.php

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/
/*
	pfSense_MODULE:	header
*/

##|+PRIV
##|*IDENT=page-requiredforjavascript
##|*NAME=Required for javascript page
##|*DESCR=Allow access to the 'Required for javascript' page.
##|*MATCH=headjs.php*
##|-PRIV

require_once("guiconfig.inc");

function getHeadJS() {
  global $g, $use_loader_tab_gif;

  if(!$use_loader_tab_gif)
    $loader_gif = "/themes/{$g['theme']}/images/misc/loader.gif";
  else
    $loader_gif = "/themes/{$g['theme']}/images/misc/loader_tab.gif";

  $headjs = "
    var input_errors = '';
    jQuery(document).ready(init);
  ";
  if (!session_id())
	session_start();
  $_SESSION['NO_AJAX'] == "True" ? $noajax = "var noAjaxOnSubmit = true;" : $noajax = "var noAjaxOnSubmit = false;";
  session_commit();

  $headjs .= "
    {$noajax}

    function init() {
      if(jQuery('#submit') && ! noAjaxOnSubmit) {
        // debugging helper
        //alert('adding observe event for submit button');
        
        jQuery(\"#submit\").click(submit_form);
        jQuery('#submit').click(function() {return false;});
        var to_insert = \"<div style='visibility:hidden' id='loading' name='loading'><img src='{$loader_gif}' alt='loader' \/><\/div>\";
        jQuery('#submit').before(to_insert);
      }
    }
    
    function submit_form(e){
      // debugging helper
      //alert(Form.serialize($('iform')));

      if(jQuery('#inputerrors'))
        jQuery('#inputerrors').html('<center><b><i>Loading...<\/i><\/b><\/center>');
        
      /* dsh: Introduced because pkg_edit tries to set some hidden fields
       *      if executing submit's onclick event. The click gets deleted
       *      by Ajax. Hence using onkeydown instead.
       */
      if(jQuery('#submit').prop('keydown')) {
        jQuery('#submit').keydown();
        jQuery('#submit').css('visibility','hidden');
      }
      if(jQuery('#cancelbutton'))
        jQuery('#cancelbutton').css('visibility','hidden');
      jQuery('#loading').css('visibility','visible');
      // submit the form using Ajax
    }
   
    function formSubmitted(resp) {
      var responseText = resp.responseText;
      
      // debugging helper
      // alert(responseText);
      
      if(responseText.indexOf('html') > 0) {
        /* somehow we have been fed an html page! */
        //alert('Somehow we have been fed an html page! Forwarding to /.');
        document.location.href = '/';
      }
      
      eval(responseText);
    }
    
    /* this function will be called if an HTTP error will be triggered */
    function formFailure(resp) {
	    showajaxmessage(resp.responseText);
		if(jQuery('#submit'))
		  jQuery('#submit').css('visibility','visible');
		if(jQuery('#cancelbutton'))
		  jQuery('#cancelbutton').css('visibility','visible');
		if(jQuery('#loading'))
		  jQuery('#loading').css('visibility','hidden');

    }
    
    function showajaxmessage(message) {
      var message_html;

      if (message == '') {
        NiftyCheck();
        Rounded(\"div#redbox\",\"all\",\"#FFF\",\"#990000\",\"smooth\");
        Rounded(\"td#blackbox\",\"all\",\"#FFF\",\"#000000\",\"smooth\");

        if(jQuery('#submit'))
          jQuery('#submit').css('visibility','visible');
        if(jQuery('#cancelbutton'))
          jQuery('#cancelbutton').css('visibility','visible');
        if(jQuery('#loading'))
          jQuery('#loading').css('visibility','hidden');

        return;
      }

      message_html = '<table height=\"32\" width=\"100%\" summary=\"redbox\"><tr><td>';
      message_html += '<div style=\"background-color:#990000\" id=\"redbox\">';
      message_html += '<table width=\"100%\" summary=\"message\"><tr><td width=\"8%\">';
      message_html += '<img style=\"vertical-align:center\" src=\"/themes/{$g['theme']}/images/icons/icon_exclam.gif\" width=\"28\" height=\"32\" alt=\"exclamation\" \/>';
      message_html += '<\/td><td width=\"70%\"><font color=\"white\">';
      message_html += '<b>' + message + '<\/b><\/font><\/td>';

      if(message.indexOf('apply') > 0) {
        message_html += '<td>';
        message_html += '<input name=\"apply\" type=\"submit\" class=\"formbtn\" id=\"apply\" value=\"" . gettext("Apply changes") . "\" \/>';
        message_html += '<\/td>';
      }

      message_html += '<\/tr><\/table><\/div><\/td><\/table><br \/>';
      jQuery('#inputerrors').html(message_html);

      NiftyCheck();
      Rounded(\"div#redbox\",\"all\",\"#FFF\",\"#990000\",\"smooth\");
      Rounded(\"td#blackbox\",\"all\",\"#FFF\",\"#000000\",\"smooth\");

      if(jQuery('#submit'))
        jQuery('#submit').css('visibility','visible');
      if(jQuery('#cancelbutton'))
        jQuery('#cancelbutton').css('visibility','visible');
      if(jQuery('#loading'))
        jQuery('#loading').css('visibility','hidden');
      if(jQuery('#inputerrors'))
        window.scrollTo(0, 0);
    }
  ";

  return $headjs;
}

?>
