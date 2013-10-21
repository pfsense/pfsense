/*!
 * jQuery twitter bootstrap wizard plugin
 * Examples and documentation at: http://github.com/VinceG/twitter-bootstrap-wizard
 * version 1.0
 * Requires jQuery v1.3.2 or later
 * Dual licensed under the MIT and GPL licenses:
 * http://www.opensource.org/licenses/mit-license.php
 * http://www.gnu.org/licenses/gpl.html
 * Authors: Vadim Vincent Gabriel (http://vadimg.com), Jason Gill (www.gilluminate.com)
 */
(function(b){var a=function(e,d){var e=b(e);
var g=this;var c=b.extend({},b.fn.bootstrapWizard.defaults,d);var f=null;var h=null;this.fixNavigationButtons=function(){if(!f.length){h.find("a:first").tab("show");
f=h.find("li:first");}if(g.firstIndex()>=g.currentIndex()){b("li.previous",e).addClass("disabled");}else{b("li.previous",e).removeClass("disabled");}if(g.currentIndex()>=g.navigationLength()){b("li.next",e).addClass("disabled");
}else{b("li.next",e).removeClass("disabled");}if(c.onTabShow&&typeof c.onTabShow==="function"&&c.onTabShow(f,h,g.currentIndex())===false){return false;
}};this.next=function(i){if(e.hasClass("last")){return false;}if(c.onNext&&typeof c.onNext==="function"&&c.onNext(f,h,g.nextIndex())===false){return false;
}$index=g.nextIndex();if($index>g.navigationLength()){}else{h.find("li:eq("+$index+") a").tab("show");}};this.previous=function(i){if(e.hasClass("first")){return false;
}if(c.onPrevious&&typeof c.onPrevious==="function"&&c.onPrevious(f,h,g.previousIndex())===false){return false;}$index=g.previousIndex();if($index<0){}else{h.find("li:eq("+$index+") a").tab("show");
}};this.first=function(i){if(c.onFirst&&typeof c.onFirst==="function"&&c.onFirst(f,h,g.firstIndex())===false){return false;}if(e.hasClass("disabled")){return false;
}h.find("li:eq(0) a").tab("show");};this.last=function(i){if(c.onLast&&typeof c.onLast==="function"&&c.onLast(f,h,g.lastIndex())===false){return false;
}if(e.hasClass("disabled")){return false;}h.find("li:eq("+g.navigationLength()+") a").tab("show");};this.currentIndex=function(){return h.find("li").index(f);
};this.firstIndex=function(){return 0;};this.lastIndex=function(){return g.navigationLength();};this.getIndex=function(i){return h.find("li").index(i);
};this.nextIndex=function(){return h.find("li").index(f)+1;};this.previousIndex=function(){return h.find("li").index(f)-1;};this.navigationLength=function(){return h.find("li").length-1;
};this.activeTab=function(){return f;};this.nextTab=function(){return h.find("li:eq("+(g.currentIndex()+1)+")").length?h.find("li:eq("+(g.currentIndex()+1)+")"):null;
};this.previousTab=function(){if(g.currentIndex()<=0){return null;}return h.find("li:eq("+parseInt(g.currentIndex()-1)+")");};this.show=function(i){return e.find("li:eq("+i+") a").tab("show");
};h=e.find("ul:first",e);f=h.find("li.active",e);if(!h.hasClass(c.tabClass)){h.addClass(c.tabClass);}if(c.onInit&&typeof c.onInit==="function"){c.onInit(f,h,0);
}b(c.nextSelector,e).bind("click",g.next);b(c.previousSelector,e).bind("click",g.previous);b(c.lastSelector,e).bind("click",g.last);b(c.firstSelector,e).bind("click",g.first);
if(c.onShow&&typeof c.onShow==="function"){c.onShow(f,h,g.nextIndex());}g.fixNavigationButtons();b('a[data-toggle="tab"]',e).on("click",function(i){if(c.onTabClick&&typeof c.onTabClick==="function"&&c.onTabClick(f,h,g.currentIndex())===false){return false;
}});b('a[data-toggle="tab"]',e).on("show",function(i){$element=b(i.target).parent();if($element.hasClass("disabled")){return false;}f=$element;g.fixNavigationButtons();
});};b.fn.bootstrapWizard=function(d){if(typeof d=="string"){var c=Array.prototype.slice.call(arguments,1).toString();return this.data("bootstrapWizard")[d](c);
}return this.each(function(e){var f=b(this);if(f.data("bootstrapWizard")){return;}var g=new a(f,d);f.data("bootstrapWizard",g);});};b.fn.bootstrapWizard.defaults={tabClass:"nav nav-pills",nextSelector:".wizard li.next",previousSelector:".wizard li.previous",firstSelector:".wizard li.first",lastSelector:".wizard li.last",onShow:null,onInit:null,onNext:null,onPrevious:null,onLast:null,onFirst:null,onTabClick:null,onTabShow:null};
})(jQuery);