/**
 * Carousel behaviour for the [cb_nearby] shortcode.
 *
 * Progressive enhancement: the markup is a horizontally scrollable list that
 * works on its own. This script wires up the prev/next buttons, sets the number
 * of visible cards from the data attribute, and auto-advances (pausing on hover
 * or focus). No external dependencies.
 */
(function () {
	'use strict';

	var AUTOPLAY_INTERVAL = 5000;

	function initCarousel(root) {
		var viewport = root.querySelector('.cb-nearby-viewport');
		var track = root.querySelector('.cb-nearby-track');
		var prev = root.querySelector('.cb-nearby-prev');
		var next = root.querySelector('.cb-nearby-next');

		if (!track) {
			return;
		}

		var visible = parseInt(root.getAttribute('data-cb-nearby-visible'), 10);
		if (!visible || visible < 1) {
			visible = 3;
		}
		root.style.setProperty('--cb-nearby-visible', String(visible));
		root.classList.add('cb-nearby-ready');

		var items = track.querySelectorAll('.cb-nearby-item');

		function step() {
			// Distance of one card including the gap.
			if (items.length < 2) {
				return track.clientWidth;
			}
			return items[1].getBoundingClientRect().left - items[0].getBoundingClientRect().left;
		}

		function atStart() {
			return track.scrollLeft <= 1;
		}

		function atEnd() {
			return track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
		}

		function updateButtons() {
			if (prev) {
				prev.disabled = atStart();
			}
			if (next) {
				next.disabled = atEnd();
			}
		}

		function scrollByCards(direction) {
			track.scrollBy({ left: direction * step(), behavior: 'smooth' });
		}

		if (prev) {
			prev.addEventListener('click', function () {
				scrollByCards(-1);
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				scrollByCards(1);
			});
		}
		track.addEventListener('scroll', function () {
			window.requestAnimationFrame(updateButtons);
		});

		// Only auto-advance / show controls when the content overflows.
		var overflows = track.scrollWidth > track.clientWidth + 1;
		if (!overflows) {
			if (prev) {
				prev.style.display = 'none';
			}
			if (next) {
				next.style.display = 'none';
			}
			return;
		}

		updateButtons();

		var timer = null;
		function advance() {
			if (atEnd()) {
				track.scrollTo({ left: 0, behavior: 'smooth' });
			} else {
				scrollByCards(1);
			}
		}
		function play() {
			stop();
			timer = window.setInterval(advance, AUTOPLAY_INTERVAL);
		}
		function stop() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		var pauseTargets = [root];
		pauseTargets.forEach(function (el) {
			el.addEventListener('mouseenter', stop);
			el.addEventListener('mouseleave', play);
			el.addEventListener('focusin', stop);
			el.addEventListener('focusout', play);
		});

		// Respect users who prefer reduced motion: no autoplay.
		var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (!reduceMotion) {
			play();
		}

		if (viewport) {
			viewport.setAttribute('tabindex', '0');
		}
	}

	function init() {
		var carousels = document.querySelectorAll('.cb-nearby');
		Array.prototype.forEach.call(carousels, initCarousel);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
