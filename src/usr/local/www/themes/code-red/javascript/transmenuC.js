/* =================================================================================================
 * TransMenu 
 * March, 2003
 * Customizable multi-level animated DHTML menus with transparency.
 * =================================================================================================
 * "Can I use this?"
 * 
 * Use of this library is governed by the Creative Commons Attribution 2.0 License. You can check it 
 * out at: http://creativecommons.org/licenses/by/2.0/
 *
 * Basically: You may copy, distribute, and eat this code as you wish. But you must give me credit 
 * for writing it. You may not misrepresent yourself as the author of this code.
 * =================================================================================================
 * "It's kinda hard to read, though"
 *
 * The uncompressed, commented version of this script can be found at: 
 * http://youngpup.net/projects/transMenus
 * ============================================================================================== */
TransMenu.spacerGif="themes/nervecenter/javascript/img/x.gif";TransMenu.dingbatOn="themes/nervecenter/javascript/img/submenu-on.gif";TransMenu.dingbatOff="themes/nervecenter/javascript/img/submenu-off.gif";TransMenu.dingbatSize=14;TransMenu.menuPadding=5;TransMenu.itemPadding=3;TransMenu.shadowSize=2;TransMenu.shadowOffset=3;TransMenu.shadowColor="#000000";TransMenu.shadowPng="themes/nervecenter/javascript/img/grey-40.png";TransMenu.backgroundColor="#990000";TransMenu.backgroundPng="themes/nervecenter/javascript/img/white-90.png";TransMenu.hideDelay=1000;TransMenu.slideTime=400;TransMenu.reference={topLeft:1,topRight:2,bottomLeft:3,bottomRight:4};TransMenu.direction={down:1,right:2};TransMenu.registry=[];TransMenu._maxZ=100;TransMenu.isSupported=function(){var ua=navigator.userAgent.toLowerCase();var pf=navigator.platform.toLowerCase();var an=navigator.appName;var r=false;if(ua.indexOf("gecko")>-1&&navigator.productSub>=20020605)r=true;else if(an=="Microsoft Internet Explorer"){if(document.getElementById){if(pf.indexOf("mac")==0){r=/msie (\d(.\d*)?)/.test(ua)&&Number(RegExp.$1)>=5.1;}
else r=true;}}
return r;}
TransMenu.initialize=function(){for(var i=0,menu=null;menu=this.registry[i];i++){menu.initialize();}}
TransMenu.renderAll=function(){var aMenuHtml=[];for(var i=0,menu=null;menu=this.registry[i];i++){aMenuHtml[i]=menu.toString();}
document.write(aMenuHtml.join(""));}
function TransMenu(oActuator,iDirection,iLeft,iTop,iReferencePoint,parentMenuSet){this.addItem=addItem;this.addMenu=addMenu;this.toString=toString;this.initialize=initialize;this.isOpen=false;this.show=show;this.hide=hide;this.items=[];this.onactivate=new Function();this.ondeactivate=new Function();this.onmouseover=new Function();this.onqueue=new Function();this.ondequeue=new Function();this.index=TransMenu.registry.length;TransMenu.registry[this.index]=this;var id="TransMenu"+this.index;var contentHeight=null;var contentWidth=null;var childMenuSet=null;var animating=false;var childMenus=[];var slideAccel=-1;var elmCache=null;var ready=false;var _this=this;var a=null;var pos=iDirection==TransMenu.direction.down?"top":"left";var dim=null;function addItem(sText,sUrl){var item=new TransMenuItem(sText,sUrl,this);item._index=this.items.length;this.items[item._index]=item;}
function addMenu(oMenuItem){if(!oMenuItem.parentMenu==this)throw new Error("Cannot add a menu here");if(childMenuSet==null)childMenuSet=new TransMenuSet(TransMenu.direction.right,-5,2,TransMenu.reference.topRight);var m=childMenuSet.addMenu(oMenuItem);childMenus[oMenuItem._index]=m;m.onmouseover=child_mouseover;m.ondeactivate=child_deactivate;m.onqueue=child_queue;m.ondequeue=child_dequeue;return m;}
function initialize(){initCache();initEvents();initSize();ready=true;}
function show(){if(ready){_this.isOpen=true;animating=true;setContainerPos();elmCache["clip"].style.visibility="visible";elmCache["clip"].style.zIndex=TransMenu._maxZ++;slideStart();_this.onactivate();}}
function hide(){if(ready){_this.isOpen=false;animating=true;for(var i=0,item=null;item=elmCache.item[i];i++)dehighlight(item);if(childMenuSet)childMenuSet.hide();slideStart();_this.ondeactivate();}}
function setContainerPos(){var sub=oActuator.constructor==TransMenuItem;var act=sub?oActuator.parentMenu.elmCache["item"][oActuator._index]:oActuator;var el=act;var x=0;var y=0;var minX=0;var maxX=(window.innerWidth?window.innerWidth:document.body.clientWidth)-parseInt(elmCache["clip"].style.width);var minY=0;var maxY=(window.innerHeight?window.innerHeight:document.body.clientHeight)-parseInt(elmCache["clip"].style.height);while(sub?el.parentNode.className.indexOf("transMenu")==-1:el.offsetParent){x+=el.offsetLeft;y+=el.offsetTop;if(el.scrollLeft)x-=el.scrollLeft;if(el.scrollTop)y-=el.scrollTop;el=el.offsetParent;}
if(oActuator.constructor==TransMenuItem){x+=parseInt(el.parentNode.style.left);y+=parseInt(el.parentNode.style.top);}
switch(iReferencePoint){case TransMenu.reference.topLeft:break;case TransMenu.reference.topRight:x+=act.offsetWidth;break;case TransMenu.reference.bottomLeft:y+=act.offsetHeight;break;case TransMenu.reference.bottomRight:x+=act.offsetWidth;y+=act.offsetHeight;break;}
x+=iLeft;y+=iTop;x=Math.max(Math.min(x,maxX),minX);y=Math.max(Math.min(y,maxY),minY);elmCache["clip"].style.left=x+"px";elmCache["clip"].style.top=y+"px";}
function slideStart(){var x0=parseInt(elmCache["content"].style[pos]);var x1=_this.isOpen?0:-dim;if(a!=null)a.stop();a=new Accelimation(x0,x1,TransMenu.slideTime,slideAccel);a.onframe=slideFrame;a.onend=slideEnd;a.start();}
function slideFrame(x){elmCache["content"].style[pos]=x+"px";}
function slideEnd(){if(!_this.isOpen)elmCache["clip"].style.visibility="hidden";animating=false;}
function initSize(){var ow=elmCache["items"].offsetWidth;var oh=elmCache["items"].offsetHeight;var ua=navigator.userAgent.toLowerCase();elmCache["clip"].style.width=ow+TransMenu.shadowSize+2+"px";elmCache["clip"].style.height=oh+TransMenu.shadowSize+2+"px";elmCache["content"].style.width=ow+TransMenu.shadowSize+"px";elmCache["content"].style.height=oh+TransMenu.shadowSize+"px";contentHeight=oh+TransMenu.shadowSize;contentWidth=ow+TransMenu.shadowSize;dim=iDirection==TransMenu.direction.down?contentHeight:contentWidth;elmCache["content"].style[pos]=-dim-TransMenu.shadowSize+"px";elmCache["clip"].style.visibility="hidden";if(ua.indexOf("mac")==-1||ua.indexOf("gecko")>-1){elmCache["background"].style.width=ow+"px";elmCache["background"].style.height=oh+"px";elmCache["background"].style.backgroundColor=TransMenu.backgroundColor;elmCache["shadowRight"].style.left=ow+"px";elmCache["shadowRight"].style.height=oh-(TransMenu.shadowOffset-TransMenu.shadowSize)+"px";elmCache["shadowRight"].style.backgroundColor=TransMenu.shadowColor;elmCache["shadowBottom"].style.top=oh+"px";elmCache["shadowBottom"].style.width=ow-TransMenu.shadowOffset+"px";elmCache["shadowBottom"].style.backgroundColor=TransMenu.shadowColor;}
else{elmCache["background"].firstChild.src=TransMenu.backgroundPng;elmCache["background"].firstChild.width=ow;elmCache["background"].firstChild.height=oh;elmCache["shadowRight"].firstChild.src=TransMenu.shadowPng;elmCache["shadowRight"].style.left=ow+"px";elmCache["shadowRight"].firstChild.width=TransMenu.shadowSize;elmCache["shadowRight"].firstChild.height=oh-(TransMenu.shadowOffset-TransMenu.shadowSize);elmCache["shadowBottom"].firstChild.src=TransMenu.shadowPng;elmCache["shadowBottom"].style.top=oh+"px";elmCache["shadowBottom"].firstChild.height=TransMenu.shadowSize;elmCache["shadowBottom"].firstChild.width=ow-TransMenu.shadowOffset;}}
function initCache(){var menu=document.getElementById(id);var all=menu.all?menu.all:menu.getElementsByTagName("*");elmCache={};elmCache["clip"]=menu;elmCache["item"]=[];for(var i=0,elm=null;elm=all[i];i++){switch(elm.className){case"items":case"content":case"background":case"shadowRight":case"shadowBottom":elmCache[elm.className]=elm;break;case"item":elm._index=elmCache["item"].length;elmCache["item"][elm._index]=elm;break;}}
_this.elmCache=elmCache;}
function initEvents(){for(var i=0,item=null;item=elmCache.item[i];i++){item.onmouseover=item_mouseover;item.onmouseout=item_mouseout;item.onclick=item_click;}
if(typeof oActuator.tagName!="undefined"){oActuator.onmouseover=actuator_mouseover;oActuator.onmouseout=actuator_mouseout;}
elmCache["content"].onmouseover=content_mouseover;elmCache["content"].onmouseout=content_mouseout;}
function highlight(oRow){oRow.className="item hover";if(childMenus[oRow._index])oRow.lastChild.firstChild.src=TransMenu.dingbatOn;}
function dehighlight(oRow){oRow.className="item";if(childMenus[oRow._index])oRow.lastChild.firstChild.src=TransMenu.dingbatOff;}
function item_mouseover(){if(!animating){highlight(this);if(childMenus[this._index])childMenuSet.showMenu(childMenus[this._index]);else if(childMenuSet)childMenuSet.hide();}}
function item_mouseout(){if(!animating){if(childMenus[this._index])childMenuSet.hideMenu(childMenus[this._index]);else dehighlight(this);}}
function item_click(){if(!animating){if(_this.items[this._index].url)location.href=_this.items[this._index].url;}}
function actuator_mouseover(){parentMenuSet.showMenu(_this);}
function actuator_mouseout(){parentMenuSet.hideMenu(_this);}
function content_mouseover(){if(!animating){parentMenuSet.showMenu(_this);_this.onmouseover();}}
function content_mouseout(){if(!animating){parentMenuSet.hideMenu(_this);}}
function child_mouseover(){if(!animating){parentMenuSet.showMenu(_this);}}
function child_deactivate(){for(var i=0;i<childMenus.length;i++){if(childMenus[i]==this){dehighlight(elmCache["item"][i]);break;}}}
function child_queue(){parentMenuSet.hideMenu(_this);}
function child_dequeue(){parentMenuSet.showMenu(_this);}
function toString(){var aHtml=[];var sClassName="transMenu"+(oActuator.constructor!=TransMenuItem?" top":"");for(var i=0,item=null;item=this.items[i];i++){aHtml[i]=item.toString(childMenus[i]);}
return'<div id="'+id+'" class="'+sClassName+'">'+'<div class="content"><table class="items" cellpadding="0" cellspacing="0" border="0">'+'<tr><td colspan="2"><img src="'+TransMenu.spacerGif+'" width="1" height="'+TransMenu.menuPadding+'"></td></tr>'+aHtml.join('')+'<tr><td colspan="2"><img src="'+TransMenu.spacerGif+'" width="1" height="'+TransMenu.menuPadding+'"></td></tr></table>'+'<div class="shadowBottom"><img src="'+TransMenu.spacerGif+'" width="1" height="1"></div>'+'<div class="shadowRight"><img src="'+TransMenu.spacerGif+'" width="1" height="1"></div>'+'<div class="background"><img src="'+TransMenu.spacerGif+'" width="1" height="1"></div>'+'</div></div>';}}
TransMenuSet.registry=[];function TransMenuSet(iDirection,iLeft,iTop,iReferencePoint){this.addMenu=addMenu;this.showMenu=showMenu;this.hideMenu=hideMenu;this.hide=hide;this.hideCurrent=hideCurrent;var menus=[];var _this=this;var current=null;this.index=TransMenuSet.registry.length;TransMenuSet.registry[this.index]=this;function addMenu(oActuator){var m=new TransMenu(oActuator,iDirection,iLeft,iTop,iReferencePoint,this);menus[menus.length]=m;return m;}
function showMenu(oMenu){if(oMenu!=current){if(current!=null)hide(current);current=oMenu;oMenu.show();}
else{cancelHide(oMenu);}}
function hideMenu(oMenu){if(current==oMenu&&oMenu.isOpen){if(!oMenu.hideTimer)scheduleHide(oMenu);}}
function scheduleHide(oMenu){oMenu.onqueue();oMenu.hideTimer=window.setTimeout("TransMenuSet.registry["+_this.index+"].hide(TransMenu.registry["+oMenu.index+"])",TransMenu.hideDelay);}
function cancelHide(oMenu){if(oMenu.hideTimer){oMenu.ondequeue();window.clearTimeout(oMenu.hideTimer);oMenu.hideTimer=null;}}
function hide(oMenu){if(!oMenu&&current)oMenu=current;if(oMenu&&current==oMenu&&oMenu.isOpen){hideCurrent();}}
function hideCurrent(){if (null != current){cancelHide(current);current.hideTimer=null;current.hide();current=null;}}}
function TransMenuItem(sText,sUrl,oParent){this.toString=toString;this.text=sText;this.url=sUrl;this.parentMenu=oParent;function toString(bDingbat){var sDingbat=bDingbat?TransMenu.dingbatOff:TransMenu.spacerGif;var iEdgePadding=TransMenu.itemPadding+TransMenu.menuPadding;var sPaddingLeft="padding:"+TransMenu.itemPadding+"px; padding-left:"+iEdgePadding+"px;"
var sPaddingRight="padding:"+TransMenu.itemPadding+"px; padding-right:"+iEdgePadding+"px;"
return'<tr class="item"><td nowrap style="'+sPaddingLeft+'">'+sText+'</td><td width="14" style="'+sPaddingRight+'">'+'<img src="'+sDingbat+'" width="14" height="14"></td></tr>';}}
function Accelimation(from,to,time,zip){if(typeof zip=="undefined")zip=0;if(typeof unit=="undefined")unit="px";this.x0=from;this.x1=to;this.dt=time;this.zip=-zip;this.unit=unit;this.timer=null;this.onend=new Function();this.onframe=new Function();}
Accelimation.prototype.start=function(){this.t0=new Date().getTime();this.t1=this.t0+this.dt;var dx=this.x1-this.x0;this.c1=this.x0+((1+this.zip)*dx/3);this.c2=this.x0+((2+this.zip)*dx/3);Accelimation._add(this);}
Accelimation.prototype.stop=function(){Accelimation._remove(this);}
Accelimation.prototype._paint=function(time){if(time<this.t1){var elapsed=time-this.t0;this.onframe(Accelimation._getBezier(elapsed/this.dt,this.x0,this.x1,this.c1,this.c2));}
else this._end();}
Accelimation.prototype._end=function(){Accelimation._remove(this);this.onframe(this.x1);this.onend();}
Accelimation._add=function(o){var index=this.instances.length;this.instances[index]=o;if(this.instances.length==1){this.timerID=window.setInterval("Accelimation._paintAll()",this.targetRes);}}
Accelimation._remove=function(o){for(var i=0;i<this.instances.length;i++){if(o==this.instances[i]){this.instances=this.instances.slice(0,i).concat(this.instances.slice(i+1));break;}}
if(this.instances.length==0){window.clearInterval(this.timerID);this.timerID=null;}}
Accelimation._paintAll=function(){var now=new Date().getTime();for(var i=0;i<this.instances.length;i++){this.instances[i]._paint(now);}}
Accelimation._B1=function(t){return t*t*t}
Accelimation._B2=function(t){return 3*t*t*(1-t)}
Accelimation._B3=function(t){return 3*t*(1-t)*(1-t)}
Accelimation._B4=function(t){return(1-t)*(1-t)*(1-t)}
Accelimation._getBezier=function(percent,startPos,endPos,control1,control2){return endPos*this._B1(percent)+control2*this._B2(percent)+control1*this._B3(percent)+startPos*this._B4(percent);}
Accelimation.instances=[];Accelimation.targetRes=10;Accelimation.timerID=null;
if(window.attachEvent){var cearElementProps=['data','onmouseover','onmouseout','onmousedown','onmouseup','ondblclick','onclick','onselectstart','oncontextmenu'];window.attachEvent("onunload", function() {var el;for(var d=document.all.length;d--;){el=document.all[d];for(var c=cearElementProps.length;c--;){el[cearElementProps[c]] = null;}}});}