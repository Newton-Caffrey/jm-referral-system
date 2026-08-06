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

		// Closing the drawer after a nav link click avoids it staying open
		// over the newly-loaded page on mobile.
		sidebar.addEventListener('click', function (event) {
			var link = event.target && event.target.closest ? event.target.closest('a') : null;
			if (link && root.classList.contains('is-nav-open')) {
				closeNav();
			}
		});
	}

	root.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.getAttribute) {
			return;
		}
		var message = form.getAttribute('data-jmrs-confirm');
		if (message && !window.confirm(message)) {
			event.preventDefault();
			return;
		}

		setFormSubmitting(form, event.submitter);
	});

	/**
	 * Marks the submitting button as busy so staff get visible feedback,
	 * and disables any other submit buttons in the same form to prevent
	 * duplicate submissions while the request is in flight.
	 *
	 * The submitter itself is intentionally never given the `disabled`
	 * attribute: disabled form controls are excluded from the submitted
	 * data, and several portal actions (e.g. archive/restore) are
	 * identified server-side by the submit button's name/value pair.
	 */
	function setFormSubmitting(form, submitter) {
		if (!submitter || !submitter.getAttribute) {
			submitter = form.querySelector('button[type="submit"], input[type="submit"]');
		}

		var buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
		for (var i = 0; i < buttons.length; i++) {
			if (buttons[i] !== submitter) {
				buttons[i].disabled = true;
			}
		}

		if (submitter) {
			submitter.classList.add('is-loading');
			submitter.setAttribute('aria-busy', 'true');
		}
	}
})();
