

(function($,undefined){


	////////////////////////////////////////
	// THE REVOLUTION PLUGIN STARTS HERE //
	///////////////////////////////////////

	$.fn.extend({

		///////////////////////////
		// MAIN PLUGIN  FUNCTION //
		///////////////////////////
		megafoliopro: function(options) {

				var defaults = {
					filterChangeAnimation:"rotatescale",			// fade, rotate, scale, rotatescale, pagetop, pagebottom,pagemiddle
					filterChangeSpeed:400,							// Speed of Transition
					filterChangeRotate:99,							// If you ue scalerotate or rotate you can set the rotation (99 = random !!)
					filterChangeScale:0.6,							// Scale Animation Endparameter
					delay:20,
					defaultWidth:980,
					paddingHorizontal:10,
					paddingVertical:10,
					layoutarray:[11],
					lowSize:50,
					startFilter:"*"
				};

				options = $.extend({}, defaults, options);


				return this.each(function() {

						// Delegate .transition() calls to .animate()
						// if the browser can't do CSS transitions.
						if (!$.support.transition)
							$.fn.transition = $.fn.animate;

						var opt=options;
						opt.detaiview="off";
						opt.firststart=1;
						if (opt.filter==undefined) opt.filter="*";

						if (opt.delay==undefined) opt.delay=0;

						//opt.savetrans = opt.filterChangeAnimation;
						//opt.filterChangeAnimation="fade";

						// CHECK IF FIREFOX 13 IS ON WAY.. IT HAS A STRANGE BUG, CSS ANIMATE SHOULD NOT BE USED
						var firefox = opt.firefox13 = false;
						var ie = opt.ie = !$.support.opacity;
						var ie9 = opt.ie9 = !$.support.htmlSerialize


						if (ie) $('body').addClass("ie8");
						if (ie9) $('body').addClass("ie9");

						var container=$(this);
						container.data('defaultwidth',opt.defaultWidth);
						container.data('paddingh',opt.paddingHorizontal);
						container.data('paddingv',opt.paddingVertical);
						container.data('order',opt.layoutarray);
						container.data('ie',ie);
						container.data('ie9',ie9);
						container.data('ff',firefox);
						container.data('opt',opt);


						prepairEntries(container,opt);
						reOrder(container,0);
						addFilter(container,"*");
						preparingLazyLoad(container);
						reOrder(container,0);
						setTimeout(function() {
							rePosition(container);
						},400)


						// START WITH AN ALTERNATIVE FILTER BY BUILDING THE CONTAINERS
						if ( opt.startFilter!="*" && opt.startFilter !=undefined) {
							addFilter(container,opt.startFilter);
							reOrder(container,0);
							rePosition(container);
						}


						$(window).resize(function() {
							clearTimeout(container.data('resized'));

							container.data('resized',setTimeout(function() {
								reOrderOrdered(container,0,container.find('>.mega-entry.tp-ordered').length);

							},150));
						});

					/****************************************************
						-	APPLE IPAD AND IPHONE WORKAROUNDS HERE	-
					******************************************************/

					if((navigator.userAgent.match(/iPhone/i)) || (navigator.userAgent.match(/iPod/i)) || (navigator.userAgent.match(/iPad/i))) {
					    $(".mega-entry").click(function(){
					        //we just need to attach a click event listener to provoke iPhone/iPod/iPad's hover event
					        //strange
					    });
					}
				})
			},

		///////////////////////
		// METHODE RESUME    //
		//////////////////////
		megamethode: function(option) {
				return this.each(function() {
					// CATCH THE CONTAINER
					var container=$(this);
				})
		},

		megagetcurrentorder: function() {

					// CATCH THE CONTAINER
					var container=$(this);
					return container.data('lastorder');

		},

		megaappendentry: function(entry) {

					// CATCH THE CONTAINER
					var container=$(this);
					var newentry=$(entry);
					newentry.addClass("mega-entry-added");
					container.append(newentry);
					var opt=container.data('opt');

					prepairNewEntries(container,opt);

					reOrder(container,0);

					rePosition(container,1);

					setTimeout(function() {
						addFilter(container,opt.filter);
						reOrder(container,0);
						rePosition(container,1);
					},200);


		},

		megaremix: function(order) {
				return this.each(function() {
					// CATCH THE CONTAINER
					var container=$(this);
					if (order!=undefined)
						container.data('order',order);
					notOrdered(container);
					reOrder(container,0);
					rePosition(container);

				})
		},

		megafilter: function(filter) {
				return this.each(function() {
					// CATCH THE CONTAINER
					var container=$(this);

					if (container.data('nofilterinaction')!=1) {
						
						container.data('nofilterinaction',1);
						addFilter(container,filter);
						reOrder(container,0);
						rePosition(container);
						setTimeout(function() {
							container.data('nofilterinaction',0);

						},1200)

					} else {
						clearInterval(container.data('nextfiltertimer'));
						container.data('nextfiltertimer',setInterval(function() {
							if (container.data('nofilterinaction')!=1) {
								clearInterval(container.data('nextfiltertimer'));
								addFilter(container,filter);
								reOrder(container,0);
								rePosition(container);
								container.data('nofilterinaction',1);
								setTimeout(function() {
									container.data('nofilterinaction',0);

								},1200)
							}
						},10));
					}
				})
		},

		megaanimchange: function(anim,speed,rotate,scale) {
				return this.each(function() {
					// CATCH THE CONTAINER
					var container=$(this);
					var opt=container.data('opt');
					var filter=opt.filter;
					opt.filterChangeAnimation=anim;
					opt.filterChangeSpeed=speed;
					opt.filterChangeRotate=rotate;
					opt.filterChangeScale=scale;
					addFilter(container,"");
					addFilter(container,filter);
					setTimeout(function() {

						reOrder(container,0);
						rePosition(container);
					},2*opt.filterChangeSpeed);
					container.data('opt',opt);
				})
		}
	})



	////////////////////////////////////////////////////////
	//	WRAP THE DIVS DEPENDING ON THE AMOUNT OF ENTRIES //
	////////////////////////////////////////////////////////
	function prepairEntries(container,opt) {

		container.find('>.mega-entry').each(function() {
				var ent=$(this);
				ent.removeClass('tp-layout-first-item').removeClass('tp-layout-last-item').removeClass('very-last-item');
				ent.addClass("tp-notordered").addClass("mega-entry-added");
				ent.wrapInner('<div class="mega-entry-innerwrap"></div>');
				//ent.find('.mega-socialbar').appendTo(ent)
				var iw = ent.find('.mega-entry-innerwrap')
				/*if (opt.ie) {
					iw.append('<img class="ieimg" src='+ent.data("src")+'>');
				} else {*/
					iw.css({'background':'url('+ent.data("src")+')','backgroundPosition':'50% 49%', 'backgroundSize':'cover', 'background-repeat':'no-repeat'});
//				}

				// LET ACT ON THE CLICK ON ITEM SOMEWAY
				ent.find('.mega-show-more').each(function() {
					var msm = $(this);

					// SAVE THE ID OF THE mega-entry WHICH IS CORRESPONDING ON THE BUTTON
					msm.data('entid',ent.attr('id'));

					// HANDLING OF THE CLICK
					msm.click(function() {
						var msm=$(this);
						var ent=container.find('#'+msm.data('entid'));
						ent.addClass("mega-in-detailview");
						opt.detailview="on";
					});

				});
		});



	}


	////////////////////////////////////////////////////////
	//	WRAP THE DIVS DEPENDING ON THE AMOUNT OF ENTRIES //
	////////////////////////////////////////////////////////
	function prepairNewEntries(container,opt) {

		container.find('>.mega-entry-added').each(function(i) {
				var ent=$(this);
				if (!ent.hasClass('tp-layout')) {

						ent.removeClass('tp-layout-first-item').removeClass('tp-layout-last-item').removeClass('very-last-item');
						ent.addClass("tp-notordered")
						ent.wrapInner('<div class="mega-entry-innerwrap"></div>');
						//ent.find('.mega-socialbar').appendTo(ent)
						var iw = ent.find('.mega-entry-innerwrap')
						/*if (opt.ie) {
							iw.append('<img class="ieimg" src='+ent.data("src")+'>');
						} else {*/
							iw.css({'background':'url('+ent.data("src")+')','backgroundPosition':'50% 49%', 'backgroundSize':'cover', 'background-repeat':'no-repeat'});
						//}

						// LET ACT ON THE CLICK ON ITEM SOMEWAY
						ent.find('.mega-show-more').each(function() {
							var msm = $(this);

							// SAVE THE ID OF THE mega-entry WHICH IS CORRESPONDING ON THE BUTTON
							msm.data('entid',ent.attr('id'));

							// HANDLING OF THE CLICK
							msm.click(function() {
								var msm=$(this);
								var ent=container.find('#'+msm.data('entid'));
								ent.addClass("mega-in-detailview");
								opt.detailview="on";

							});

						});
				}
		});



	}

	///////////////////////////////////////
	//	ADD NOT ORDERED TO THE ENTRIES   //
	///////////////////////////////////////
	function notOrdered(container) {
		container.find('>.mega-entry.tp-layout').each(function() {
			var ent=$(this);
			ent.removeClass('tp-layout').addClass('tp-notordered');
		});
	}



	///////////////////////////////////////
	//	ADD FILTER FOR THE ENTRIES       //
	///////////////////////////////////////
	function addFilter(container,filter) {


		var ie=container.data('ie');
		var ie9=container.data('ie9');
		var opt = container.data('opt');
		if (opt.filterChangeSpeed == undefined) opt.filterChangeSpeed = Math.round(Math.random()*500+100);
		opt.filter=filter;
		var outi=1;
		var ini=1;
		
		

		container.find('>.mega-entry').each(function(i) {
			var ent=$(this);

			var rot = opt.filterChangeRotate;
			if (rot==undefined) rot=30;

			if (rot==99) rot = Math.round(Math.random()*50-25);

			ent.removeClass('tp-layout-first-item').removeClass('tp-layout-last-item').removeClass('very-last-item');

				var subfilters = filter.split(',');
				var hasfilter =false;

				for (var u=0;u<subfilters.length;u++) {

					if (ent.hasClass(subfilters[u])) {
						hasfilter=true;
						console.log("has class");
					}
				}
				  
				if (hasfilter || filter=="*") {


						ent.removeClass('tp-layout').addClass('tp-notordered');
					} else {

						ent.removeClass('tp-ordered').removeClass('tp-layout');
						  setTimeout(function() {
								if (ie || ie9) {
									ent.animate({'scale':0, 'opacity':0},{queue:false,duration:opt.filterChangeSpeed});
								} else {

									if (opt.filterChangeAnimation=="fade") {
										ent.transition({'scale':1, 'opacity':0,'rotate':0},opt.filterChangeSpeed);
										ent.find('.mega-entry-innerwrap').transition({'scale':1, 'opacity':1,perspective: '10000px',rotateX: '0deg'},opt.filterChangeSpeed);
									}
									else
									if (opt.filterChangeAnimation=="scale") {
										ent.transition({'scale':opt.filterChangeScale, 'opacity':0,'rotate':0},opt.filterChangeSpeed);
										ent.find('.mega-entry-innerwrap').transition({'scale':1, 'opacity':1,perspective: '10000px',rotateX: '0deg'},opt.filterChangeSpeed);
									}
									else
									if (opt.filterChangeAnimation=="rotate") {
										ent.transition({'scale':1, 'opacity':0,'rotate':rot},opt.filterChangeSpeed);
										ent.find('.mega-entry-innerwrap').transition({'scale':1, 'opacity':1,perspective: '10000px',rotateX: '0deg'},opt.filterChangeSpeed);
									}
									else
									if (opt.filterChangeAnimation=="rotatescale") {
										ent.transition({'scale':opt.filterChangeScale, 'opacity':0,'rotate':rot},opt.filterChangeSpeed);
										ent.find('.mega-entry-innerwrap').transition({'scale':1, 'opacity':1,perspective: '10000px',rotateX: '0deg'},opt.filterChangeSpeed);
									} else
									if (opt.filterChangeAnimation=="pagetop" || opt.filterChangeAnimation=="pagebottom" || opt.filterChangeAnimation=="pagemiddle") {

										ent.find('.mega-entry-innerwrap').removeClass("pagemiddle").removeClass("pagetop").removeClass("pagebottom").addClass(opt.filterChangeAnimation);
										ent.transition({'opacity':0},opt.filterChangeSpeed);
										ent.find('.mega-entry-innerwrap').transition({'scale':1, 'opacity':0,perspective: '10000px',rotateX: '90deg'},opt.filterChangeSpeed);
									}

								}
							setTimeout(function() {	ent.css({visibility:'hidden'})},opt.filterChangeSpeed);

						},ini*opt.delay/2);
						ini++;

					}



		});



	}


	///////////////////////////////////////////
	// PREPARE FOR THE FIRST START THE ITEMS //
	//////////////////////////////////////////
	function preparingLazyLoad(container) {

		var ie=container.data('ie');
		var ie9=container.data('ie9');
		var opt = container.data('opt');
		if (opt.filterChangeSpeed == undefined) opt.filterChangeSpeed = Math.round(Math.random()*500+100);
		if (opt.filterChangeScale == undefined) opt.filterChangeScale = 0.8;


		var outi=0;
		var ini=0;

		container.find('>.mega-entry').each(function(i) {
			var ent=$(this);

			var rot = opt.filterChangeRotate;
			if (rot==undefined) rot=30;
			if (rot==99) rot = Math.round(Math.random()*360);

				if (ie || ie9) {
					ent.css({'opacity':0});
				} else {
					if (opt.filterChangeAnimation=="fade")
						ent.transition({'scale':1, 'opacity':0,'rotate':0,duration:1,queue:false});
					else
					if (opt.filterChangeAnimation=="scale") {
						ent.transition({'scale':opt.filterChangeScale, 'opacity':0,'rotate':0,duration:1,queue:false});
					}
					else
					if (opt.filterChangeAnimation=="rotate")
						ent.transition({'scale':1, 'opacity':0,'rotate':rot,duration:1,queue:false});
					else
					if (opt.filterChangeAnimation=="rotatescale") {

						ent.transition({'scale':opt.filterChangeScale, 'opacity':0,'rotate':rot,duration:1,queue:false});
					}
					else
					if (opt.filterChangeAnimation=="pagetop" || opt.filterChangeAnimation=="pagebottom" || opt.filterChangeAnimation=="pagemiddle") {
							ent.find('.mega-entry-innerwrap').addClass(opt.filterChangeAnimation);
							ent.transition({'opacity':0,duration:1,queue:false});
							ent.find('.mega-entry-innerwrap').transition({'scale':1, 'opacity':1,perspective: '10000px',rotateX: '90deg',duration:1,queue:false});
					}

				}
		});
		//opt.filterChangeAnimation = opt.savetrans;



	}


	////////////////////////////////////////////////////////
	//	REORDER THE CONTAINER DEPENDING ON THE SETTINGS   //
	////////////////////////////////////////////////////////
	function reOrder(container,deep) {


		// TO SAVE THE LAST ORDER FOR THE GALLERY, TO BE ABLE TO REPRODUCE IT
		if (deep==0) {
			var lastorder=new Array();
		} else
			var lastorder=container.data('lastorder');


		var cw = container.width();

		// THIS IS THE CURRENT REQUESTED ORDER
		var order=container.data('order');

		// IF ORDER ARRIVED TO THE END, SHOULD START FROM THE BEGINNING
		if (deep>order.length-1) deep=0;


		// SAVE THE ENTRIES IN AN ARRAY
		var entries=container.find('>.mega-entry.tp-notordered');

		// LET SEE HOW MANY LAYOUT ART WE HAVE 2-9 ARE THE PREMIUM GRIDS
		var max_layout_art=12;
		if (entries.length<9) max_layout_art=entries.length;

		//SAVE THE NEXT LAYOUT HERE
		var next_layout  = order[deep];
		


		var firefox =  false;
		var ie = !$.support.opacity;
		var ie9 = !$.support.htmlSerialize

		if (order[deep]==0 || next_layout<2 || next_layout>23)
			if (ie) {
				next_layout=9;
			} else {
				next_layout=Math.round(Math.random()*max_layout_art+1);
			}


		if (next_layout<2) next_layout=2;
		if (next_layout>23) next_layout=23;

		// PUSH THE NEXT LAYOUT INTO THE SAVED ORDER (IN CASE IT NEEDED SOMEWHERE)
		lastorder.push(next_layout);

		var element_amount=next_layout;
		if (next_layout==10 || next_layout==14) element_amount=3;
		if (next_layout==11 || next_layout==15) element_amount=4;
		if (next_layout==12 || next_layout==16) element_amount=5;
		if (next_layout==13 || next_layout==17) element_amount=6;


		if (next_layout==11 || next_layout==12 || next_layout==13 || next_layout==15 || next_layout==16 || next_layout==17)
			if (cw<840  && cw>721) element_amount=4
			  else
			if (cw<720) element_amount=3;

		if (next_layout==18 || next_layout==19 || next_layout==20) element_amount = 1;
		if (next_layout==21 || next_layout==22 || next_layout==23) element_amount = 2;

		// SET THE NEXT ITEM AS THE NEXT LAYOUT INDICATES
		entries.slice(0,element_amount).each(function(i) {

			var ent=$(this);


			ent.removeClass('tp-layout-first-item').removeClass('tp-layout-last-item').removeClass('very-last-item');

			// tp-layout SHOWS THAT THE ITEM HAS ALREADY A LAYOUT
			ent.addClass('tp-ordered tp-layout');
			// SAVE THE LAYOUT TYPE IN EACH mega-entry
			ent.data('layout',next_layout)
			//SAVE THE CHILD INDEX IN THE mega-entry
			ent.data('child',i)
			// MARK FIRST AND LAST ITEMS HERE
			if (i==0) ent.addClass("tp-layout-first-item");

			if (i==element_amount-1) {

					ent.addClass("tp-layout-last-item");
			}

			//ITEM IS ORDERED, SO NOT ORDERED CLASS CAN BE REMOVED
			ent.removeClass('tp-notordered');

		});

		// WE GO ONE FURTHER; DEEPER IN THE REKURSIVE FUNCTION
		deep=deep+1;
		//SAVE THE LAST ORDER !!
		container.data('lastorder',lastorder);

		//IF WE HAVE MORE ITEM TO ORDER, WE CAN CALL THE REKURSIVE FUNCTION AGAIN
		if (container.find('>.mega-entry.tp-notordered').length>0)
			reOrder(container,deep);
		else
			{
				try{
				  findLastOrdered(container).addClass('very-last-item');
				  } catch(e) {}
				return container;
			}
	}


	////////////////////////////////////////////////////////
	//	REORDER THE CONTAINER DEPENDING ON THE SETTINGS   //
	////////////////////////////////////////////////////////
	function reOrderOrdered(container,deep,itemtogo) {



		// TO SAVE THE LAST ORDER FOR THE GALLERY, TO BE ABLE TO REPRODUCE IT
		if (deep==0) {
			var lastorder=new Array();
		} else
			var lastorder=container.data('lastorder');


		var cw = container.width();

		// THIS IS THE CURRENT REQUESTED ORDER
		var order=container.data('order');

		// IF ORDER ARRIVED TO THE END, SHOULD START FROM THE BEGINNING
		if (deep>order.length-1) deep=0;


		// SAVE THE ENTRIES IN AN ARRAY
		var entries=container.find('>.mega-entry.tp-ordered');

		// LET SEE HOW MANY LAYOUT ART WE HAVE 2-9 ARE THE PREMIUM GRIDS
		var max_layout_art=12;
		if (entries.length<9) max_layout_art=entries.length;

		//SAVE THE NEXT LAYOUT HERE
		var next_layout  = order[deep];

		var firefox =  false;
		var ie = !$.support.opacity;
		var ie9 = !$.support.htmlSerialize

		if (order[deep]==0 || next_layout<2 || next_layout>23)
			if (ie) {
				next_layout=9;
			} else {
				next_layout=Math.round(Math.random()*max_layout_art+1);
			}

		if (next_layout<2) next_layout=2;
		if (next_layout>23) next_layout=23;

		// PUSH THE NEXT LAYOUT INTO THE SAVED ORDER (IN CASE IT NEEDED SOMEWHERE)
		lastorder.push(next_layout);

		var element_amount=next_layout;
		if (next_layout==10 || next_layout==14) element_amount=3;
		if (next_layout==11 || next_layout==15) element_amount=4;
		if (next_layout==12 || next_layout==16) element_amount=5;
		if (next_layout==13 || next_layout==17) element_amount=6;


		if (next_layout==11 || next_layout==12 || next_layout==13 || next_layout==15 || next_layout==16 || next_layout==17)
				if (cw<840  && cw>721) element_amount=4
				  else
				if (cw<720) element_amount=3;

		if (next_layout==18 || next_layout==19 || next_layout==20) element_amount = 1;
		if (next_layout==21 || next_layout==22 || next_layout==23) element_amount = 2;		
		
		var firstent = entries.length-itemtogo;

		// SET THE NEXT ITEM AS THE NEXT LAYOUT INDICATES
		entries.slice(firstent,firstent+element_amount).each(function(i) {

			var ent=$(this);

			ent.removeClass('tp-layout-first-item').removeClass('tp-layout-last-item').removeClass('very-last-item');

			// tp-layout SHOWS THAT THE ITEM HAS ALREADY A LAYOUT
			ent.addClass('tp-ordered tp-layout');
			// SAVE THE LAYOUT TYPE IN EACH mega-entry
			ent.data('layout',next_layout)
			//SAVE THE CHILD INDEX IN THE mega-entry
			ent.data('child',i)
			// MARK FIRST AND LAST ITEMS HERE
			if (i==0) ent.addClass("tp-layout-first-item");

			if (i==element_amount-1) {

					ent.addClass("tp-layout-last-item");
			}

			//ITEM IS ORDERED, SO NOT ORDERED CLASS CAN BE REMOVED
			ent.removeClass('tp-notordered');

		});

		// WE GO ONE FURTHER; DEEPER IN THE REKURSIVE FUNCTION
		deep=deep+1;
		//SAVE THE LAST ORDER !!
		container.data('lastorder',lastorder);
		itemtogo=itemtogo-element_amount;

		//IF WE HAVE MORE ITEM TO ORDER, WE CAN CALL THE REKURSIVE FUNCTION AGAIN
		if (itemtogo>0)
			reOrderOrdered(container,deep,itemtogo);
		else
			{
				findLastOrdered(container).addClass('very-last-item');
				rePosition(container);
				return container;
			}
	}



	/////////////////////////////
	// FIND LAST ORDERED ITEM //
	///////////////////////////
	function findLastOrdered(container) {
	   var lastitem;

	   container.find('>.mega-entry.tp-layout.tp-ordered').each(function() {
			lastitem=$(this);
		});


	   return lastitem;
	}


	/////////////////////////////
	// ROUND ME TO RIGHT VALUE //
	////////////////////////////
	function roundme(val) {
		return val;
	}

	//////////////////////////
	//	PUT IT IN POSITION	//
	//////////////////////////
	function rePosition(container,maxdelay) {

		var topp=0;
		var startwidth = container.data('defaultwidth');
		var opt=container.data('opt');
		var optdelay=opt.delay;

		var firststart=0;
		if (opt.firststart==1) {
			firststart=1;
			opt.firststart=0;
		}



		var cw = container.width();
		var prop = cw / startwidth;
		var basicheight = 185;
		var paddingh = container.data('paddingh');
		var paddingv = container.data('paddingv');


		var outi=1;
		var ini=1;

		var ie=container.data('ie');
		var ie9=container.data('ie9');

		// CALCULATE THE BASI PROPORTIONS
		var w5 = 5*prop;

		// THE MAX HEIGHT OF THE ITEM
		var maxhh=0;

		// THE NEW BASIC VALUES
		//w5 = Math.floor(w5)+dec;

		// Calculate the Basic Grid Sizes
		var w980 = cw;
		var w790 = roundme(w5 * 160);
		var w780 = roundme(w5 * 158);
		var w760 = roundme(w5 * 152);
		var w740 = roundme(w5 * 148);
		var w660 = roundme(w5 * 132);
		var w615 = roundme(w5 * 123);
		var w610 = roundme(w5 * 122);
		var w595 = roundme(w5 * 119);
		var w565 = roundme(w5 * 113);
		var w560 = roundme(w5 * 112);
		var w490 = roundme(w5 * 98);
		var w420 = roundme(w5 * 84);
		var w415 = roundme(w5 * 83);
		var w395 = roundme(w5 * 79);
		var w390 = roundme(w5 * 78);
		var w385 = roundme(w5 * 77);
		var w370 = roundme(w5 * 74);
		var w365 = roundme(w5 * 73);
		var w345 = roundme(w5 * 69);
		var w340 = roundme(w5 * 68);
		var w335 = roundme(w5 * 67);
		var w326 = roundme(w5 * 65.3);
		var w245 = roundme(w5 * 49);
		var w240 = roundme(w5 * 48);
		var w225 = roundme(w5 * 45);
		var w220 = roundme(w5 * 44);
		var w196 = roundme(w5 * 39.2);
		var w195 = roundme(w5 * 39);
		var w190 = roundme(w5 * 38);
		var w185 = roundme(w5 * 37);
		var w180 = roundme(w5 * 36);
		var w163 = roundme(w5 * 32.66);

		// The Basic Heights
		var h1110 = roundme(w5 * 222);
		var h740 = w740;
		var h555 = roundme(w5*111);
		var h370 = w370;
		var h365 = w365;
		var h245 = w245;
		var h240 = w240;
		var h185 = w185;
		var h180 = w180;

		var heights = new Array(0,0,0,0,0,0,0,0,0);
		var lastheights = new Array(0,0,0,0,0,0,0,0,0);

		var currentcolumn=0;
		var layout=0;

		var allelements=container.find('>.mega-entry.tp-layout').length;

		// LET CREATE THE GIRDS
		container.find('>.mega-entry.tp-layout').each(function(i) {
			var ent=$(this);
			var iw = ent.find('.mega-entry-innerwrap');
			layout=ent.data('layout');


			if (layout==11 || layout==12 || layout==13 )
				if (cw<840 && cw>721) layout=11
				 else
				if (cw<720) layout=10;

			if (layout==15 || layout==16 || layout==17)
				if (cw<840 && cw>721) layout=15
				 else
				if (cw<720) layout=14;


			// SET THE SPEED
			var dur=500;

			// SET THE BASIC POSITIONS AND SIZES
			var w,h,xp;
			var ph=paddingh;
			var pv=paddingv;
			var yp=topp;
			var lasth=h185;

			if (cw>480) {
						// THE GRID TYPE 3 POSITIONS SHOULD BE CALCULATED HERE
						if (layout==2) {
								if (cw>767) {
										if (ent.data('child')==0) { w=w565; h=h370; xp=0; 	 lasth=h370}
										if (ent.data('child')==1) {	w=w415; h=h370; xp=w565;  ph=0; topp=topp+h370; lasth=h370}

								} else {
									if (cw>480 && cw<768) {
										if (ent.data('child')==0) { w=w420; h=h370; xp=0; 	 lasth=h370}
										if (ent.data('child')==1) {	w=w560; h=h370; xp=w420;  ph=0; topp=topp+h370; lasth=h370}
									}
								}
						}


						// THE GRID TYPE 3 POSITIONS SHOULD BE CALCULATED HERE
						if (layout==3) {
								if (cw>767) {
										if (ent.data('child')==0) { w=w390; h=h370; xp=0; 	 lasth=h370}
										if (ent.data('child')==1) { w=w225; h=h370; xp=w390; lasth=h370}
										if (ent.data('child')==2) {	w=w365; h=h370; xp=w615;  topp=topp+h370; lasth=h370;ph=0;}

								} else {
									if (cw>480 && cw<768) {
										if (ent.data('child')==0) {	w=w370; h=h370; xp=0; 		lasth=h370}
										if (ent.data('child')==1) { w=w390; h=h370; xp=w370;  	lasth=h370}
										if (ent.data('child')==2) { w=w220; h=h370; xp=w760; 	lasth=h370; topp=topp+h370; ph=0;}


									}
								}
						}

						// THE GRID TYPE 4 POSITIONS SHOULD BE CALCULATED HERE
						if (layout==4) {
								if (cw>767) {
										if (ent.data('child')==0) { w=w420; h=h370; xp=0; 	 lasth=370;}
										if (ent.data('child')==1) { w=w195; h=h185; xp=w420; }
										if (ent.data('child')==2) { w=w365; h=h370; xp=w615; ph=0;  topp=topp+h185; lasth=h370}
										if (ent.data('child')==3) {	w=w195; h=h185; xp=w420;  topp=topp+h185;}

								} else {
									if (cw>480 && cw<768) {

										if (ent.data('child')==0) { w=w195; h=h185; xp=w420; }
										if (ent.data('child')==1) { w=w420; h=h370; xp=0; 	 topp=topp+h185; }
										if (ent.data('child')==2) {	w=w195; h=h185; xp=w420; topp=topp-h185;}
										if (ent.data('child')==3) { w=w365; h=h370; xp=w615; ph=0; topp=topp+h370;  lasth=h370}

									}
								}
						}

						// THE GRID TYPE 5 POSITIONS SHOULD BE CALCULATED HERE
						if (layout==5) {
								if (cw>767) {
										if (ent.data('child')==0) { w=w420; h=h185; xp=0; 	 }
										if (ent.data('child')==1) { w=w195; h=h185; xp=w420; }
										if (ent.data('child')==2) { w=w365; h=h370; xp=w615; ph=0;  topp=topp+h185; lasth=h370}
										if (ent.data('child')==3) { w=w220; h=h185; xp=0; 	 }
										if (ent.data('child')==4) {	w=w395; h=h185; xp=w220;  topp=topp+h185;}

								} else {
									if (cw>480 && cw<768) {
										if (ent.data('child')==0) { w=w420; h=h185; xp=0; 	 }
										if (ent.data('child')==1) { w=w195; h=h185; xp=w420; }
										if (ent.data('child')==2) { w=w365; h=h185; xp=w615; 	ph=0;  topp=topp+h185;}
										if (ent.data('child')==3) { w=w490; h=h185; xp=0; 	 }
										if (ent.data('child')==4) {	w=w490; h=h185; xp=w490;  	ph=0; topp=topp+h185;}

									}
								}
						}


						// THE GRID TYPE 5 POSITIONS SHOULD BE CALCULATED HERE
						if (layout==6) {


								if (cw>767) {
										if (ent.data('child')==0) { w=w370; h=h370; xp=0; 	 lasth=h370;}
										if (ent.data('child')==1) { w=w225; h=h185; xp=w370; }
										if (ent.data('child')==2) { w=w385; h=h185; xp=w595; 	ph=0; topp=topp+h185;}
										if (ent.data('child')==3) { w=w225; h=h185; xp=w370; }
										if (ent.data('child')==4) { w=w195; h=h185; xp=w595; }
										if (ent.data('child')==5) {	w=w190; h=h185; xp=w780;  	ph=0; topp=topp+h185;}

								} else {
									if (cw>480 && cw<768) {
										if (ent.data('child')==0) { w=w225; h=h370; xp=0; 	 lasth=h370;}
										if (ent.data('child')==1) { w=w340; h=h370; xp=w225; lasth=h370;}
										if (ent.data('child')==2) { w=w195; h=h185; xp=w565; }
										if (ent.data('child')==3) { w=w220; h=h185; xp=w760; 	ph=0;  topp=topp+h185;}
										if (ent.data('child')==4) { w=w195; h=h185; xp=w565; }
										if (ent.data('child')==5) {	w=w220; h=h185; xp=w760; 	ph=0;  topp=topp+h185;}

									}
								}
						}

						// THE GRID TYPE 7 POSITIONS SHOULD BE CALCULATED HERE
						if (layout==7) {




							if (cw>767) {
										if (ent.data('child')==0) { w=w565; h=h370; xp=0; 	 	lasth=h370;}
										if (ent.data('child')==1) { w=w415; h=h185; xp=w565; 	ph=0;  topp=topp+h185;}
										if (ent.data('child')==2) { w=w195; h=h185; xp=w565; }
										if (ent.data('child')==3) { w=w220; h=h370; xp=w760; 	ph=0;  topp=topp+h185;lasth=h370}
										if (ent.data('child')==4) { w=w225; h=h185; xp=0; 	 }
										if (ent.data('child')==5) { w=w340; h=h185; xp=w225; }
										if (ent.data('child')==6) { w=w195; h=h185; xp=w565;   topp=topp+h185;}

								} else {
									if (cw>480 && cw<768) {
										if (ent.data('child')==0) { w=w565; h=h370; xp=0; 	 lasth=h370;}
										if (ent.data('child')==1) { w=w415; h=h185; xp=w565; 	ph=0; topp=topp+h185;}
										if (ent.data('child')==2) { w=w195; h=h185; xp=w565; }
										if (ent.data('child')==3) { w=w220; h=h185; xp=w760; 	ph=0; topp=topp+h185;}
										if (ent.data('child')==4) { w=w225; h=h185; xp=0; 	 }
										if (ent.data('child')==5) { w=w340; h=h185; xp=w225; }
										if (ent.data('child')==6) { w=w415; h=h185; xp=w565; 	ph=0;  topp=topp+h185;}

									}
								}
						}

						// THE GRID TYPE 8 POSITIONS SHOULD BE CALCULATED HERE
						if (layout==8) {



							if (cw>767) {

										if (ent.data('child')==0) { w=w415; h=h185; xp=0; 	 }
										if (ent.data('child')==1) { w=w345; h=h370; xp=w415; lasth=h370;}
										if (ent.data('child')==2) { w=w220; h=h185; xp=w760; 	ph=0;  topp=topp+h185;}
										if (ent.data('child')==3) { w=w195; h=h185; xp=0; 	 	}
										if (ent.data('child')==4) { w=w220; h=h185; xp=w195; 	}
										if (ent.data('child')==5) { w=w220; h=h370; xp=w760; 	ph=0;  topp=topp+h185; lasth=h185;}
										if (ent.data('child')==6) { w=w415; h=h185; xp=0; 	 	}
										if (ent.data('child')==7) { w=w345; h=h185; xp=w415; 	 topp=topp+h185; }


								} else {
									if (cw>480 && cw<768) {
										if (ent.data('child')==0) { w=w415; h=h185; xp=0; 	 	}
										if (ent.data('child')==1) { w=w195; h=h185; xp=w415; 	}
										if (ent.data('child')==2) { w=w370; h=h185; xp=w610; 	ph=0; topp=topp+h185; lasth=h370;}
										if (ent.data('child')==3) { w=w195; h=h185; xp=0; 	 	}
										if (ent.data('child')==4) { w=w415; h=h185; xp=w195; 	}
										if (ent.data('child')==5) { w=w370; h=h370; xp=w610; 	ph=0;  topp=topp+h185; lasth=h370;}
										if (ent.data('child')==6) { w=w415; h=h185; xp=0; 	 	}
										if (ent.data('child')==7) { w=w195; h=h185; xp=w415; 	 topp=topp+h185; }


									}
								}
						}


						// THE GRID TYPE 9 POSITIONS SHOULD BE CALCULATED HERE
						if (layout==9) {



								if (cw>767) {
										if (ent.data('child')==0) { w=w565; h=h370; xp=0; 	 	lasth=h370;}
										if (ent.data('child')==1) { w=w415; h=h185; xp=w565; 	ph=0;  topp=topp+h185; }
										if (ent.data('child')==2) { w=w195; h=h185; xp=w565; 	}
										if (ent.data('child')==3) { w=w220; h=h370; xp=w760; 	ph=0;  topp=topp+h185; lasth=h370;}
										if (ent.data('child')==4) { w=w225; h=h370; xp=0; 	 	lasth=h370;}
										if (ent.data('child')==5) { w=w340; h=h370; xp=w225; 	lasth=h370;}
										if (ent.data('child')==6) { w=w195; h=h185; xp=w565; 	 topp=topp+h185; }
										if (ent.data('child')==7) { w=w195; h=h185; xp=w565; 	}
										if (ent.data('child')==8) {	w=w220; h=h185; xp=w760; 	ph=0;  topp=topp+h185; }


								} else {
									if (cw>480 && cw<768) {
										if (ent.data('child')==0) { w=w370; h=h370; xp=0; 	 	lasth=h370;}
										if (ent.data('child')==1) { w=w240; h=h370; xp=w370; 	lasth=h370;}
										if (ent.data('child')==2) { w=w370; h=h370; xp=w610; 	ph=0;  topp=topp+h370; lasth=h370;}
										if (ent.data('child')==3) { w=w240; h=h370; xp=0;    	lasth=h370;}
										if (ent.data('child')==4) { w=w370; h=h370; xp=w240; 	lasth=h370;}
										if (ent.data('child')==5) { w=w370; h=h370; xp=w610; 	ph=0;  topp=topp+h370; lasth=h370;}
										if (ent.data('child')==6) { w=w370; h=h370; xp=0;   	lasth=h370;}
										if (ent.data('child')==7) { w=w370; h=h370; xp=w370; 	lasth=h370;}
										if (ent.data('child')==8) {	w=w240; h=h370; xp=w740;  	ph=0;  topp=topp+h370; lasth=h370;}


									}
								}
						}

						/*********************************
							- BASIC GRID OPTIONS -
						**********************************/
						if (layout>9 && layout<14) {
							 if (layout==10) {
								w=Math.round(w326);

								w=w + (ph/3);
							 } else
							 if (layout==11) {
								w=Math.round(w245);

								w=w + (ph/4);
							  } else
							 if (layout==12) {
								w=Math.round(w196);
								w=w  + (ph/5);
							 } else
							 if (layout==13) {
								w=Math.round(w163);
								w=w + (ph/6);
							}

							var h=w;
							var chil=ent.data('child');
							xp=w*chil;
							lasth=h;

							if ( (chil==2 && layout==10) ||
								 (chil==3 && layout==11) ||
								 (chil==4 && layout==12) ||
								 (chil==5 && layout==13) ||
								 (ent.hasClass('tp-layout-last-item'))
								) {		// ph=0;
										topp=topp+h; }
						}
						
						
						/*********************************
							- 1 GRID OPTIONS -
						**********************************/
						
						// 1 COLUMN GRIDS
						if (layout==18) {
								w=Math.round(w980);
								w=w + (ph);

							var h=w;
							var chil=ent.data('child');
							xp=w*chil;
							lasth=h;

							topp=topp+h;
						}
						
						// 1 COLUMN GRIDS
						if (layout==19) {
								w=Math.round(w980);
								w=w + (ph);

							var h=w/ 2;
							var chil=ent.data('child');
							xp=w*chil;
							lasth=h;

							topp=topp+h;
						}
						
						// 1 COLUMN GRIDS
						if (layout==20) {
								w=Math.round(w980);
								w=w + (ph);

							var h=w/ 3;
							var chil=ent.data('child');
							xp=w*chil;
							lasth=h;

							topp=topp+h;
						}
						
						
						/*********************************
							- 2 GRID OPTIONS -
						**********************************/
						
						// 1 COLUMN GRIDS
						if (layout==21) {
							w=Math.round(w490);
							w=w + (ph/2);
							var h=w;
							var chil=ent.data('child');
							xp=w*chil;
							lasth=h;	

							if ( chil==1) topp=topp+h; 
						}
						
						// 1 COLUMN GRIDS
						if (layout==22) {
							w=Math.round(w490);
							w=w + (ph/2);
							var h=w/2;
							var chil=ent.data('child');
							xp=w*chil;
							lasth=h;	
												
							if ( chil==1) topp=topp+h; 
						}
						
						// 1 COLUMN GRIDS
						if (layout==23) {
							w=Math.round(w490);
							w=w + (ph/2);
							var h=w/3;
							var chil=ent.data('child');
							xp=w*chil;
							lasth=h;	
												
							if ( chil==1) topp=topp+h; 
						}
						
						/*********************************
							- DIFFERENT HEIGHT OPTIONS -
						**********************************/
						if (layout>13 && layout<18) {
							 if (layout==14) {
								w=Math.round(w326);

								w=w + (ph/3);
							 } else
							 if (layout==15) {
								w=Math.round(w245);

								w=w + (ph/4);
							  } else
							 if (layout==16) {
								w=Math.round(w196);
								w=w  + (ph/5);
							 } else
							 if (layout==17) {
								w=Math.round(w163);
								w=w + (ph/6);
							}

							var chil = ent.data('child');
							var prop =w/ent.data('width');

							h=ent.data('height') * prop;
							xp=w*ent.data('child');
							yp=heights[chil];
							lasth=h*prop;
							lastheights[chil]=lasth;
							topp=yp+h;
							heights[chil] = topp;
						}


			} else { // IF THE CONTAINER IS TOO SMALL THAN LET

								h=Math.round(ent.data('height')*(cw/ent.data('width')));
								w=cw;
								ph=0;
								xp=0;
								yp=topp;
								topp=topp+h;
			}



			var scal=1;
			var opaa=1;
			var rx = 0;
			var rot = 0;

			var orot = opt.filterChangeRotate;
			if (orot==undefined) rot=30;

			if (orot==99) orot = Math.round(Math.random()*360);



			// FILTER DEPENDEND SETTINGS
			var subfilters = opt.filter.split(',');
			var hasfilter =false;

			for (var u=0;u<subfilters.length;u++) {

				if (ent.hasClass(subfilters[u])) {
					hasfilter=true;
					console.log("has class");
				}
			}
			  

			if (hasfilter || opt.filter=="*") {

								ent.css({visibility:'visible'});
								if (ie || ie9) {
									scal=1;
									opaa=1;


								} else {
									if (opt.filterChangeAnimation=="pagetop" || opt.filterChangeAnimation=="pagebottom" || opt.filterChangeAnimation=="pagemiddle") {
										rot=0;
										rx=0;
										scal=1;
										opaa=1;
										eiscal=1;
										eiopaa=1;
										eirx=0;

										//ent.transition({rotate:0,'opacity':1},opt.filterChangeSpeed);
									} else {
										scal=1;
										rot=0;
										opaa=1;
										eiscal=1;
										eiopaa=1;
										eirx=0;

									}
								}

					}


			if (opt.detailview=="on" && opaa==1) opaa=0.4;
			if (ent.hasClass('mega-in-detailview')) opaa=1;

			ent.removeClass("mega-square").removeClass("mega-portrait").removeClass("mega-landscape");
			var wround=Math.floor(w/100);
			var hround=Math.floor(h/100);
			if (wround>hround) ent.addClass("mega-landscape");
			if (hround>wround) ent.addClass("mega-portrait");
			if (wround==hround) ent.addClass("mega-square");


			var timer_delay=i*opt.delay;
			/*if (maxdelay!=undefined) {
				if (allelements-i<6)
					timer_delay=(allelements-i)*opt.delay;
			}*/

			// PUT THE mega-entry IN THE RIGHT POSITION
			if (ie || ie9) {
				ent.find('.mega-socialbar').animate({'width':w+'px'});
				ent.animate({ 'scale':scal, 'opacity':opaa, width:w+"px", height:h+"px", left:xp+"px", top:yp+"px", 'paddingBottom':pv+"px", 'paddingRight':ph+"px"},{queue:false,duration:400});
				iw.animate({'background-position':'50% 49%', 'background-size':'cover'},{queue:false,duration:400});
				if (ie) {
					var img=iw.find('.ieimg');
					var imgratio=Math.round(ent.data('width')) / Math.round(ent.data('height'));
					var conratio=Math.round(w)/Math.round(h);

					var nw=w;
					var nh=nw/ent.data('width')*ent.data('height');


					if (nh<h) {

						nh=h;
						nw=nh/ent.data('height')*ent.data('width');
					}

					img.css({'width':nw+'px','height':nh+'px'});



				}

			} else {


				var prop = (cw / startwidth)*100 -16;


				if (ent.data('lowsize')!=undefined )
				if (prop<=ent.data('lowsize'))
					ent.addClass("mega-lowsize")
				else
					ent.removeClass("mega-lowsize");

				if (firststart) {
						  timer_delay=timer_delay+100;
						  ent.transition({  'opacity':0, "top":yp+"px","left":xp+"px", width:w, height:h, 'paddingBottom':pv+"px", 'paddingRight':ph+"px",duration:1,queue:false});
					}
				setTimeout(function() {
					ent.transition({ 'scale':scal, 'opacity':opaa,'rotate':rot, 'z-index':1,width:w, height:h, "top":yp+"px","left":xp+"px", 'paddingBottom':pv+"px", 'paddingRight':ph+"px",duration:opt.filterChangeSpeed,queue:false});
					setTimeout(function() {
						ent.find('.mega-entry-innerwrap').transition({'scale':eiscal, 'opacity':eiopaa,perspective: '10000px',rotateX: eirx,duration:opt.filterChangeSpeed,queue:false});
						ent.removeClass('mega-entry-added');
					},50);
					iw.transition({'background-position':'50% 49%', 'background-size':'cover',duration:opt.filterChangeSpeed,queue:false});
				},timer_delay);

			}


			if (ent.hasClass('very-last-item') && !ent.hasClass('tp-layout-last-item')) {

				topp=topp+lasth;

			} else {
					//$('.debug').html($('.debug').html()+"<br>"+topp);
			}

			if (maxhh<yp+h) maxhh=yp+h;

		})

		// IF THE LAST LAYOUT HAD A DIFFERENT HEIGHT ATTRIBUTE, WE NEED TO CALCULATE THE HEIGHETS ROW
		if (layout>13 && layout<18) {

					topp=heights[0];
					for (allh=0;allh<heights.length;allh++) {

						if (topp<heights[allh]) topp=heights[allh];
					 }
				}


		container.css({height:maxhh+"px"})


	}


})(jQuery);




