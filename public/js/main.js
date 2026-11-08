Modernizr.addTest('iphone-safari', function () {
   var deviceAgent = navigator.userAgent.toLowerCase(),
   agentID = deviceAgent.match(/(iphone|ipod|ipad)/),
   isSafari = !!navigator.userAgent.match(/Version\/[\d\.]+.*Safari/);
    if (agentID && isSafari ) {
		return true;
	}
});

function debounce(func, wait, immediate) {
// utility to trigger events after set time (used on scroll principally)
			var timeout;
			return function() {
				var context = this, args = arguments;
				clearTimeout(timeout);
				timeout = setTimeout(function() {
					timeout = null;
					if (!immediate) {
						func.apply(context, args);
					}
				}, wait);
				if (immediate && !timeout) {
					func.apply(context, args);
				}
			};
}

$(function() {

	var 
	$body = $('body'),
	videoEle,
	$videoBgSelector = $('#bg-video'),
	videoBgEle = $videoBgSelector.get(0),
	$header = $('.header'),
	$modalVideo = $('.modal-video'),
	$fadeControls = $('.modal-video .fade-control'),
	$launchButton = $('.btn-launch-video'),
	$hasHint = $('.has-hint'),
	$hero = $('.level-hero'),
	controlsTimer = null,
	eventLastX = 0,
	eventLastY = 0,
	desktopView =  Modernizr.mq( "screen and ( min-width: 1200px )" ),
	canPlaceholder = Modernizr.input.placeholder;
	baseUrl = window.location.hostname;
	
	// plays the film; a play the browser cuts short is let go quietly, as
	// the pageshow handler below plays the film again once it can
	function playFilm() {
		var playing = videoBgEle.play();
		if ( playing && playing.catch ) {
			playing.catch( function () {} );
		}
	}

	if ( Modernizr.video && desktopView && videoBgEle ) {
			videoBgEle.load();
			playFilm();
		
		if (typeof videoBgEle.loop == 'boolean') { 
			// loop supported
			videoBgEle.loop = true;
		} 
		else { 
			// loop property not supported
			videoBgEle.on('ended', function () {
				this.currentTime = 0;
				this.play();
			}, 
			false);
		}
		
	}

	else {
		$videoBgSelector.remove();
	}

	// a page the browser brings back from its back/forward cache runs no
	// script again, and the film was paused when the page was left: start
	// it again, unless it was taken out above. Safari also reloads a film
	// whose loading was cut short by leaving, which aborts a play started
	// at once, so the film is played again as soon as it can play
	window.addEventListener('pageshow', function ( event ) {
		if ( event.persisted && videoBgEle && document.body.contains( videoBgEle ) ) {
			videoBgEle.addEventListener('canplay', playFilm, { once: true });
			playFilm();
		}
	});

	// timer fadeout on controls
	var beginFadeTimer = function( duration ) {
		$fadeControls.addClass('on');
		if ( ! duration ) {
			duration = 4000;
		}
		if ( controlsTimer ) {
				clearTimeout( controlsTimer ); 
				controlsTimer = null;
			}
			controlsTimer = setTimeout( fadeControlsOut , duration );
	};
	
	var fadeControlsOut = function() {
		$fadeControls.removeClass('on');
	};
	

	$modalVideo.add($fadeControls).on('mousemove touchmove', function( e ) {
			// click / touch restarts timer
		if( eventLastX !== e.clientX || eventLastY !== e.clientY ) {

				beginFadeTimer( 4000 );
			}   
			eventLastX = e.pageX;
			eventLastY = e.pageY;
	}).on('click', function(e) {
		beginFadeTimer( 4000 );
	});
	
	// hint arrows
	var levelHandler = function()  {
		
		var st = $(window).scrollTop(),
		wh = $(window).height(),
		hh = $hero.outerHeight(true),
		headerh = $header.outerHeight(true);
		
			if ( $hasHint.length ) {
			
				if ( wh > (hh+headerh) ) {
						$hasHint.removeClass('active-state');
				}
				
				else {
		
					if ( st <= 50 ) {
						$hasHint.addClass('active-state');
					}
			
					else {
						$hasHint.removeClass('active-state');
					}
				}
		}
		
	};
	
	// run on doc ready
	levelHandler();
	
	var scrollTrigger = debounce(function() {
		// re-run on scroll :)
		levelHandler();
	}, 1);
	// doesnt play well to debounce this
	
	if ( canPlaceholder ) {
		// switched. ie test
		$('label').addClass('sr-only');
	}

	
	// external links in new window 
	$('a[href^="http:"]').not('[href*="'+baseUrl+'"]').addClass('external').attr({target: "_blank"});
	
	if ( window.location.hash && $('.is-section-archived').length ) {
		var eleID = window.location.hash.split('#');
		$('[aria-controls="' + eleID[1] + '"]').hide();
	}
	
	if (window.history && window.history.pushState) {
	
		$('.show-section').on('click', function(e) {
				e.preventDefault();
				
				var $t = $(this),
				anchor = $t.attr('href'),
				offset = $(anchor).offset();
				
				$t.hide();
				history.pushState({}, "", anchor);
				$(anchor).addClass('is-archive-visible');
				$('body,html').animate({scrollTop: (offset.top)-50 }, 500);
			});
		
	}

	// util - needed ?
	// jQuery 3 runs this ready handler asynchronously, so the window's
	// load event may already be over: mark the page loaded at once then
	if ( document.readyState === 'complete' ) {
		$body.addClass('loaded');
	} else {
		$(window).on('load', function() {
			$body.addClass('loaded');
		});
	}
	
	$(window).on('resize scroll',  scrollTrigger );
	
	// scroll to next level clicking on hint arrow - cheap!
	$('.hint-arrow').on('click', function ( e ) {
		var $parentLevel = $(this).closest('.level');
		
		if ( ! $parentLevel.length ) {
			$parentLevel = $('.level-hero');
		}
		
		// the section right after, whatever its class: the one after
		// the hero is no .level, and an empty set has no offset
		var $nextLevel = $parentLevel.next();
		if ( $nextLevel.length ) {
			$('body,html').animate({scrollTop: $nextLevel.offset().top}, 700, "linear");
		}
	});

	// scroll to next level - this should be merged with previous function
	$('.btn-scroll').on('click', function ( e ) {
		var href = $(this).attr('href');
		
		if ( href.length ) {
			var $target = $(href);
		}
		
		var target = $target.offset(),
		target = target.top;
		$('body,html').animate({scrollTop: target}, 700, "linear");

		e.preventDefault();

	});
	
	// colophon date 
	var d = new Date(),
	dateText = d.getFullYear();
	$('#date-year').text(dateText);
	
	// TABBED CONTENT

	$tabList = $('.inline-tabs');

 	function measureTabs() {

 		$tabList.each(function (e) {

			var $t = $(this),
			$listItems = $t.find('li'),
			totalWidth = 0,
 			$parent = $t.closest('.inline-tabs-wrapper'),
 			ww = window.innerWidth;

			$listItems.each( function() {
				var $li = $(this);
				totalWidth += $li.outerWidth( true );

				// console.log( $li.offset() );
			});

			console.log(totalWidth);

			if ( totalWidth +30 > ww ) {
				$parent.addClass('active-nav');
				$t.width(totalWidth);

			}

			else {
				$parent.removeClass('active-nav');
				$t.removeAttr('style');
			}
			
		});


	}

	var resizeTabs = debounce(function() {

		measureTabs();
	}, 50);

	$(window).on('load resize', function() {
		resizeTabs();
	});

	function prevNextTabs(e, $t ) {
		e.preventDefault();

		var 

		$target = $t.parent(),
		$wrapper = $target.find('.inline-tabs-list-wrapper'),
		$scroller = $wrapper.find('.inline-tabs'),
		$listItems = $target.find('li'),
		$firstInView = $target.find('.first-in-view'),
		distance,
		animSpeed = 400,
		currentScroll = $wrapper.scrollLeft();

		// can click, will click
		if( ! $t.hasClass('disabled') ) {

			if ( $t.hasClass('btn-prev') ) {

				if ( $firstInView.prev().length ) {

					$firstInView.removeClass('first-in-view').prev().addClass('first-in-view');

				}

				distance = $firstInView.outerWidth();

				$wrapper.animate({scrollLeft: ( currentScroll - distance ) }, animSpeed, function () {

						$target.find('.btn-next').removeClass('disabled');

						var scrollLeft = $wrapper.scrollLeft();

						if ( scrollLeft <= 0 ) {
							$t.addClass('disabled');
						}
				});



			}

			else {

				// switch class to next item if we cab

				if ( $firstInView.next().length ) {

					$firstInView.removeClass('first-in-view').next().addClass('first-in-view');

				}

				distance = $firstInView.outerWidth();

				$wrapper.animate({scrollLeft: (distance + currentScroll) }, animSpeed, function () {

						$target.find('.btn-prev').removeClass('disabled');
						var scrollLeft = $wrapper.scrollLeft();


						// alert ( "scroll left:" + scrollLeft + " width of scroller: " + $scroller.width() + " width of wrapper: " + $wrapper.width() );


						if ( ( $wrapper.width() + scrollLeft ) >= $scroller.width()  ) {
							$t.addClass('disabled');
						}
				});

			}	

			

		}
		
	}

	$('.inline-tabs-nav').on('click', function(e) {
		
		prevNextTabs(e,  $(this) );

	});

	var $tabs = $('.inline-tabs a');
	$tabs.on('click', function (e) {
		
		var $this = $(this),
		h = $this.attr('href'),
		$parent = $this.closest('.inline-tabs'),
		$li = $parent.find('li'),
		$target = $(h);

		$li.removeClass('active');
		$this.parent().addClass('active');

		$target.addClass('active').siblings().removeClass('active');

		e.preventDefault();
	});


	
});