// As usual, we keep the generic functions out of the namespace
function addEvent(obj, evType, fn, useCapture){
        if (obj.addEventListener){
                obj.addEventListener(evType, fn, useCapture);
                return true;
        } else if (obj.attachEvent){
                var r = obj.attachEvent("on"+evType, fn);
                return r;
        }
        return false;
}

function removeEvent(obj, evType, fn, useCapture){
        if (obj.removeEventListener){
                obj.removeEventListener(evType, fn, useCapture);
                return true;
        } else if (obj.detachEvent){
                var r = obj.detachEvent("on"+evType, fn);
                return r;
        }
        return false;
}

if(!String.prototype.trim) String.prototype.trim = function() { return this.replace(/^\s*/,'').replace(/\s*$/, ''); }

var incrementalInputController;

// Encapsulate the Timer and incrementalInput objects
(function() {

// WEBFX Timer : http://www.webfx.com/
function Timer(nPauseTime) {
        this._pauseTime = typeof nPauseTime == "undefined" ? 1000 : nPauseTime;
        this._timer = null;
        this._isStarted = false;
}

Timer.prototype.start = function () {
        if (this.isStarted())
                this.stop();
        var oThis = this;
        this._timer = window.setTimeout(function () {
                if (typeof oThis.ontimer == "function")
                        oThis.ontimer();
        }, this._pauseTime);
        this._isStarted = false;
};

Timer.prototype.stop = function () {
        if (this._timer != null)
                window.clearTimeout(this._timer);
        this._isStarted = false;
};

Timer.prototype.isStarted = function () {
        return this._isStarted;
};

Timer.prototype.getPauseTime = function () {
        return this._pauseTime;
};

Timer.prototype.setPauseTime = function (nPauseTime) {
        this._pauseTime = nPauseTime;
};

function incrementalInput(inp, range, increment, classInc, classDec) {
        if(!inp || !range) return;

        this._inp = inp;
        this._buttonInc;
        this._buttonDec;
        this._value;
        this._classInc = classInc;
        this._classDec = classDec;
        this._minv = Number(range[0]);
        this._maxv = Number(range[1]);
        this._incBase = Number(increment) || 1;
        this._precision = 0;
        if(increment.indexOf('.') != -1) {
                this._precision = increment.substr(increment.indexOf('.')+1, increment.length);
                this._precision = this._precision.length;
        }

        this._increment;
        this._timerInc = 1000;
        this._timer = new Timer(1000);
        this._stop = false;
        this._key = false;

        this._events = {

                dec: function(e) {
                        self._increment = -self._incBase;
                        self.updateValue();
                        return false;
                },
                inc: function(e) {
                        self._increment = self._incBase;
                        self.updateValue();
                        return false;
                },
                keydec: function(e) {
                        var kc;
                        if (!e) var e = window.event;

                        if (e.keyCode) kc = e.keyCode;
                        else if (e.charCode) kc = e.charCode;

                        if ( kc != 13 || self._key ) return true;

                        self._key = true;
                        self._increment = -self._incBase;
                        self._timerInc = 1000;
                        self.updateValue();
                        return false;
                },
                keyinc: function(e) {
                        var kc;
                        if (!e) var e = window.event;

                        if (e.keyCode) kc = e.keyCode;
                        else if (e.charCode) kc = e.charCode;

                        if ( kc != 13 || self._key ) return true;

                        self._key = true;
                        self._increment = self._incBase;
                        self._timerInc = 1000;
                        self.updateValue();
                        return false;
                },
                clearTimer: function(e) {
                        self._key = false;
                        self._events.stopTimer();
                },
                stopTimer: function(e) {
                        self._timer.stop();
                        self._timerInc = 1000;
                        self._timer.setPauseTime(self._timerInc);
                },
                onchange: function(e){
                        var value = Number(parseFloat(self._inp.value).toFixed(self._precision));

                        if( Number(value % self._incBase).toFixed(self._precision) != self._incBase ) {
                                value -= Number(parseFloat(value % self._incBase)).toFixed(self._precision);
                        };
                        if(value < self._minv) value = self._minv;
                        else if(value > self._maxv) value = self._maxv;
                        self._inp.value = parseFloat(value).toFixed(self._precision);
                }
        };

        this.updateValue = function() {
                if(self._inp.disabled) {
                        stopTimer();
                        return;
                }


                var value = Number(parseFloat(self._inp.value).toFixed(self._precision));
                var stop = self._timerInc == 0 ? true : false;

                if( Math.abs(Number(value % self._incBase).toFixed(self._precision)) != self._incBase ) {
                        value -= Number(parseFloat(value % self._incBase)).toFixed(self._precision);
                }

                value += Number(parseFloat(self._increment).toFixed(self._precision));

                if(value < self._minv) {
                        value = self._minv;
                        stop = true;
                } else if(value > self._maxv) {
                        value = self._maxv;
                        stop = true;
                }

                self._inp.value = parseFloat(value).toFixed(self._precision);

                if(self._timerInc > 50) {
                        self._timerInc = 50 + Math.round(((self._timerInc - 50) / 1.8));
                }

                self._timer.setPauseTime(self._timerInc);
                if(!stop) self._timer.start();
        }

        this.construct = function() {
                var h = self._inp.offsetHeight;

                self._inp.onchange = self._events.onchange;

                self._buttonInc = document.createElement('button');
                self._buttonDec = document.createElement('button');

                if(self._classDec) self._buttonDec.className = self._classDec;
                if(self._classInc) self._buttonInc.className = self._classInc;

                self._buttonDec.setAttribute('type','button');
                self._buttonInc.setAttribute('type','button');

                self._buttonDec.appendChild(document.createTextNode('-'));
                self._buttonInc.appendChild(document.createTextNode('+'));

                self._buttonDec.onmousedown = self._events.dec;
                self._buttonInc.onmousedown = self._events.inc;

                addEvent(self._buttonDec, "keypress", self._events.keydec, true);
                addEvent(self._buttonDec, "keyup", self._events.clearTimer, true);
                addEvent(self._buttonInc, "keypress", self._events.keyinc, true);
                addEvent(self._buttonInc, "keyup", self._events.clearTimer, true);

                self._buttonInc.onmouseout  = self._events.stopTimer;
                self._buttonDec.onmouseout  = self._events.stopTimer;

                addEvent(document, 'mouseup', self._events.stopTimer, false);

                if(self._inp.nextSibling) {
                        self._inp.parentNode.insertBefore( self._buttonDec, self._inp.nextSibling );
                        self._inp.parentNode.insertBefore( self._buttonInc, self._inp.nextSibling );
                } else {
                        self._inp.parentNode.appendChild( self._buttonInc );
                        self._inp.parentNode.appendChild( self._buttonDec );
                };
        };

        var self = this;

        self._timer.ontimer = function() { self.updateValue(); }
        self.construct();
}

incremetalInputController = {
        inputCollection: [],
        constructor: function() {

                if(!document.getElementById || !document.createElement) return;

                // TODO : cut the regExps down to readable levels - they are hideous at present...
                var regExp_1 = /fd_incremental_inp_range_([-]{0,1}[0-9]+(f[0-9]+){0,1}){1}_([-]{0,1}[0-9]+(f[0-9]+){0,1}){1}/ig;
                var regExp_2 = /fd_increment_([0-9]+(f[0-9]+){0,1}){1}/ig;
                var regExp_3 = /fd_classname_inc_([\-_0-9a-zA-Z]+){1}/ig;
                var regExp_4 = /fd_classname_dec_([\-_0-9a-zA-Z]+){1}/ig;

                var inputCollection = document.getElementsByTagName('input');
                var obj, range, classname, classes, classInc, classDec, increment;

                for(var i = 0, inp; inp = inputCollection[i]; i++) {
                        if(inp.type == 'text' && inp.className && inp.className.search(regExp_1) != -1) {
                                classes = inp.className.split(' ');
                                increment = 1;
                                range = [0,0];
                                classInc = "";
                                classDec = "";

                                for(var z = 0, classname; classname = classes[z]; z++) {
                                        if(classname.search(regExp_1) != -1) {
                                                range = classname.trim();
                                                range = range.replace(/fd_incremental_inp_range_/ig, '');
                                                range = range.replace(/f/g,'.');
                                                range = range.split('_');
                                        } else if(classname.search(regExp_2) != -1) {
                                                increment = classname.trim();
                                                increment = increment.replace(/fd_increment_/ig, '');
                                                increment = increment.replace('f','.');
                                        } else if(classname.search(regExp_3) != -1) {
                                                classInc = classname.trim();
                                                classInc = classInc.replace(/fd_classname_inc_/ig, '');
                                        } else if(classname.search(regExp_4) != -1) {
                                                classDec = classname.trim();
                                                classDec = classDec.replace(/fd_classname_dec_/ig, '');
                                        }
                                }

                                if (inp.value.length == 0 || isNaN(inp.value) == true) { inp.value = 0; }

                                obj = new incrementalInput(inp, range, increment, classInc, classDec);
                                incremetalInputController.inputCollection.push(obj);
                        }
                }
        }
}

// Close and call anonymous function
})();

addEvent(window, 'load', incremetalInputController.constructor, true);