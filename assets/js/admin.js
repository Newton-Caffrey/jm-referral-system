/**
 * JM Referral System — shared admin UX helpers
 * Double-submit prevention, confirmations, export busy state.
 *
 * Important: named submit controls must remain represented in POST data.
 * Disabled fields are omitted by the browser, so the clicked submitter's
 * name/value is copied to a hidden input before any control is disabled.
 */
(function () {
	'use strict';

	var i18n = (window.jmrsAdmin && window.jmrsAdmin.i18n) || {};

	function busyLabelFor(el) {
		var custom = el.getAttribute('data-jmrs-busy');
		if (custom) {
			return custom;
		}

		var name = ((el.getAttribute('name') || '') + ' ' + (el.value || '') + ' ' + (el.textContent || '')).toLowerCase();

		if (name.indexOf('generat') !== -1) {
			return i18n.generating || 'Generating...';
		}
		if (name.indexOf('upload') !== -1) {
			return i18n.uploading || 'Uploading...';
		}
		if (name.indexOf('complet') !== -1 || name.indexOf('execute') !== -1) {
			return i18n.completing || 'Completing Visit...';
		}
		if (name.indexOf('review') !== -1) {
			return i18n.reviewing || 'Reviewing...';
		}
		if (name.indexOf('archive') !== -1) {
			return i18n.archiving || 'Archiving...';
		}
		if (name.indexOf('restore') !== -1) {
			return i18n.restoring || 'Restoring...';
		}
		if (name.indexOf('export') !== -1) {
			return i18n.exporting || 'Exporting...';
		}

		return i18n.saving || 'Saving...';
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

	function setBusy(el) {
		if (!el || el.getAttribute('aria-busy') === 'true') {
			return;
		}

		el.setAttribute('aria-busy', 'true');
		el.classList.add('disabled');

		if (el.tagName === 'BUTTON' || (el.tagName === 'INPUT' && (el.type === 'submit' || el.type === 'button'))) {
			if (!el.getAttribute('data-jmrs-original-label')) {
				el.setAttribute(
					'data-jmrs-original-label',
					el.tagName === 'INPUT' ? el.value : el.textContent
				);
			}
			var label = busyLabelFor(el);
			if (el.tagName === 'INPUT') {
				el.value = label;
			} else {
				el.textContent = label;
			}
			el.disabled = true;
		} else if (el.tagName === 'A') {
			el.setAttribute('aria-disabled', 'true');
			el.classList.add('disabled');
		}
	}

	function isSubmitControl(el) {
		if (!el) {
			return false;
		}
		if (el.tagName === 'BUTTON' && (!el.type || el.type === 'submit')) {
			return true;
		}
		return el.tagName === 'INPUT' && el.type === 'submit';
	}

	function onSubmit(event) {
		var form = event.target;
		if (!form || form.tagName !== 'FORM') {
			return;
		}

		// Only guard plugin forms inside JM admin screens.
		if (!document.body.classList.contains('jmrs-admin')) {
			return;
		}

		if (form.getAttribute('data-jmrs-submitting') === '1') {
			event.preventDefault();
			return;
		}

		var submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
		var msg = form.getAttribute('data-jmrs-confirm');
		if (!msg && submitter) {
			msg = submitter.getAttribute('data-jmrs-confirm');
		}

		if (msg && !window.confirm(msg)) {
			event.preventDefault();
			return;
		}

		// 1) Preserve named submitter while it is still enabled.
		preserveSubmitter(form, submitter);

		// 2) Then show busy state and disable submit controls only.
		if (submitter) {
			setBusy(submitter);
		}

		var submits = form.querySelectorAll('button[type="submit"], input[type="submit"]');
		Array.prototype.forEach.call(submits, function (btn) {
			if (btn !== submitter) {
				btn.disabled = true;
			}
		});

		form.setAttribute('data-jmrs-submitting', '1');
	}

	function onClick(event) {
		var el = event.target.closest('[data-jmrs-confirm]');
		if (!el || el.tagName === 'FORM' || isSubmitControl(el)) {
			return;
		}

		var msg = el.getAttribute('data-jmrs-confirm') || i18n.confirmDefault || 'Are you sure you want to continue?';
		if (!window.confirm(msg)) {
			event.preventDefault();
			event.stopPropagation();
			return;
		}

		if (el.matches('a')) {
			setBusy(el);
		}
	}

	document.addEventListener('submit', onSubmit, true);
	document.addEventListener('click', onClick, true);
})();
