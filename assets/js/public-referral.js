/**
 * Public referral intake — progressive enhancement only.
 * Native HTML POST remains the submission path (no AJAX).
 *
 * Important: disabling the clicked submit control during the submit event
 * can cancel the browser's pending POST. Preserve name/value first, then
 * defer disable so native submission proceeds.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function submitterValue(el) {
		if (!el) {
			return '';
		}

		if (el.tagName === 'BUTTON') {
			var attr = el.getAttribute('value');
			if (null !== attr && '' !== attr) {
				return attr;
			}
			return el.value || '1';
		}

		return el.value || '1';
	}

	/**
	 * Copy the active submit control into a hidden field so its name/value
	 * still posts after the visible control is disabled.
	 */
	function preserveSubmitter(form, submitter) {
		if (!form || !submitter) {
			return;
		}

		var name = submitter.getAttribute('name');
		if (!name) {
			return;
		}

		var previous = form.querySelectorAll('input[data-jmrs-preserved-submitter="1"]');
		Array.prototype.forEach.call(previous, function (node) {
			if (node.parentNode) {
				node.parentNode.removeChild(node);
			}
		});

		var hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = name;
		hidden.value = submitterValue(submitter);
		hidden.setAttribute('data-jmrs-preserved-submitter', '1');
		form.appendChild(hidden);
	}

	function setBusyLabel(el, label) {
		if (!el) {
			return;
		}

		el.setAttribute('aria-busy', 'true');

		if (!el.getAttribute('data-jmrs-original-label')) {
			el.setAttribute(
				'data-jmrs-original-label',
				el.tagName === 'INPUT' ? el.value : el.textContent
			);
		}

		if (el.tagName === 'INPUT') {
			el.value = label;
		} else {
			el.textContent = label;
		}
	}

	function clearBusy(el) {
		if (!el) {
			return;
		}

		el.removeAttribute('aria-busy');
		el.disabled = false;

		var original = el.getAttribute('data-jmrs-original-label');
		if (null !== original) {
			if (el.tagName === 'INPUT') {
				el.value = original;
			} else {
				el.textContent = original;
			}
			el.removeAttribute('data-jmrs-original-label');
		}
	}

	ready(function () {
		var root = document.querySelector('.jmrs-public-referral');
		if (!root) {
			return;
		}

		if (root.getAttribute('data-jmrs-focus-errors') === '1') {
			var summary = document.getElementById('jmrs-public-error-summary');
			if (summary) {
				summary.focus();
			}
		}

		var success = document.getElementById('jmrs-public-success');
		if (success && root.classList.contains('jmrs-public-referral--success')) {
			success.focus();
		}

		var form = root.querySelector('.jmrs-public-referral__form');
		if (!form) {
			return;
		}

		form.addEventListener('submit', function (event) {
			// Double-submit guard only — never block the first native POST.
			if (form.getAttribute('data-jmrs-submitting') === '1') {
				event.preventDefault();
				return;
			}

			var submitter =
				event.submitter ||
				form.querySelector('button[type="submit"], input[type="submit"]');

			// Optional progressive checkValidity only when browser supports it.
			// Form uses novalidate for server-authoritative errors; skip blocking
			// unless the form explicitly opts in later.
			if (form.hasAttribute('data-jmrs-client-validate') && typeof form.checkValidity === 'function') {
				if (!form.checkValidity()) {
					event.preventDefault();
					clearBusy(submitter);
					form.removeAttribute('data-jmrs-submitting');

					var invalid = form.querySelector(':invalid');
					if (invalid && typeof invalid.focus === 'function') {
						invalid.focus();
					}
					return;
				}
			}

			// 1) Preserve named submitter while it is still enabled.
			preserveSubmitter(form, submitter);

			var label = form.getAttribute('data-jmrs-busy-label') || 'Submitting…';
			setBusyLabel(submitter, label);
			form.setAttribute('data-jmrs-submitting', '1');

			// 2) Defer disable so the browser finishes starting the native POST.
			// Synchronous disable during submit commonly cancels navigation.
			window.setTimeout(function () {
				if (!submitter) {
					return;
				}
				submitter.disabled = true;

				var others = form.querySelectorAll('button[type="submit"], input[type="submit"]');
				Array.prototype.forEach.call(others, function (btn) {
					if (btn !== submitter) {
						btn.disabled = true;
					}
				});
			}, 0);
		});
	});
})();
