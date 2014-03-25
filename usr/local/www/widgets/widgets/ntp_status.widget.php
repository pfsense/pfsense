<?php
/*
        $Id$
        Copyright 2007 Scott Dale
        Part of pfSense widgets (https://www.pfsense.org)
        originally based on m0n0wall (http://m0n0.ch/wall)

        Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
        and Jonathan Watt <jwatt@jwatt.org>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");

require_once("/usr/local/www/widgets/include/ntp_status.inc");

if($_REQUEST['updateme']) {
//this block displays only on ajax refresh
	exec("/usr/local/sbin/ntpq -pn | /usr/bin/tail +3", $ntpq_output);
	$ntpq_counter = 0;
	foreach ($ntpq_output as $line) {
		if (substr($line, 0, 1) == "*") {
			//Active NTP Peer
			$line = substr($line, 1);
			$peerinfo = preg_split("/[\s\t]+/", $line);
			if ($peerinfo[2] == "1") {
				$syncsource = $peerinfo[0] . " (stratum " . $peerinfo[2] . ", " . $peerinfo[1] . ")";
			} else {
				$syncsource = $peerinfo[0] . " (stratum " . $peerinfo[2] . ")";
			}
			$ntpq_counter++;
		} elseif (substr($line, 0, 1) == "o") {
			//Local PPS Peer
			$line = substr($line, 1);
			$peerinfo = preg_split("/[\s\t]+/", $line);
			$syncsource = $peerinfo[1] . " (stratum " . $peerinfo[2] . ", PPS)";
			$ntpq_counter++;
		}
	}

	exec("/usr/local/sbin/ntpq -c clockvar", $ntpq_clockvar_output);
	foreach ($ntpq_clockvar_output as $line) {
		if (substr($line, 0, 9) == "timecode=") {
			$tmp = explode('"', $line);
			$tmp = $tmp[1];
			if (substr($tmp, 0, 6) == '$GPRMC') {
				$gps_vars = explode(",", $tmp);
				$gps_ok  = ($gps_vars[2] == "A");
				$gps_lat_deg = substr($gps_vars[3], 0, 2);
				$gps_lat_min = substr($gps_vars[3], 2) / 60.0;
				$gps_lon_deg = substr($gps_vars[5], 0, 3);
				$gps_lon_min = substr($gps_vars[5], 3) / 60.0;
				$gps_lat = $gps_lat_deg + $gps_lat_min;
				$gps_lat = $gps_lat * (($gps_vars[4] == "N") ? 1 : -1);
				$gps_lon = $gps_lon_deg + $gps_lon_min;
				$gps_lon = $gps_lon * (($gps_vars[6] == "E") ? 1 : -1);
				$gps_la = $gps_vars[4];
				$gps_lo = $gps_vars[6];
			}elseif (substr($tmp, 0, 6) == '$GPGGA') {
				$gps_vars = explode(",", $tmp);
				$gps_ok  = $gps_vars[6];
				$gps_lat_deg = substr($gps_vars[2], 0, 2);
				$gps_lat_min = substr($gps_vars[2], 2) / 60.0;
				$gps_lon_deg = substr($gps_vars[4], 0, 3);
				$gps_lon_min = substr($gps_vars[4], 3) / 60.0;
				$gps_lat = $gps_lat_deg + $gps_lat_min;
				$gps_lat = $gps_lat * (($gps_vars[3] == "N") ? 1 : -1);
				$gps_lon = $gps_lon_deg + $gps_lon_min;
				$gps_lon = $gps_lon * (($gps_vars[5] == "E") ? 1 : -1);
				$gps_alt = $gps_vars[9];
				$gps_alt_unit = $gps_vars[10];
				$gps_sat = $gps_vars[7];
				$gps_la = $gps_vars[3];
				$gps_lo = $gps_vars[5];
			}elseif (substr($tmp, 0, 6) == '$GPGLL') {
				$gps_vars = explode(",", $tmp);
				$gps_ok  = ($gps_vars[6] == "A");
				$gps_lat_deg = substr($gps_vars[1], 0, 2);
				$gps_lat_min = substr($gps_vars[1], 2) / 60.0;
				$gps_lon_deg = substr($gps_vars[3], 0, 3);
				$gps_lon_min = substr($gps_vars[3], 3) / 60.0;
				$gps_lat = $gps_lat_deg + $gps_lat_min;
				$gps_lat = $gps_lat * (($gps_vars[2] == "N") ? 1 : -1);
				$gps_lon = $gps_lon_deg + $gps_lon_min;
				$gps_lon = $gps_lon * (($gps_vars[4] == "E") ? 1 : -1);
				$gps_la = $gps_vars[2];
				$gps_lo = $gps_vars[4];
			}
		}
	}

	if (isset($config['ntpd']['gps']['type']) && ($config['ntpd']['gps']['type'] == 'SureGPS') && (isset($gps_ok))) {
		//GSV message is only enabled by init commands in services_ntpd_gps.php for SureGPS board
		$gpsport = fopen("/dev/gps0", "r+");
		while($gpsport){
			$buffer = fgets($gpsport);
			if(substr($buffer, 0, 6)=='$GPGSV'){
				//echo $buffer."\n";
				$gpgsv = explode(',',$buffer);
				$gps_satview = $gpgsv[3];
				break;
			}
		}
	}
?>

<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="clock">
	<tbody>
		<tr>
			<td width="40%" class="vncellt">Sync Source</td>
			<td width="60%" class="listr">
			<?php if ($ntpq_counter == 0): ?>
				No active peers available
			<?php else: ?>
				<?php echo $syncsource; ?>
			<?php endif; ?>
			</td>
		</tr>
		<?php if (($gps_ok) && ($gps_lat) && ($gps_lon)): ?>
			<tr>
				<td width="40%" class="vncellt">Clock location</td>
				<td width="60%" class="listr">
					<a target="_gmaps" href="http://maps.google.com/?q=<?php echo $gps_lat; ?>,<?php echo $gps_lon; ?>">
					<?php 
					echo sprintf("%.5f", $gps_lat) . " " . $gps_la . ", " . sprintf("%.5f", $gps_lon) . " " . $gps_lo; ?>
					</a>
					<?php if (isset($gps_alt)) {echo " (" . $gps_alt . " " . $gps_alt_unit . " alt.)";} ?>
				</td>
			</tr>
			<?php if (isset($gps_sat) || isset($gps_satview)): ?>
				<tr>
					<td width="40%" class="vncellt">Satellites</td>
					<td width="60%" class="listr">
					<?php 
					if (isset($gps_satview)) {echo 'in view ' . intval($gps_satview);}
					if (isset($gps_sat) && isset($gps_satview)) {echo ', ';}
					if (isset($gps_sat)) {echo 'in use ' . $gps_sat;} 
					?>
					</td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>
	</tbody>
</table>
<?php	
	exit;
}

/*** Clock -- beginning of server-side support code
by Andrew Shearer, http://www.shearersoftware.com/
v2.1.2-PHP, 2003-08-07. For updates and explanations, see
<http://www.shearersoftware.com/software/web-tools/clock/>. ***/

/* Prevent this page from being cached (though some browsers still
   cache the page anyway, which is why we use cookies). This is
   only important if the cookie is deleted while the page is still
   cached (and for ancient browsers that don't know about Cache-Control).
   If that's not an issue, you may be able to get away with
   "Cache-Control: private" instead. */

/* Grab the current server time. */
$gDate = time();
/* Are the seconds shown by default? When changing this, also change the
   JavaScript client code's definition of clockShowsSeconds below to match. */
$gClockShowsSeconds = true;

function getServerDateItems($inDate) {
	return date('Y,n,j,G,',$inDate).intval(date('i',$inDate)).','.intval(date('s',$inDate));
	// year (4-digit),month,day,hours (0-23),minutes,seconds
	// use intval to strip leading zero from minutes and seconds
	//   so JavaScript won't try to interpret them in octal
	//   (use intval instead of ltrim, which translates '00' to '')
}

function clockDateString($inDate) {
    return date('Y. F j l',$inDate);    // eg "Monday, January 1, 2002"
}

function clockTimeString($inDate, $showSeconds) {
    return date($showSeconds ? 'G:i:s' : 'g:i',$inDate).' ';
}
/*** Clock -- end of server-side support code ***/
?>

<script type="text/javascript">
<!--
/* set up variables used to init clock in BODY's onLoad handler;
   should be done as early as possible */
var clockLocalStartTime = new Date();
var clockServerStartTime = new Date(<?php echo(getServerDateItems($gDate))?>);

/* stub functions for older browsers;
   will be overridden by next JavaScript1.2 block */
function clockInit() {
}
//-->
</script>


<script type="text/javascript">
<!--
/*** simpleFindObj, by Andrew Shearer

Efficiently finds an object by name/id, using whichever of the IE,
classic Netscape, or Netscape 6/W3C DOM methods is available.
The optional inLayer argument helps Netscape 4 find objects in
the named layer or floating DIV. */
function simpleFindObj(name, inLayer) {
	return document[name] || (document.all && document.all[name])
		|| (document.getElementById && document.getElementById(name))
		|| (document.layers && inLayer && document.layers[inLayer].document[name]);
}

/*** Beginning of Clock 2.1.2, by Andrew Shearer
See: http://www.shearersoftware.com/software/web-tools/clock/
Redistribution is permitted with the above notice intact.

Client-side clock, based on computed time differential between browser &
server. The server time is inserted by server-side JavaScript, and local
time is subtracted from it by client-side JavaScript while the page is
loading.

Cookies: The local and remote times are saved in cookies named
localClock and remoteClock, so that when the page is loaded from local
cache (e.g. by the Back button) the clock will know that the embedded
server time is stale compared to the local time, since it already
matches its cookie. It can then base the calculations on both cookies,
without reloading the page from the server. (IE 4 & 5 for Windows didn't
respect Response.Expires = 0, so if cookies weren't used, the clock
would be wrong after going to another page then clicking Back. Netscape
& Mac IE were OK.)

Every so often (by default, one hour) the clock will reload the page, to
make sure the clock is in sync (as well as to update the rest of the
page content).

Compatibility: IE 4.x and 5.0, Netscape 4.x and 6.0, Mozilla 1.0. Mac & Windows.

History:  1.0   2000-05-09 GIF-image digits
          2.0   2000-06-29 Uses text DIV layers (so 4.0 browsers req'd), &
                         cookies to work around Win IE stale-time bug
		  2.1   2002-10-12 Noted Mozilla 1.0 compatibility; released PHP version.
		  2.1.1 2002-10-20 Fixed octal bug in the PHP translation; the number of
		  				minutes & seconds were misinterpretes when less than 10
		  2.1.2 2003-08-07 The previous fix had introduced a bug when the
		                minutes or seconds were exactly 0. Thanks to Man Bui
		                for reporting the bug.
*/
var clockIncrementMillis = 1000;
var localTime;
var clockOffset;
var clockExpirationLocal;
var clockShowsSeconds = true;
var clockTimerID = null;

function clockInit(localDateObject, serverDateObject)
{
    var origRemoteClock = parseInt(clockGetCookieData("remoteClock"));
    var origLocalClock = parseInt(clockGetCookieData("localClock"));
    var newRemoteClock = serverDateObject.getTime();
    // May be stale (WinIE); will check against cookie later
    // Can't use the millisec. ctor here because of client inconsistencies.
    var newLocalClock = localDateObject.getTime();
    var maxClockAge = 60 * 60 * 1000;   // get new time from server every 1hr

    if (newRemoteClock != origRemoteClock) {
        // new clocks are up-to-date (newer than any cookies)
        document.cookie = "remoteClock=" + newRemoteClock;
        document.cookie = "localClock=" + newLocalClock;
        clockOffset = newRemoteClock - newLocalClock;
        clockExpirationLocal = newLocalClock + maxClockAge;
        localTime = newLocalClock;  // to keep clockUpdate() happy
    }
    else if (origLocalClock != origLocalClock) {
        // error; localClock cookie is invalid (parsed as NaN)
        clockOffset = null;
        clockExpirationLocal = null;
    }
    else {
        // fall back to clocks in cookies
        clockOffset = origRemoteClock - origLocalClock;
        clockExpirationLocal = origLocalClock + maxClockAge;
        localTime = origLocalClock;
        // so clockUpdate() will reload if newLocalClock
        // is earlier (clock was reset)
    }
    /* Reload page at server midnight to display the new date,
       by expiring the clock then */
    var nextDayLocal = (new Date(serverDateObject.getFullYear(),
            serverDateObject.getMonth(),
            serverDateObject.getDate() + 1)).getTime() - clockOffset;
    if (nextDayLocal < clockExpirationLocal) {
        clockExpirationLocal = nextDayLocal;
    }
}

function clockOnLoad()
{
    clockUpdate();
}

function clockOnUnload() {
    clockClearTimeout();
}

function clockClearTimeout() {
    if (clockTimerID) {
        clearTimeout(clockTimerID);
        clockTimerID = null;
    }
}

function clockToggleSeconds()
{
    clockClearTimeout();
    if (clockShowsSeconds) {
        clockShowsSeconds = false;
        clockIncrementMillis = 60000;
    }
    else {
        clockShowsSeconds = true;
        clockIncrementMillis = 1000;
    }
    clockUpdate();
}

function clockTimeString(inHours, inMinutes, inSeconds) {
	return inHours
	+ (inMinutes < 10 ? ":0" : ":") + inMinutes
	+ (inSeconds < 10 ? ":0" : ":") + inSeconds;
}

function clockDisplayTime(inHours, inMinutes, inSeconds) {
    
    clockWriteToDiv("ClockTime", clockTimeString(inHours, inMinutes, inSeconds));
}

function clockWriteToDiv(divName, newValue) // APS 6/29/00
{
    var divObject = simpleFindObj(divName);
    newValue = '<b>' + newValue + '<' + '/b>';
    if (divObject && divObject.innerHTML) {
        divObject.innerHTML = newValue;
    }
    else if (divObject && divObject.document) {
        divObject.document.writeln(newValue);
        divObject.document.close();
    }
    // else divObject wasn't found; it's only a clock, so don't bother complaining
}

function clockGetCookieData(label) {
    /* find the value of the specified cookie in the document's
    semicolon-delimited collection. For IE Win98 compatibility, search
    from the end of the string (to find most specific host/path) and
    don't require "=" between cookie name & empty cookie values. Returns
    null if cookie not found. One remaining problem: Under IE 5 [Win98],
    setting a cookie with no equals sign creates a cookie with no name,
    just data, which is indistinguishable from a cookie with that name
    but no data but can't be overwritten by any cookie with an equals
    sign. */
    var c = document.cookie;
    if (c) {
        var labelLen = label.length, cEnd = c.length;
        while (cEnd > 0) {
            var cStart = c.lastIndexOf(';',cEnd-1) + 1;
            /* bug fix to Danny Goodman's code: calculate cEnd, to
            prevent walking the string char-by-char & finding cookie
            labels that contained the desired label as suffixes */
            // skip leading spaces
            while (cStart < cEnd && c.charAt(cStart)==" ") cStart++;
            if (cStart + labelLen <= cEnd && c.substr(cStart,labelLen) == label) {
                if (cStart + labelLen == cEnd) {
                    return ""; // empty cookie value, no "="
                }
                else if (c.charAt(cStart+labelLen) == "=") {
                    // has "=" after label
                    return unescape(c.substring(cStart + labelLen + 1,cEnd));
                }
            }
            cEnd = cStart - 1;  // skip semicolon
        }
    }
    return null;
}

/* Called regularly to update the clock display as well as onLoad (user
   may have clicked the Back button to arrive here, so the clock would need
   an immediate update) */
function clockUpdate()
{
    var lastLocalTime = localTime;
    localTime = (new Date()).getTime();
    
    /* Sanity-check the diff. in local time between successive calls;
       reload if user has reset system clock */
    if (clockOffset == null) {
        clockDisplayTime(null, null, null);
    }
    else if (localTime < lastLocalTime || clockExpirationLocal < localTime) {
        /* Clock expired, or time appeared to go backward (user reset
           the clock). Reset cookies to prevent infinite reload loop if
           server doesn't give a new time. */
        document.cookie = 'remoteClock=-';
        document.cookie = 'localClock=-';
        location.reload();      // will refresh time values in cookies
    }
    else {
        // Compute what time would be on server 
        var serverTime = new Date(localTime + clockOffset);
        clockDisplayTime(serverTime.getHours(), serverTime.getMinutes(),
            serverTime.getSeconds());
        
        // Reschedule this func to run on next even clockIncrementMillis boundary
        clockTimerID = setTimeout("clockUpdate()",
            clockIncrementMillis - (serverTime.getTime() % clockIncrementMillis));
    }
}

/*** End of Clock ***/
//-->
  window.onload=clockInit(clockLocalStartTime, clockServerStartTime);clockOnLoad();
  window.onunload=clockOnUnload()
  clockUpdate();
</script>


<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="clock">
	<tbody>
		<tr>
			<td width="40%" class="vncellt">Server Time</td>
			<td width="60%" class="listr">
				<div id="ClockTime">
					<b><?php echo(clockTimeString($gDate,$gClockShowsSeconds));?></b>
				</div>
			</td>
		</tr>
	</tbody>
</table>

<div id='ntpstatus'>
<table width="100%" border="0" cellspacing="0" cellpadding="0" summary="clock">
	<tbody>
		<tr>
			<td width="100%" class="listr">
				Updating...
			</td>
		</tr>
	</tbody>
</table>
</div>

<script type="text/javascript">
	function ntp_getstatus() {
		scroll(0,0);
		var url = "/widgets/widgets/ntp_status.widget.php";
		var pars = 'updateme=yes';
		jQuery.ajax(
			url,
			{
				type: 'get',
				data: pars,
				complete: ntpstatuscallback
			});
		// Refresh the status every 1 minute
		setTimeout('ntp_getstatus()', 1*60*1000);
	}
	function ntpstatuscallback(transport) {
		// The server returns formatted html code
		var responseStringNtp = transport.responseText
		jQuery('#ntpstatus').prop('innerHTML',responseStringNtp);
	}
	// Do the first status check 1 second after the dashboard opens
	setTimeout('ntp_getstatus()', 1000);
</script>
