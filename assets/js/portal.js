(function () {
	'use strict';

	var root = document.getElementById('jmrs-portal-root');
	if (!root) {
		return;
	}

	var toggle = document.getElementById('jmrs-portal-nav-toggle');
	var backdrop = document.getElementById('jmrs-portal-backdrop');
	var sidebar = document.getElementById('jmrs-portal-sidebar');

	function setOpen(open) {
		root.classList.toggle('is-nav-open', open);
		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
		if (backdrop) {
			if (open) {
				backdrop.removeAttribute('hidden');
			} else {
				backdrop.setAttribute('hidden', 'hidden');
			}
		}
	}

	function closeNav() {
		setOpen(false);
	}

	if (toggle) {
		toggle.addEventListener('click', function () {
			setOpen(!root.classList.contains('is-nav-open'));
		});
	}

	if (backdrop) {
		backdrop.addEventListener('click', closeNav);
	}

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && root.classList.contains('is-nav-open')) {
			closeNav();
			if (toggle) {
				toggle.focus();
			}
		}
	});

	if (sidebar) {
		sidebar.addEventListener('keydown', function (event) {
			if (event.key !== 'Tab' || !root.classList.contains('is-nav-open')) {
				return;
			}
			var focusable = sidebar.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])');
			if (!focusable.length) {
				return;
			}
			var first = focusable[0];
			var last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});
	}
})();
