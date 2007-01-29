/*
        DatePicker v2.5 by frequency-decoder.com (2006/12/01)

        Released under a creative commons Attribution-ShareAlike 2.5 license (http://creativecommons.org/licenses/by-sa/2.5/)

        Please credit frequency-decoder in any derivative work - thanks.
        
        You are free:

        * to copy, distribute, display, and perform the work
        * to make derivative works
        * to make commercial use of the work

        Under the following conditions:

                by Attribution.
                --------------
                You must attribute the work in the manner specified by the author or licensor.

                sa
                --
                Share Alike. If you alter, transform, or build upon this work, you may distribute the resulting work only under a license identical to this one.

        * For any reuse or distribution, you must make clear to others the license terms of this work.
        * Any of these conditions can be waived if you get permission from the copyright holder.
*/
var datePickerController;

(function() {

datePicker.isSupported = typeof document.createElement != "undefined" &&
        typeof document.documentElement != "undefined" &&
        typeof document.documentElement.offsetWidth == "number";

// Detect the users language
datePicker.languageinfo = navigator.language ? navigator.language : navigator.userLanguage;
datePicker.languageinfo = datePicker.languageinfo ? datePicker.languageinfo.toLowerCase().replace(/-[a-z]+$/, "") : 'en';

if(datePicker.languageinfo != 'en') {
        // Load the appropriate language file
        var scriptFiles = document.getElementsByTagName('head')[0].getElementsByTagName('script');
        var loc         = "";

        for(var i = 0, scriptFile; scriptFile = scriptFiles[i]; i++) {
                if(scriptFile.src && scriptFile.src.match(/datepicker/)) {
                        loc = scriptFile.src.replace("datepicker", "lang/" + datePicker.languageinfo);
                        break;
                };
        };

        if(loc != "") {
                var script      = document.createElement('script');
                script.type     = "text/javascript";
                script.src      = loc;
                // Hopefully this allows a UTF-8 js file to be imported into a non-UTF HTML document
                script.setAttribute("charset", "utf-8");
                document.getElementsByTagName('head')[0].appendChild(script);
        };
};

// Defaults for the language should the locale file not load
datePicker.months = [
                "January",
                "February",
                "March",
                "April",
                "May",
                "June",
                "July",
                "August",
                "September",
                "October",
                "November",
                "December"];
datePicker.fullDay = [
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
                "Saturday",
                "Sunday"];
datePicker.titles = [
                "Previous month",
                "Next month",
                "Previous year",
                "Next year"];
datePicker.daysPerMonth = [31,28,31,30,31,30,31,31,30,31,30,31];

datePicker.getDaysPerMonth = function (nMonth, nYear) {
        nMonth = (nMonth + 12) % 12;
        var res = datePicker.daysPerMonth[nMonth];
        if(((0 == (nYear%4)) && ((0 != (nYear%100)) || (0 == (nYear%400)))) && nMonth == 1) {
                res = 29;
        };
        return res;
};

function datePicker(options) {

        this.defaults = {};
        
        for(opt in options) {
                this[opt] = this.defaults[opt] = options[opt];
        };
        
        this.date              = new Date();
        this.yearinc           = 1;
        this.timer             = null;
        this.pause             = 1000;
        this.timerSet          = false;
        this.opacity           = 0;
        this.opacityTo         = 0;
        this.fadeTimer         = null;
        this.interval          = new Date();
        this.firstDayOfWeek    = this.defaults.firstDayOfWeek = 0;
        this.dateSet           = null;
        this.visible           = false;
        this.div;
        this.table;

        var o = this;

        o.reset = function() {
                for(def in o.defaults) {
                        o[def] = o.defaults[def];
                };
        };
        o.setOpacity = function(op) {
                o.div.style.opacity =  + op/100;
                o.div.style.filter = 'alpha(opacity=' + op + ')';
                o.opacity = op;
        };
        o.fade = function() {

                window.clearTimeout(o.fadeTimer);
                var diff = Math.round(o.opacity + ((o.opacityTo - o.opacity) / 4));

                o.setOpacity(diff);

                if(Math.abs(o.opacityTo - diff) > 3) {
                        o.fadeTimer = window.setTimeout(function () { o.fade(); }, 50);
                } else {
                        o.setOpacity(o.opacityTo);
                        if(o.opacityTo == 0) o.div.style.display = "none";
                };
        };
        o.killEvent = function(e) {
                if (e == null) e = document.parentWindow.event;
                
                if (e.stopPropagation) {
                        e.stopPropagation();
                        e.preventDefault();
                }
                /*@cc_on@*/
                /*@if(@_win32)
                e.cancelBubble = true;
                e.returnValue = false;
                /*@end@*/
                return false;
        };
        o.startTimer = function () {
                if (o.timerSet) o.stopTimer();
                o.timer = window.setTimeout(function () { o.onTimer(); }, o.timerInc);
                o.timerSet = true;
        };
        o.stopTimer = function () {
                if (o.timer != null) window.clearTimeout(o.timer);
                o.timerSet = false;
        };
        o.events = {
                onkeydown: function (e) {

                        if(!o.visible) return false;

                        if (e == null) e = document.parentWindow.event;
                        var kc = e.keyCode ? e.keyCode : e.charCode;

                        if ( kc == 13 ) {
                                // close with update
                                o.returnFormattedDate();
                                o.hide();
                                return o.killEvent(e);
                        } else if ( kc == 27 ) {
                                // close
                                o.hide();
                                return o.killEvent(e);
                        } else if ( kc == 32 || kc == 0 ) {
                                // close
                                o.date =  new Date( );
                                o.updateTable();
                                return o.killEvent(e);
                        };

                        // Internet Explorer fires the keydown event faster than the JavaScript engine can
                        // update the interface. The following attempts to fix this.

                        /*@cc_on@*/
                        /*@if(@_win32)
                                if(new Date().getTime() - o.interval.getTime() < 100) return o.killEvent(e);
                                o.interval = new Date();
                        /*@end@*/

                        if ((kc > 49 && kc < 56) || (kc > 97 && kc < 104)) {
                                if (kc > 96) kc -= (96-48);
                                kc -= 49;
                                o.firstDayOfWeek = (o.firstDayOfWeek + kc) % 7;
                                o.updateTable();
                                return o.killEvent(e);
                        };

                        if ( kc < 37 || kc > 40 ) return true;

                        var d = new Date( o.date ).valueOf();

                        if ( kc == 37 ) {
                                // ctrl + left = previous month
                                if( e.ctrlKey ) {
                                        d = new Date( o.date );
                                        d.setDate( Math.min(d.getDate(), datePicker.getDaysPerMonth(d.getMonth() - 1,d.getFullYear())) ); // no need to catch dec -> jan for the year
                                        d.setMonth( d.getMonth() - 1 );
                                } else {
                                        d -= 24 * 60 * 60 * 1000;
                                };
                        } else if ( kc == 39 ) {
                                // ctrl + right = next month
                                if( e.ctrlKey ) {
                                        d = new Date( o.date );
                                        d.setDate( Math.min(d.getDate(), datePicker.getDaysPerMonth(d.getMonth() + 1,d.getFullYear())) ); // no need to catch dec -> jan for the year
                                        d.setMonth( d.getMonth() + 1 );
                                } else {
                                        d += 24 * 60 * 60 * 1000;
                                };
                        } else if ( kc == 38 ) {
                                // ctrl + up = next year
                                if( e.ctrlKey ) {
                                        d = new Date( o.date );
                                        d.setDate( Math.min(d.getDate(), datePicker.getDaysPerMonth(d.getMonth(),d.getFullYear() + 1)) ); // no need to catch dec -> jan for the year
                                        d.setFullYear( d.getFullYear() + 1 );
                                } else {
                                        d -= 7 * 24 * 60 * 60 * 1000;
                                };
                        } else if ( kc == 40 ) {
                                // ctrl + down = prev year
                                if( e.ctrlKey ) {
                                        d = new Date( o.date );
                                        d.setDate( Math.min(d.getDate(), datePicker.getDaysPerMonth(d.getMonth(),d.getFullYear() - 1)) ); // no need to catch dec -> jan for the year
                                        d.setFullYear( d.getFullYear() - 1 );
                                } else {
                                        d += 7 * 24 * 60 * 60 * 1000;
                                };
                        };

                        var tmpDate = new Date( d );
                        if(!o.outOfRange(tmpDate)) {
                                o.date = tmpDate;
                        };

                        o.updateTable();

                        return o.killEvent(e);
                },
                onmousedown: function(e) {
                        if ( e == null ) e = document.parentWindow.event;
                        var el = e.target != null ? e.target : e.srcElement;
                        
                        var found = false;

                        while(el.parentNode) {
                                if(el.id && (el.id == "fd-"+o.id || el.id == "fd-but-"+o.id)) {
                                        found = true;
                                        break;
                                }
                                try {
                                        el = el.parentNode;
                                } catch(err) {
                                        break;
                                }
                        }
                        if(found) return true;
                        datePickerController.hideAll();
                },
                onmouseover: function(e) {
                        if(document.getElementById("date-picker-hover")) {
                                document.getElementById("date-picker-hover").id = "";
                        };

                        this.id = "date-picker-hover";

                        o.date.setDate(this.firstChild.nodeValue);
                },
                onclick: function (e) {
                        if(o.opacity != o.opacityTo) return false;
                        if ( e == null ) e = document.parentWindow.event;
                        var el = e.target != null ? e.target : e.srcElement;
                        while ( el.nodeType != 1 ) el = el.parentNode;

                        var d = new Date( o.date );
                        var n = Number( el.firstChild.data );

                        if(isNaN(n)) { return true; };

                        d.setDate( n );
                        o.date = d;

                        o.returnFormattedDate();
                        o.hide();
                        return o.killEvent(e);
                },
                incDec:function(e) {
                        if(o.timerSet) {
                                o.stopTimer();
                        };
                        
                        datePickerController.addEvent(document, "mouseup", o.events.clearTimer);
                        
                        o.timerInc      = 1000;
                        o.dayInc        = arguments[1];
                        o.yearInc       = arguments[2];
                        o.monthInc      = arguments[3];
                        o.onTimer();
                        return o.killEvent(e);
                },
                clearTimer:function() {
                        o.stopped       = true;
                        o.timerInc      = 1000;
                        o.yearInc       = 0;
                        o.monthInc      = 0;
                        o.dayInc        = 0;
                        try {
                                datePickerController.removeEvent(document, "mouseup", o.events.clearTimer);
                        } catch(e) { };
                        o.stopTimer();
                }
        };
        o.onTimer = function() {
                var d = new Date( o.date );

                d.setDate( Math.min(d.getDate()+o.dayInc, datePicker.getDaysPerMonth(d.getMonth()+o.monthInc,d.getFullYear()+o.yearInc)) ); // no need to catch dec -> jan for the year
                d.setMonth( d.getMonth() + o.monthInc );
                d.setFullYear( d.getFullYear() + o.yearInc );

                o.date = d;

                if(o.timerInc > 50) {
                        o.timerInc = 50 + Math.round(((o.timerInc - 50) / 1.8));
                };
                o.startTimer();
                o.updateTable();
        };
        o.getElem = function() {
                return document.getElementById(o.id.replace(/^fd-/, '')) || false;
        };
        o.setRangeLow = function(range) {
                if(String(range).search(/^(\d\d?\d\d)(0[1-9]|1[012])(0[1-9]|[12][0-9]|3[01])$/) == -1) range = '';
                o.low = o.defaults.low = range;
        };
        o.setRangeHigh = function(range) {
                if(String(range).search(/^(\d\d?\d\d)(0[1-9]|1[012])(0[1-9]|[12][0-9]|3[01])$/) == -1) range = '';
                o.high = o.defaults.high = range;
        };
        o.setDisabledDays = function(dayArray) {
                o.disableDays = o.defaults.disableDays = dayArray;
        };
        o.setFirstDayOfWeek = function(e) {
                if ( e == null ) e = document.parentWindow.event;
                var elem = e.target != null ? e.target : e.srcElement;

                if(elem.tagName.toLowerCase() != "th") {
                        while(elem.tagName.toLowerCase() != "th") elem = elem.parentNode;
                }

                var cnt = 0;

                while(elem.previousSibling) {
                        elem = elem.previousSibling;
                        if(elem.tagName.toLowerCase() == "th") cnt++;
                }

                o.firstDayOfWeek = (o.firstDayOfWeek + cnt) % 7;
                o.updateTable();

                return o.killEvent(e);
        };
        o.trueBody = function() {
                return
        };
        o.resize = function() {
                if(!o.created || !o.getElem()) return;
                
                o.div.style.visibility = "hidden";
                o.div.style.display = "block";
                
                var osh = o.div.offsetHeight;
                var osw = o.div.offsetWidth;
                
                o.div.style.visibility = "visible";
                o.div.style.display = "none";
                
                var elem          = document.getElementById('fd-but-' + o.id);
                var pos           = datePickerController.findPosition(elem);
                var trueBody      = (document.compatMode && document.compatMode!="BackCompat")? document.documentElement : document.body;
                
                if ( parseInt(trueBody.clientWidth+trueBody.scrollLeft) < parseInt(osw+pos[0])) {
                        o.div.style.left = Math.abs(parseInt((trueBody.clientWidth+trueBody.scrollLeft) - osw)) + "px";
                } else {
                        o.div.style.left  = pos[0] + "px";
                };

                if ( parseInt(trueBody.clientHeight+trueBody.scrollTop) < parseInt(osh+pos[1]+elem.offsetHeight+2)) {
                        o.div.style.top   = Math.abs(parseInt(pos[1] - (osh + 2))) + "px";
                } else {
                        o.div.style.top   = Math.abs(parseInt(pos[1] + elem.offsetHeight + 2)) + "px";
                };
        };
        o.equaliseDates = function() {
                var clearDayFound = false;
                var tmpDate;
                for(var i = o.low; i <= o.high; i++) {
                        tmpDate = String(i);
                        if(!o.disableDays[new Date(tmpDate.substr(4,2) + '/' + tmpDate.substr(6,2) + '/' + tmpDate.substr(0,4)).getDay() - 1]) {
                                clearDayFound = true;
                                break;
                        };
                };
                if(!clearDayFound) o.disableDays = o.defaults.disableDays = [0,0,0,0,0,0,0];
        };
        o.outOfRange = function(tmpDate) {
                if(!o.low && !o.high) return false;
                
                var level = false;
                if(!tmpDate) {
                        level = true;
                        tmpDate = o.date;
                };
                
                var d           = (tmpDate.getDate() < 10) ? "0" + tmpDate.getDate() : tmpDate.getDate();
                var m           = ((tmpDate.getMonth() + 1) < 10) ? "0" + (tmpDate.getMonth() + 1) : tmpDate.getMonth() + 1;
                var y           = tmpDate.getFullYear();
                var dt          = (y+' '+m+' '+d).replace(/ /g,'');
                
                if(o.low) {
                        if(parseInt(dt) < parseInt(o.low)) {
                                if(!level) return true;
                                o.date = new Date( o.low.substr(4,2) + '/' + o.low.substr(6,2) + '/' + o.low.substr(0,4) );
                                return false;
                        };
                };
                if(o.high) {
                        if(parseInt(dt) > parseInt(o.high)) {
                                if(!level) return true;
                                o.date = new Date( o.high.substr(4,2) + '/' + o.high.substr(6,2) + '/' + o.high.substr(0,4) );
                        };
                };
                return false;
        };
        o.create = function() {

                /*@cc_on@*/
                /*@if(@_jscript_version <= 5.6)
                        if(!document.getElementById("iePopUpHack")) {
                                var loc = "./blank.html";
                                var scriptFiles = document.getElementsByTagName('head')[0].getElementsByTagName('script');
                                for(var i = 0, scriptFile; scriptFile = scriptFiles[i]; i++) {
                                        if(scriptFile.src && scriptFile.src.match(/datepicker.js$/)) {
                                                loc = scriptFile.src.replace("datepicker.js", "blank.html");
                                                break;
                                        };
                                };

                                o.iePopUp = document.createElement('iframe');
                                o.iePopUp.src = loc;
                                o.iePopUp.setAttribute('className','iehack');
                                o.iePopUp.scrolling="no";
                                o.iePopUp.frameBorder="0";
                                o.iePopUp.name = o.iePopUp.id = "iePopUpHack";
                                document.body.appendChild(o.iePopUp);
                        } else {
                                o.iePopUp = document.getElementById("iePopUpHack");
                        };
                /*@end@*/
                
                if(typeof(fdLocale) == "object" && o.locale) {
                        datePicker.titles  = fdLocale.titles;
                        datePicker.months  = fdLocale.months;
                        datePicker.fullDay = fdLocale.fullDay;
                        // Optional parameters
                        if(fdLocale.dayAbbr) datePicker.dayAbbr = fdLocale.dayAbbr;
                        if(fdLocale.firstDayOfWeek) o.firstDayOfWeek = o.defaults.firstDayOfWeek = fdLocale.firstDayOfWeek;
                };
                
                o.div = document.createElement('div');
                o.div.style.zIndex = 9999;
                o.div.id = "fd-"+o.id;
                var tableBody = document.createElement('tbody');
                var tableHead = document.createElement('thead');
                var nbsp = String.fromCharCode( 160 );
                
                o.table = document.createElement('table');
                o.div.className = "datePicker";

                var tr = document.createElement('tr');
                var th = document.createElement('th');

                // previous year
                var tmpelem = document.createElement('button');
                tmpelem.setAttribute("type", "button");
                tmpelem.className = "prev-but";
                tmpelem.appendChild(document.createTextNode('\u00AB'));
                tmpelem.title = datePicker.titles[2];
                tmpelem.onmousedown = function(e) { this.blur(); o.events.incDec(e,0,-1,0); };
                tmpelem.onmouseup = o.events.clearTimer;
                th.appendChild( tmpelem );
                
                // previous month
                var tmpelem = document.createElement('button');
                tmpelem.setAttribute("type", "button");
                tmpelem.className = "prev-but";
                tmpelem.appendChild(document.createTextNode("\u2039"));
                tmpelem.title = datePicker.titles[0];
                tmpelem.onmousedown = function(e) { this.blur(); o.events.incDec(e,0,0,-1); };
                tmpelem.onmouseup = o.events.clearTimer;
                th.appendChild( tmpelem );
                tr.appendChild( th );

                // title bar
                o.titleBar = document.createElement('th');

                /*@cc_on
                /*@if (@_win32)
                o.titleBar.setAttribute('colSpan','5');
                @else @*/
                o.titleBar.setAttribute('colspan','5');
                /*@end
                @*/

                o.titleBar.setAttribute('text-align','center');
                tr.appendChild( o.titleBar );

                th = document.createElement('th');

                // next month
                var tmpelem = document.createElement('button');
                tmpelem.setAttribute("type", "button");
                tmpelem.className = "next-but";
                tmpelem.appendChild(document.createTextNode('\u203A'));
                tmpelem.title = datePicker.titles[1];
                tmpelem.onmousedown = function(e) { this.blur(); o.events.incDec(e,0,0,1); };
                tmpelem.onmouseup = o.events.clearTimer;

                th.appendChild( tmpelem );

                // next year
                var tmpelem = document.createElement('button');
                tmpelem.setAttribute("type", "button");
                tmpelem.className = "next-but";
                tmpelem.appendChild(document.createTextNode('\u00BB'));
                tmpelem.title = datePicker.titles[3];
                tmpelem.onmousedown = function(e) { this.blur(); o.events.incDec(e,0,1,0); };
                tmpelem.onmouseup = o.events.clearTimer;
                th.appendChild( tmpelem );
                
                tr.appendChild( th );

                tableHead.appendChild(tr);

                var row, col;
                
                for(var rows = 0; rows < 7; rows++) {
                        row = document.createElement('tr');
                        for(var cols = 0; cols < 7; cols++) {
                                col = (rows == 0) ? document.createElement('th') : document.createElement('td');
                                if(rows != 0) {
                                        col.appendChild(document.createTextNode(nbsp));
                                } else {
                                        col.className = "date-picker-day-header";
                                        col.scope = "col";
                                };

                                row.appendChild(col);
                        }
                        if(rows != 0) tableBody.appendChild(row);
                        else          tableHead.appendChild(row);
                };
                o.table.appendChild( tableHead );
                o.table.appendChild( tableBody );
                
                o.div.appendChild( o.table );
                o.created = true;

                document.getElementsByTagName('body')[0].appendChild( o.div );
        };
        o.setDateFromInput = function() {
                o.dateSet = null;
                
                var elem = o.getElem();
                if(!elem) return;
                
                var date = elem.value;

                var d,m,y,dt,dates;

                d = o.format.replace(/-/g,'').indexOf('d');
                m = o.format.replace(/-/g,'').indexOf('m');
                y = o.format.replace(/-/g,'').indexOf('y');

                if(o.splitDate) {
                        dates = [];

                        dates[m] = document.getElementById(o.id+'-mm').value;
                        if(dates[m] < 1 || dates[m] > 12) dates[m] = "";
                        
                        dates[d] = document.getElementById(o.id+'-dd').value;
                        if(dates[d] < 1 || dates[d] > datePicker.daysPerMonth[dates[m]-1]) dates[d] = "";

                        dates[y] = date;
                } else {
                        if(date.match(/^[0-9]{4}$/)) {
                                if(date > 1600 && date < 2030) {
                                        o.date.setFullYear(date);
                                        return;
                                };
                        };
                        
                        dates = date.split(o.divider);
                
                        if(dates.length != 3) {
                                o.date = new Date();
                                return;
                        };
                };

                var check = new Date( dates[y] + "/" + dates[m] + "/" + dates[d] );
                if(check == 'Invalid Date' /*@cc_on@*/ /*@if(@_win32) || check == 'NaN' /*@end@*/) {
                        o.date = new Date();
                        return;
                };

                o.date.setMonth(dates[m]-1);
                o.date.setFullYear(dates[y]);
                o.date.setDate(dates[d]);
                
                o.dateSet = new Date(o.date);
        };
        o.returnFormattedDate = function() {
                var elem = o.getElem();
                if(!elem) return;
                
                var d           = (o.date.getDate() < 10) ? "0" + o.date.getDate() : o.date.getDate();
                var m           = ((o.date.getMonth() + 1) < 10) ? "0" + (o.date.getMonth() + 1) : o.date.getMonth() + 1;
                var yyyy        = o.date.getFullYear();

                var weekDay     = ( o.date.getDay() + 6 ) % 7;

                if(!(o.disableDays[weekDay])) {
                        if(o.splitDate) {
                                document.getElementById(o.id+"-dd").value = d;
                                document.getElementById(o.id+"-mm").value = m;
                                elem.value = yyyy;
                                
                                document.getElementById(o.id+"-dd").focus();
                                if(document.getElementById(o.id+"-dd").onchange) document.getElementById(o.id+"-dd").onchange();
                                if(document.getElementById(o.id+"-mm").onchange) document.getElementById(o.id+"-mm").onchange();
                        } else {
                                elem.value = o.format.replace('y',yyyy).replace('m',m).replace('d',d).replace(/-/g,o.divider);
                                elem.focus();
                        };
                        if(elem.onchange) elem.onchange();
                };
        };
        // Credit where credit's due:
        
        // Most of the logic for this method from the webfx date-picker
        // http://webfx.eae.net/
        
        o.updateTable = function() {

                if(document.getElementById("date-picker-hover")) {
                        document.getElementById("date-picker-hover").id = "";
                };

                var i;
                var str = "";
                var rows = 6;
                var cols = 7;
                var currentWeek = 0;
                var nbsp = String.fromCharCode( 160 );

                var cells = new Array( rows );

                for ( i = 0; i < rows; i++ ) {
                        cells[i] = new Array( cols );
                };

                o.outOfRange();

                // Set the tmpDate to this month
                var tmpDate = new Date( o.date.getFullYear(), o.date.getMonth(), 1 );
                var today = new Date();

                // titleBar
                var titleText = datePicker.months[o.date.getMonth()] + nbsp + o.date.getFullYear();
                while(o.titleBar.firstChild) o.titleBar.removeChild(o.titleBar.firstChild);
                o.titleBar.appendChild(document.createTextNode(titleText));

                for ( i = 1; i < 32; i++ ) {
                
                        tmpDate.setDate( i );
                        var weekDay = ( tmpDate.getDay() + 6 ) % 7;
                        var colIndex = ( (weekDay - o.firstDayOfWeek) + 7 ) % 7;
                        var cell     = { text:"", className:"", id:"" };
                        
                        if ( tmpDate.getMonth() == o.date.getMonth() ) {
                        
                                cells[currentWeek][colIndex] = { text:"", className:"", id:"" };

                                var isToday = tmpDate.getDate() == today.getDate() &&
                                              tmpDate.getMonth() == today.getMonth() &&
                                              tmpDate.getFullYear() == today.getFullYear();

                                if ( o.dateSet != null && o.dateSet.getDate() == tmpDate.getDate() && o.dateSet.getMonth() == tmpDate.getMonth() && o.dateSet.getFullYear() == tmpDate.getFullYear()) {
                                        cells[currentWeek][colIndex].className = "date-picker-selected-date";
                                };
                                if ( o.date.getDate() == tmpDate.getDate() && o.date.getFullYear() == tmpDate.getFullYear()) {
                                        cells[currentWeek][colIndex].id = "date-picker-hover";
                                };

                                if(o.highlightDays[weekDay]) {
                                        cells[currentWeek][colIndex].className += " date-picker-highlight";
                                };
                                if ( isToday ) {
                                        cells[currentWeek][colIndex].className = "date-picker-today";
                                };
                                if(o.outOfRange(tmpDate)) {
                                        cells[currentWeek][colIndex].className = "out-of-range";
                                } else if(o.disableDays[weekDay]) {
                                        cells[currentWeek][colIndex].className = "day-disabled";
                                };
                                cells[currentWeek][colIndex].text = tmpDate.getDate();
                                if ( colIndex == 6 ) currentWeek++;
                        };
                };

                // Table headers
                var lnk, d;
                var ths = o.table.getElementsByTagName('thead')[0].getElementsByTagName('tr')[1].getElementsByTagName('th');
                for ( var y = 0; y < 7; y++ ) {
                        d = (o.firstDayOfWeek + y) % 7;

                        while(ths[y].firstChild) ths[y].removeChild(ths[y].firstChild);

                        ths[y].title = datePicker.fullDay[d];

                        // Don't create a button for the first day header
                        if(y > 0) {
                                but = document.createElement("BUTTON");
                                but.className = "fd-day-header";
                                but.onclick = but.onkeypress = ths[y].onclick = o.setFirstDayOfWeek;
                                but.appendChild(document.createTextNode(datePicker.dayAbbr ? datePicker.dayAbbr[d] : datePicker.fullDay[d].charAt(0)));
                                ths[y].appendChild(but);
                                but.title = datePicker.fullDay[d];
                        } else {
                                ths[y].appendChild(document.createTextNode(datePicker.dayAbbr ? datePicker.dayAbbr[d] : datePicker.fullDay[d].charAt(0)));
                                ths[y].onclick = null;
                        };
                };


                var trs = o.table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

                var tmpCell;

                for ( var y = 0; y < rows; y++ ) {
                        var tds = trs[y].getElementsByTagName('td');
                        for (var x = 0; x < cols; x++) {
                                tmpCell = tds[x];

                                while(tmpCell.firstChild) tmpCell.removeChild(tmpCell.firstChild);

                                if ( typeof cells[y][x] != "undefined" ) {
                                        tmpCell.className = cells[y][x].className;
                                        tmpCell.id = cells[y][x].id;
                                        
                                        tmpCell.appendChild(document.createTextNode(cells[y][x].text));
                                        
                                        if(cells[y][x].className != "out-of-range") {
                                                tmpCell.onmouseover = o.events.onmouseover;
                                                tmpCell.onclick =  cells[y][x].className == "day-disabled" ? o.killEvent : o.events.onclick;
                                                tmpCell.title = datePicker.months[o.date.getMonth()] + nbsp + cells[y][x].text + "," + nbsp + o.date.getFullYear();
                                        } else {
                                                tmpCell.onmouseover = null;
                                                tmpCell.onclick = o.killEvent;
                                                tmpCell.title = "";
                                        };
                                } else {
                                        tmpCell.className = "";
                                        tmpCell.id = "";
                                        tmpCell.onmouseover = null;
                                        tmpCell.onclick = function(e) { return o.killEvent(e); };
                                        tmpCell.appendChild(document.createTextNode(nbsp));
                                        tmpCell.title = "";
                                };
                        };
                };
        };
        o.init = function() {
                if(o.low && o.high && (o.high - o.low < 7)) {
                        o.equaliseDates();
                };
                o.resize();
                o.setDateFromInput();
                o.fade();
                o.ieHack(true);
        };
        o.ieHack = function(cleanup) {
                // IE hack
                if(o.iePopUp) {
                        o.iePopUp.style.display = "block";
                        o.iePopUp.style.top = (o.div.offsetTop + 2) + "px";
                        o.iePopUp.style.left = o.div.offsetLeft + "px";
                        o.iePopUp.style.width = o.div.clientWidth + "px";
                        o.iePopUp.style.height = (o.div.clientHeight - 2) + "px";
                        if(cleanup) o.iePopUp.style.display = "none";
                }
        };
        o.show = function() {
                var elem = o.getElem();
                if(!elem || o.visible || elem.disabled) return;

                o.reset();
                o.setDateFromInput();
                o.updateTable();
                o.resize();
                o.ieHack(false);

                datePickerController.addEvent(document, "mousedown", o.events.onmousedown);
                datePickerController.addEvent(document, "keypress", o.events.onkeydown);
                
                // Internet Explorer requires the keydown event in order to catch arrow keys
                
                /*@cc_on@*/
                /*@if(@_win32)
                        datePickerController.removeEvent(document, "keypress", o.events.onkeydown);
                        datePickerController.addEvent(document, "keydown", o.events.onkeydown);
                /*@end@*/

                o.opacityTo = 90;
                o.div.style.display = "block";
                o.ieHack(false);
                o.fade();
                o.visible = true;

        };
        o.hide = function()   {
                try {
                        datePickerController.removeEvent(document, "mousedown", o.events.onmousedown);
                        datePickerController.removeEvent(document, "keypress", o.events.onkeydown);
                        datePickerController.removeEvent(document, "keydown", o.events.onkeydown);
                } catch(e) {
                
                };
                if(o.iePopUp) {
                        o.iePopUp.style.display = "none";
                };
                o.opacityTo = 0;
                o.fade();
                o.visible = false;
        };
        o.create();
        o.init();
};

datePickerController = {
        datePickers: {},
        addEvent: function(obj, type, fn, tmp) {
                tmp || (tmp = true);
                if( obj.attachEvent ) {
                        obj["e"+type+fn] = fn;
                        obj[type+fn] = function(){obj["e"+type+fn]( window.event );};
                        obj.attachEvent( "on"+type, obj[type+fn] );
                } else {
                        obj.addEventListener( type, fn, true );
                };
        },
        removeEvent: function(obj, type, fn, tmp) {
                tmp || (tmp = true);
                if( obj.detachEvent ) {
                        obj.detachEvent( "on"+type, obj[type+fn] );
                        obj[type+fn] = null;
                } else {
                        obj.removeEventListener( type, fn, true );
                };
        },
        findPosition: function(obj) {
                var curleft = 0;
                var curtop  = 0;
                var orig    = obj;
                
                if(obj.offsetParent) {
                        while(obj.offsetParent) {
                                curleft += obj.offsetLeft;
                                curtop  += obj.offsetTop;
                                obj = obj.offsetParent;
                        };
                } else if (obj.x) {
                        curleft += obj.x;
                        curtop  += obj.y;
                };
                return [ curleft, curtop ];
        },
        hideAll: function(exception) {
                for(dp in datePickerController.datePickers) {
                        if(exception && exception == datePickerController.datePickers[dp].id) continue;
                        datePickerController.datePickers[dp].hide();
                };
        },
        cleanUp: function() {
                var dp;
                for(dp in datePickerController.datePickers) {
                        if(!document.getElementById(datePickerController.datePickers[dp].id)) {
                                dpElem = document.getElementById("fd-"+datePickerController.datePickers[dp].id);
                                if(dpElem) {
                                        dpElem.parentNode.removeChild(dpElem);
                                };
                                datePickerController.datePickers[dp] = null;
                                delete datePickerController.datePickers[dp];
                        };
                };
        },
        dateFormat: function(dateIn, favourMDY) {
                var dateTest = [
                        { regExp:/^(0[1-9]|[12][0-9]|3[01])([- \/.])(0[1-9]|1[012])([- \/.])(\d\d?\d\d)$/, d:1, m:3, y:5 },  // dmy
                        { regExp:/^(0[1-9]|1[012])([- \/.])(0[1-9]|[12][0-9]|3[01])([- \/.])(\d\d?\d\d)$/, d:3, m:1, y:5 },  // mdy
                        { regExp:/^(\d\d?\d\d)([- \/.])(0[1-9]|1[012])([- \/.])(0[1-9]|[12][0-9]|3[01])$/, d:5, m:3, y:1 }   // ymd
                        ];

                var start;
                var cnt = 0;
                
                while(cnt < 3) {
                        start = (cnt + (favourMDY ? 4 : 3)) % 3;

                        if(dateIn.match(dateTest[start].regExp)) {
                                res = dateIn.match(dateTest[start].regExp);
                                y = res[dateTest[start].y];
                                m = res[dateTest[start].m];
                                d = res[dateTest[start].d];
                                if(m.length == 1) m = "0" + m;
                                if(d.length == 1) d = "0" + d;
                                if(y.length != 4) y = (parseInt(y) < 50) ? '20' + y : '19' + y;

                                return y+m+d;
                        };
                        
                        cnt++;
                };

                return 0;
        },
        create: function() {
                if(!datePicker.isSupported) return;

                datePickerController.cleanUp();
                
                var inputs = document.getElementsByTagName('input');

                var regExp1 = /disable-days-([1-7]){1,6}/g;             // the days to disable
                var regExp3 = /highlight-days-([1-7]){1,7}/g;           // the days to highlight in red
                var regExp4 = /range-low-([0-9\-]){10}/g;               // the lowest selectable date
                var regExp5 = /range-high-([0-9\-]){10}/g;              // the highest selectable date
                var regExp6 = /format-([dmy\-]{5})/g;                   // the input/output date format
                var regExp7 = /divider-(dot|slash|space|dash)/g;        // the character used to divide the date
                var regExp8 = /no-locale/g;                             // do not attempt to detect the browser language

                for(var i=0, inp; inp = inputs[i]; i++) {
                        if(inp.className && (inp.className.search(regExp6) != -1 || inp.className.search(/split-date/) != -1) && inp.type == "text" && inp.name) {

                                if(!inp.id) {
                                        // Internet explorer requires you to give each input a unique ID attribute.
                                        if(document.getElementById(inp.name)) continue;
                                        inp.id = inp.name;
                                };

                                var options = {
                                        id:inp.id,
                                        low:"",
                                        high:"",
                                        divider:"/",
                                        format:"d-m-y",
                                        highlightDays:[0,0,0,0,0,1,1],
                                        disableDays:[0,0,0,0,0,0,0],
                                        locale:inp.className.search(regExp8) == -1,
                                        splitDate:0
                                };

                                // Split the date into three parts ?
                                if(inp.className.search(/split-date/) != -1) {
                                        if(document.getElementById(inp.id+'-dd') && document.getElementById(inp.id+'-mm') && document.getElementById(inp.id+'-dd').tagName.toLowerCase() == "input" && document.getElementById(inp.id+'-mm').tagName.toLowerCase() == "input") {
                                                options.splitDate = 1;
                                        };
                                };
                                
                                // Date format(variations of d-m-y)
                                if(inp.className.search(regExp6) != -1) {
                                        options.format = inp.className.match(regExp6)[0].replace('format-','');
                                };
                                
                                // What divider to use, a "/", "-", "." or " "
                                if(inp.className.search(regExp7) != -1) {
                                        var divider = inp.className.match(regExp7)[0].replace('divider-','');
                                        switch(divider.toLowerCase()) {
                                                case "dot":
                                                        options.divider = ".";
                                                        break;
                                                case "space":
                                                        options.divider = " ";
                                                        break;
                                                case "dash":
                                                        options.divider = "-";
                                                        break;
                                                default:
                                                        options.divider = "/";
                                        };
                                };

                                // The days to highlight
                                if(inp.className.search(regExp3) != -1) {
                                        var tmp = inp.className.match(regExp3)[0].replace(/highlight-days-/, '');
                                        options.highlightDays = [0,0,0,0,0,0,0];
                                        for(var j = 0; j < tmp.length; j++) {
                                                options.highlightDays[tmp.charAt(j) - 1] = 1;
                                        };
                                };

                                // The days to disable
                                if(inp.className.search(regExp1) != -1) {
                                        var tmp = inp.className.match(regExp1)[0].replace(/disable-days-/, '');
                                        options.disableDays = [0,0,0,0,0,0,0];
                                        for(var j = 0; j < tmp.length; j++) {
                                                options.disableDays[tmp.charAt(j) - 1] = 1;
                                        };
                                };
                                
                                // The lower limit
                                if(inp.className.search(regExp4) != -1) {
                                        options.low = datePickerController.dateFormat(inp.className.match(regExp4)[0].replace(/range-low-/, ''), options.format.charAt(0) == "m");
                                        if(options.low == 0) {
                                                options.low = '';
                                        };
                                };

                                // The higher limit
                                if(inp.className.search(regExp5) != -1) {
                                        options.high = datePickerController.dateFormat(inp.className.match(regExp5)[0].replace(/range-high-/, ''), options.format.charAt(0) == "m");
                                        if(options.high == 0) {
                                                options.high = '';
                                        };
                                };

                                // Datepicker is already created so reset it's defaults
                                if(document.getElementById('fd-'+inp.id)) {
                                        for(var opt in options) {
                                                datePickerController.datePickers[inp.id].defaults[opt] = options[opt];
                                        };
                                };
                                
                                // Create the button (if needs be)
                                if(!document.getElementById("fd-but-" + inp.id)) {
                                        var but = document.createElement('button');
                                        but.setAttribute("type", "button");
                                        but.className = "date-picker-control";

                                        but.id = "fd-but-" + inp.id;
                                        but.appendChild(document.createTextNode(String.fromCharCode( 160 )));
                                
                                        if(inp.nextSibling) {
                                                inp.parentNode.insertBefore(but, inp.nextSibling);
                                        } else {
                                                inp.parentNode.appendChild(but);
                                        };

                                } else {
                                        var but = document.getElementById("fd-but-" + inp.id);
                                };
                                
                                // Add button events
                                but.onclick = but.onpress = function() {
                                        var inpId = this.id.replace('fd-but-','');

                                        datePickerController.hideAll(inpId);
                                        if(inpId in datePickerController.datePickers && !datePickerController.datePickers[inpId].visible) {
                                                datePickerController.datePickers[inpId].show();
                                        };
                                        return false;
                                };
                                
                                // Create the datePicker (if needs be)
                                if(!document.getElementById('fd-'+inp.id)) {
                                        datePickerController.datePickers[inp.id] = new datePicker(options);
                                };
                        };
                };
        }

};


})();

datePickerController.addEvent(window, 'load', datePickerController.create);

