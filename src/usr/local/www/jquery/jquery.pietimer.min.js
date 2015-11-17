/*
 Copyright (c) 2012, Northfield X Ltd
All rights reserved.

Modified BSD License

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
 Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
 Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
 Neither the name of the <organization> nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
(function(d){var k={seconds:10,color:"rgba(255, 255, 255, 0.8)",height:null,width:null},e=3*Math.PI/2,g=Math.PI/180,f=function(b,a,c){null===a.width&&(a.width=b.width());null===a.height&&(a.height=b.height());this.settings=a;this.jquery_object=b;this.interval_id=null;this.current_value=360;this.initial_time=new Date;this.accrued_time=0;this.callback=c;this.is_paused=!0;this.is_reversed="undefined"!=typeof a.is_reversed?a.is_reversed:!1;this.jquery_object.html('<canvas class="pie_timer" width="'+a.width+
'" height="'+a.height+'"></canvas>');this.canvas=this.jquery_object.children(".pie_timer")[0]};f.prototype={start:function(){this.is_paused&&(this.initial_time=new Date-this.accrued_time,0>=this.current_value&&(this.current_value=360),this.interval_id=setInterval(d.proxy(this.run_timer,this),40),this.is_paused=!1)},pause:function(){this.is_paused||(this.accrued_time=new Date-this.initial_time,clearInterval(this.interval_id),this.is_paused=!0)},run_timer:function(){if(this.canvas.getContext)if(this.elapsed_time=
(new Date-this.initial_time)/1E3,this.current_value=360*Math.max(0,this.settings.seconds-this.elapsed_time)/this.settings.seconds,0>=this.current_value)clearInterval(this.interval_id),this.canvas.width=this.settings.width,d.isFunction(this.callback)&&this.callback.call(),this.is_paused=!0;else{this.canvas.width=this.settings.width;var b=this.canvas.getContext("2d"),a=[this.canvas.width,this.canvas.height],c=Math.min(a[0],a[1])/2,a=[a[0]/2,a[1]/2],h=this.is_reversed;b.beginPath();b.moveTo(a[0],a[1]);
b.arc(a[0],a[1],c,h?e-(360-this.current_value)*g:e-this.current_value*g,e,h);b.closePath();b.fillStyle=this.settings.color;b.fill()}}};var l=function(b,a){var c=d.extend({},k,b);return this.each(function(){var b=d(this),e=new f(b,c,a);b.data("pie_timer",e)})},m=function(b){b in f.prototype||d.error("Method "+b+" does not exist on jQuery.pietimer");var a=Array.prototype.slice.call(arguments,1);return this.each(function(){var c=d(this).data("pie_timer");if(!c)return!0;c[b].apply(c,a)})};d.fn.pietimer=
function(b){return"object"===typeof b||!b?l.apply(this,arguments):m.apply(this,arguments)}})(jQuery);
