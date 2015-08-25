/* $Id$ */
/*
    ticker.js
    Copyright (C) 2012 Marcello Coutinho
    Copyright (C) 2012 Carlos Cesario - carloscesario@gmail.com
    All rights reserved.

    originally part of m0n0wall (http://m0n0.ch/wall)
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

	pfSense_MODULE:	utils

*/
function notice_action(action,msgid) {
	jQuery.ajax({
		type: 'post',
		cache: false,
		url: 'index.php',
		data: {closenotice: msgid},
		success: function(response) {
			jQuery('#menu_messages').html(response);
		}
	});
}

function pulsateText(elem) {
    jQuery(elem).effect("pulsate", { times:12 }, 500);
    jQuery(elem).effect("pulsate", { times:6 }, 1500);
    jQuery(elem).effect("pulsate", { times:3 }, 2500);
}

jQuery(document).ready(function() {
    pulsateText('#marquee-text');
    jQuery('#marquee-text a').hover(function () {
        jQuery(this).css('cursor','pointer');
    });
});

function alias_popup(alias_id,theme,loading) {
	domTT_update('ttalias_'+alias_id,"<a><img src='/themes/"+theme+"/images/misc/loader.gif'>"+loading+"</a>");
	jQuery.ajax({
		type: 'post',
		cache: false,
		url: "/index.php",
		data: {aliasid:alias_id, act:'alias_info_popup'},
		success: function(response) {
			//alert('<div>'+response.match(/<h1>.*<\/table>/i)+'<div>');
			domTT_update('ttalias_'+alias_id,'<div>'+response.match(/<h1>.*<\/table>/i)+'<div>');
			}
		});
}