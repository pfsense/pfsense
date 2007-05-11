<?php
/* $Id$ */
/*
        Copyright (C) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
        All rights reserved.

        Copyright (C) 2006 Paul Taylor <paultaylor@winn-dixie.com>.
        All rights reserved.

        Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
        All rights reserved.

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

include_once("auth.inc");
require_once("functions.inc");
/* TODO: Needs testing... require_once("pages.inc"); */

/* We only support htpasswd backed HTTP Basic auth right now
 * backing methods
 *      passwd_backed - this will use the system passwd file in /etc
 *      radius_backed - this will allow you to use a radius server
 *      htpasswd_backed - this uses the "standard" .htpasswd file
 *      pam_backed - this uses the system's PAM facility .htpasswd file
 */
//$auth_method="basic_auth";
$auth_method="session_auth";
$backing_method="htpasswd_backed";

/* Authenticate user - exit if failed (we should have a callback for this maybe) */
if (!$auth_method($backing_method)) { exit; }

/* scriptname is set in headjs.php if the user did try to access a page other
 * than index.php without beeing logged in.
 */
if (isset($_POST['scriptname']) && isSystemAdmin($HTTP_SERVER_VARS['AUTH_USER'])) {
  pfSenseHeader("{$_POST['scriptname']}");
  exit;
}

/* TODO: Needs testing
if ($_SESSION) {
        if (isset($_SESSION['Pages'])) {
                $pfPages = getPageObjectFromSession();
        } else {
                $pfPages = getPageObject($HTTP_SERVER_VARS['AUTH_USER']);
                $pages =& $pfPages->getPages();
                $pages_index =& $pfPages->getPagesIndex();
                $allowed_pages_index =& $pfPages->getAllowedPagesIndex();

                $_SESSION['Pages'] = serialize($pages);
                $_SESSION['Pages_Index'] = serialize($pages_index);
                $_SESSION['Allowed_Pages_Index'] = serialize($allowed_pages_index);
        }
} else {
        $pfPages = getPageObject($HTTP_SERVER_VARS['AUTH_USER']);
}
*/

// Once here, the user has authenticated with the web server.
// Now, we give them access only to the appropriate pages for their group.
if (!(isSystemAdmin($HTTP_SERVER_VARS['AUTH_USER']))) {
  $allowed[] = '';
  if (isset($config['system']['group'][$groupindex[$config['system']['user'][$userindex[$HTTP_SERVER_VARS['AUTH_USER']]]['groupname']]]['pages'][0])) {
    $allowed = &$config['system']['group'][$groupindex[$config['system']['user'][$userindex[$HTTP_SERVER_VARS['AUTH_USER']]]['groupname']]]['pages'];
  }

  $group = $config['system']['user'][$userindex[$HTTP_SERVER_VARS['AUTH_USER']]]['groupname'];
  /* get the group homepage, to be able to forward
   * the user to this particular PHP page.
   */
  getGroupHomePage($group) == "" ? $home = "/index.php" : $home = "/" . getGroupHomePage($group);

  /* okay but if the user realy tries to explicitely access a particular
   * page, set $home to that page instead.
   */
  if (isset($_POST['scriptname']) && $_POST['scriptname'] <> "/" && $_POST['scriptname'] <> "/index.php")
    $home = basename($_POST['scriptname']);

  // If the user is attempting to hit the default page, set it to specifically look for /index.php.
  // Without this, any user would have access to the index page.
  //if ($_SERVER['SCRIPT_NAME'] == '/')
  //  $_SERVER['SCRIPT_NAME'] = $home;

  // Strip the leading / from the currently requested PHP page
  if (!in_array(basename($_SERVER['SCRIPT_NAME']),$allowed) && !in_array("ANY", $allowed)) {
    // The currently logged in user is not allowed to access the page
    // they are attempting to go to.  Redirect them to an allowed page.

    if(stristr($_SERVER['SCRIPT_NAME'],"sajax")) {
      echo "||Access to AJAX has been disallowed for this user.";
      exit;
    }

    if ($home <> "" && (in_array($home, $allowed) || in_array("ANY", $allowed))) {
      pfSenseHeader("{$home}");
      exit;
    } else {
      header("HTTP/1.0 401 Unauthorized");
      header("Status: 401 Unauthorized");

      echo display_error_form("401", "401 Unauthorized. Authorization required.");
      exit;
    }
  }

  if (isset($_SESSION['Logged_In'])) {
    /*
     * only forward if the user has just logged in
     * TODO: session auth based - may be an issue.
     */
    if ($_SERVER['SCRIPT_NAME'] <> $home && empty($_SESSION['First_Visit'])) {
      $_SESSION['First_Visit'] = "False";
      pfSenseHeader("{$home}");
      exit;
    }
  }
}

function display_error_form($http_code, $desc) {
  global $config, $g;
  $g['theme'] = $config['theme'];

  $htmlstr = <<<EOD
<html>
  <head>
    <script type="text/javascript" src="/javascript/scriptaculous/prototype.js"></script>
    <script type="text/javascript" src="/javascript/scriptaculous/scriptaculous.js"></script>
    <title>An error occurred: {$http_code}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
    <link rel="shortcut icon" href="/themes/{$g['theme']}/images/icons/favicon.ico" />
    <link rel="stylesheet" type="text/css" href="/themes/{$g['theme']}/all.css" media="all" />
    <style type="text/css">
    #errordesc {
      background: #cccccc;
      border: 0px solid #666666;
      margin: 5em auto;
      padding: 0em;
      width: 340px;
    }
    #errordesc h1 {
      background: url(/themes/{$g['theme']}/images/misc/logon.png) no-repeat top left;
      margin-top: 0;
      display: block;
      text-indent: -1000px;
      height: 50px;
      border-bottom: none;
    }

    #login p {
      font-size: 1em;
      font-weight: bold;
      padding: 3px;
      margin: 0em;
      text-indent: 10px;
    }

    #login span {
      font-size: 1em;
      font-weight: bold;
      width: 20%;
      padding: 3px;
      margin: 0em;
      text-indent: 10px;
    }

    #login p#text {
      font-size: 1em;
      font-weight: normal;
      padding: 3px;
      margin: 0em;
      text-indent: 10px;
    }
    </style>

    <script type="text/javascript">
    <!--
      function page_load() {
        NiftyCheck();
        Rounded("div#errordesc","bl br","#333","#cccccc","smooth");
        Effect.Pulsate('errortext', { duration: 10 });
      }
      <?php
        require("headjs.php");
        echo getHeadJS();
      ?>
    //-->
    </script>
    <script type="text/javascript" src="/themes/{$g['theme']}/javascript/niftyjsCode.js"></script>
  </head>
  <body onload="page_load();">
    <div id="errordesc">
      <h1>&nbsp</h1>
      <p id="errortext" style="vertical-align: middle; text-align: center;"><span style="color: #000000; font-weight: bold;">{$desc}</span></p>
    </div>
  </body>
</html>

EOD;

  return $htmlstr;
}

function display_login_form() {
  require_once("globals.inc");
  global $config, $g;
  $g['theme'] = $config['theme'];

  unset($input_errors);

  if(isAjax()) {
    if (isset($_POST['login'])) {
      if($_SESSION['Logged_In'] <> "True") {
        isset($_SESSION['Login_Error']) ? $login_error = $_SESSION['Login_Error'] : $login_error = "unknown reason";
        echo "showajaxmessage('Invalid login ({$login_error}).');";
      }
      if (file_exists("{$g['tmp_path']}/webconfigurator.lock")) {
        // TODO: add the IP from the user who did lock the device
        $whom = file_get_contents("{$g['tmp_path']}/webconfigurator.lock");
        echo "showajaxmessage('This device is currently beeing maintained by: {$whom}.');";
      }
    }
    exit;
  }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
  <head>
    <script type="text/javascript" src="/javascript/scriptaculous/prototype.js"></script>
    <script type="text/javascript" src="/javascript/scriptaculous/scriptaculous.js"></script>
    <title><?=gettext("Login"); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
    <link rel="shortcut icon" href="/themes/<?= $g['theme'] ?>/images/icons/favicon.ico" />
    <link rel="stylesheet" type="text/css" href="/themes/<?= $g['theme'] ?>/all.css" media="all" />
    <script type="text/javascript">
    <!--
      function page_load() {
        NiftyCheck();
        Rounded("div#login","bl br","#333","#cccccc","smooth");
        document.login_iform.usernamefld.focus();
      }
      function clearError() {
        if($('inputerrors'))
          $('inputerrors').innerHTML='';
      }
      <?php
        require("headjs.php");
        echo getHeadJS();
      ?>
    //-->
    </script>
    <script type="text/javascript" src="/themes/<?= $g['theme'] ?>/javascript/niftyjsCode.js"></script>
  </head>
  <body onload="page_load()">
    <div id="login">
      <h1>&nbsp;</h1>
      <form id="iform" name="login_iform" method="post" autocomplete="off" action="<?= $_SERVER['SCRIPT_NAME'] ?>">
        <div id="inputerrors"></div>
        <p>
          <span style="text-align: left;">
            <?=gettext("Username"); ?>:&nbsp;&nbsp;
          </span>
          <input onclick="clearError();" onchange="clearError();" id="usernamefld" type="text" name="usernamefld" class="formfld" tabindex="1" />
        </p>
        <p>
          <span style="text-align: left;">
            <?=gettext("Password"); ?>:&nbsp;&nbsp;
          </span>
          <input onclick="clearError();" onchange="clearError();" id="passwordfld" type="password" name="passwordfld" class="formfld" tabindex="2" />
        </p>
        <table width="90%" style="margin-right: auto; margin-left: auto;">
          <tr>
            <td valign="middle" align="right" style="font-style: italic;"><br /><?=gettext("Enter username and password to login."); ?></td>
            <td valign="middle" align="left"><input type="submit" id="submit" name="login" class="formbtn" value="<?=gettext("Login"); ?>" tabindex="3" /></td>
          </tr>
        </table>
      </form>
    </div>
  </body>
</html>
<?php
} // end function

?>
