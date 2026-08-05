/**
 * Public referral wizard — progressive enhancement over one native HTML POST form.
 * No AJAX. Server validation remains authoritative.
 */
(function () {
	'use strict';

	var STEP_LABELS = {
		1: 'About You',
		2: 'Person Needing Care',
		3: 'Care Needs',
		4: 'Documents',
		5: 'Review & Submit'
	};

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

	function PublicReferralWizard(root) {
		this.root = root;
		this.form = root.querySelector('.jmrs-public-referral__form');
		if (!this.form) {
			return;
		}

		this.panels = Array.prototype.slice.call(root.querySelectorAll('[data-jmrs-step]'));
		this.live = root.querySelector('#jmrs-wizard-live');
		this.progressList = root.querySelector('#jmrs-wizard-progress');
		this.progressCompact = root.querySelector('#jmrs-progress-compact');
		this.progressFill = root.querySelector('#jmrs-progress-bar-fill');
		this.btnBack = root.querySelector('[data-jmrs-back]');
		this.btnContinue = root.querySelector('[data-jmrs-continue]');
		this.btnFinal = root.querySelector('[data-jmrs-final-submit]');
		this.btnStart = root.querySelector('[data-jmrs-start]');
		this.referrerType = root.querySelector('[data-jmrs-referrer-type]');
		this.orgField = root.querySelector('[data-jmrs-org-field]');
		this.fileInput = root.querySelector('[data-jmrs-files]');
		this.fileSummary = root.querySelector('#jmrs-file-summary');
		this.step1Heading = root.querySelector('#jmrs-step-1-heading');

		try {
			this.orgTypes = JSON.parse(root.getAttribute('data-jmrs-org-types') || '[]');
		} catch (e) {
			this.orgTypes = ['hospital', 'gp', 'social_worker', 'local_authority', 'care_provider', 'other'];
		}

		this.maxUploads = parseInt(root.getAttribute('data-jmrs-max-uploads') || '3', 10) || 3;
		this.maxUploadMb = parseInt(root.getAttribute('data-jmrs-max-upload-mb') || '10', 10) || 10;
		this.company = root.getAttribute('data-jmrs-company') || '';

		var initial = parseInt(root.getAttribute('data-jmrs-initial-step') || '0', 10);
		if (isNaN(initial) || initial < 0 || initial > 5) {
			initial = 0;
		}
		this.step = initial;

		this.activate();
		this.bind();
		this.goTo(this.step, { announce: false, focus: root.getAttribute('data-jmrs-focus-errors') !== '1' });

		if (root.getAttribute('data-jmrs-focus-errors') === '1') {
			var summary = document.getElementById('jmrs-public-error-summary');
			if (summary) {
				summary.focus();
			}
		}
	}

	PublicReferralWizard.prototype.activate = function () {
		this.root.classList.add('jmrs-js');
		Array.prototype.forEach.call(
			this.root.querySelectorAll('.jmrs-public-referral__wizard-only'),
			function (el) {
				el.hidden = false;
			}
		);
	};

	PublicReferralWizard.prototype.bind = function () {
		var self = this;

		if (this.btnStart) {
			this.btnStart.addEventListener('click', function () {
				self.goTo(1, { announce: true, focus: true });
			});
		}
		if (this.btnBack) {
			this.btnBack.addEventListener('click', function () {
				self.goTo(Math.max(0, self.step - 1), { announce: true, focus: true });
			});
		}
		if (this.btnContinue) {
			this.btnContinue.addEventListener('click', function () {
				if (!self.validateStep(self.step)) {
					return;
				}
				self.goTo(Math.min(5, self.step + 1), { announce: true, focus: true });
			});
		}

		Array.prototype.forEach.call(this.root.querySelectorAll('[data-jmrs-edit]'), function (btn) {
			btn.addEventListener('click', function () {
				var target = parseInt(btn.getAttribute('data-jmrs-edit') || '1', 10);
				self.goTo(target, { announce: true, focus: true });
			});
		});

		if (this.referrerType) {
			this.referrerType.addEventListener('change', function () {
				self.updateOrgVisibility();
				self.updateAboutHeading();
			});
			this.updateOrgVisibility();
			this.updateAboutHeading();
		}

		if (this.fileInput) {
			this.fileInput.addEventListener('change', function () {
				self.renderFileSummary();
			});
			this.renderFileSummary();
		}

		Array.prototype.forEach.call(this.root.querySelectorAll('[data-jmrs-print]'), function (btn) {
			btn.addEventListener('click', function () {
				window.print();
			});
		});

		this.form.addEventListener('submit', function (event) {
			self.onSubmit(event);
		});

		this.form.addEventListener('input', function (event) {
			var t = event.target;
			if (t && t.getAttribute && t.getAttribute('aria-invalid') === 'true') {
				t.setAttribute('aria-invalid', 'false');
				var err = t.parentNode && t.parentNode.querySelector('.jmrs-public-referral__field-error[data-client]');
				if (err && err.parentNode) {
					err.parentNode.removeChild(err);
				}
			}
		});
	};

	PublicReferralWizard.prototype.announce = function (message) {
		if (!this.live) {
			return;
		}
		this.live.textContent = '';
		var live = this.live;
		window.setTimeout(function () {
			live.textContent = message;
		}, 20);
	};

	PublicReferralWizard.prototype.dispatchStepEvent = function (step) {
		try {
			document.dispatchEvent(
				new CustomEvent('jmrs:publicReferralStepChanged', {
					detail: { step: step }
				})
			);
		} catch (e) {
			/* ignore */
		}
	};

	PublicReferralWizard.prototype.goTo = function (step, opts) {
		opts = opts || {};
		this.step = step;

		this.panels.forEach(function (panel) {
			var n = parseInt(panel.getAttribute('data-jmrs-step') || '0', 10);
			if (n === step) {
				panel.classList.add('is-active');
			} else {
				panel.classList.remove('is-active');
			}
		});

		this.updateProgress();
		this.updateNav();

		if (step === 5) {
			this.populateReview();
		}

		if (opts.announce) {
			var label = step === 0 ? 'Welcome' : STEP_LABELS[step] || '';
			this.announce('Step ' + (step === 0 ? 'welcome' : step + ' of 5') + '. ' + label);
		}

		if (opts.focus) {
			var heading = this.root.querySelector('#jmrs-step-' + step + '-heading');
			if (heading && typeof heading.focus === 'function') {
				heading.focus();
			}
		}

		try {
			if (window.history && window.history.replaceState) {
				window.history.replaceState({ jmrsStep: step }, '', window.location.pathname + window.location.search);
			}
		} catch (e) {
			/* ignore */
		}

		this.dispatchStepEvent(step);
	};

	PublicReferralWizard.prototype.updateProgress = function () {
		var userStep = this.step < 1 ? 0 : this.step;
		if (this.progressCompact) {
			if (userStep < 1) {
				this.progressCompact.textContent = 'Welcome';
			} else {
				this.progressCompact.textContent =
					'Step ' + userStep + ' of 5 — ' + (STEP_LABELS[userStep] || '');
			}
		}
		if (this.progressFill) {
			var pct = userStep < 1 ? 0 : (userStep / 5) * 100;
			this.progressFill.style.width = pct + '%';
		}
		if (this.progressList) {
			Array.prototype.forEach.call(this.progressList.querySelectorAll('[data-step]'), function (item) {
				var n = parseInt(item.getAttribute('data-step') || '0', 10);
				item.classList.remove('is-current', 'is-complete', 'is-upcoming');
				item.removeAttribute('aria-current');
				if (userStep < 1) {
					item.classList.add('is-upcoming');
				} else if (n === userStep) {
					item.classList.add('is-current');
					item.setAttribute('aria-current', 'step');
				} else if (n < userStep) {
					item.classList.add('is-complete');
				} else {
					item.classList.add('is-upcoming');
				}
			});
		}
	};

	PublicReferralWizard.prototype.updateNav = function () {
		var step = this.step;
		if (this.btnBack) {
			this.btnBack.hidden = step <= 0;
		}
		if (this.btnContinue) {
			this.btnContinue.hidden = step === 0 || step === 5;
		}
		if (this.btnFinal) {
			this.btnFinal.hidden = step !== 5;
		}
		var nav = this.root.querySelector('#jmrs-wizard-nav');
		if (nav) {
			nav.hidden = step === 0;
		}
	};

	PublicReferralWizard.prototype.updateOrgVisibility = function () {
		if (!this.orgField || !this.referrerType) {
			return;
		}
		var type = this.referrerType.value || '';
		var show = this.orgTypes.indexOf(type) !== -1;
		this.orgField.hidden = !show;
		if (!show) {
			/* Keep value in DOM for no-JS / progressive enhancement; do not clear. */
		}
	};

	PublicReferralWizard.prototype.updateAboutHeading = function () {
		if (!this.step1Heading || !this.referrerType) {
			return;
		}
		var type = this.referrerType.value || '';
		var personal = ['self', 'family', 'friend'];
		var text =
			personal.indexOf(type) !== -1 || type === ''
				? this.step1Heading.getAttribute('data-jmrs-heading-personal')
				: this.step1Heading.getAttribute('data-jmrs-heading-org');
		if (text) {
			this.step1Heading.textContent = text;
		}
	};

	PublicReferralWizard.prototype.clearClientErrors = function (panel) {
		Array.prototype.forEach.call(panel.querySelectorAll('[aria-invalid="true"]'), function (el) {
			el.setAttribute('aria-invalid', 'false');
		});
		Array.prototype.forEach.call(panel.querySelectorAll('.jmrs-public-referral__field-error[data-client]'), function (el) {
			if (el.parentNode) {
				el.parentNode.removeChild(el);
			}
		});
	};

	PublicReferralWizard.prototype.showClientError = function (field, message) {
		if (!field) {
			return;
		}
		field.setAttribute('aria-invalid', 'true');
		var wrap = field.closest('.jmrs-public-referral__field') || field.parentNode;
		if (!wrap) {
			return;
		}
		var existing = wrap.querySelector('.jmrs-public-referral__field-error[data-client]');
		if (existing) {
			existing.textContent = message;
			return;
		}
		var p = document.createElement('p');
		p.className = 'jmrs-public-referral__field-error';
		p.setAttribute('data-client', '1');
		p.textContent = message;
		wrap.appendChild(p);
	};

	PublicReferralWizard.prototype.validateStep = function (step) {
		var panel = this.root.querySelector('[data-jmrs-step="' + step + '"]');
		if (!panel) {
			return true;
		}
		this.clearClientErrors(panel);
		var firstInvalid = null;

		var requireFilled = function (id, message) {
			var el = panel.querySelector('#' + id);
			if (!el) {
				return;
			}
			var val = (el.value || '').trim();
			if (!val) {
				this.showClientError(el, message);
				if (!firstInvalid) {
					firstInvalid = el;
				}
			}
		}.bind(this);

		if (step === 1) {
			requireFilled('jmrs_referrer_type', 'Please select who is making this referral.');
			requireFilled('jmrs_referrer_name', 'Please enter your name.');
			var email = (panel.querySelector('#jmrs_referrer_email') || {}).value || '';
			var phone = (panel.querySelector('#jmrs_referrer_phone') || {}).value || '';
			if (!String(email).trim() && !String(phone).trim()) {
				var emailEl = panel.querySelector('#jmrs_referrer_email');
				this.showClientError(emailEl, 'Please provide an email address or phone number.');
				if (!firstInvalid) {
					firstInvalid = emailEl;
				}
			} else if (String(email).trim() && emailElInvalid(email)) {
				var em = panel.querySelector('#jmrs_referrer_email');
				this.showClientError(em, 'Please enter a valid email address.');
				if (!firstInvalid) {
					firstInvalid = em;
				}
			}
		}

		if (step === 2) {
			requireFilled('jmrs_client_first_name', 'Please enter the first name.');
			requireFilled('jmrs_client_last_name', 'Please enter the last name.');
		}

		if (step === 3) {
			requireFilled('jmrs_service_type_id', 'Please select a service type.');
			requireFilled('jmrs_care_requirements', 'Please describe the care requirements.');
		}

		if (step === 4 && this.fileInput && this.fileInput.files) {
			var files = this.fileInput.files;
			if (files.length > this.maxUploads) {
				this.showClientError(this.fileInput, 'Too many files selected.');
				firstInvalid = this.fileInput;
			} else {
				for (var i = 0; i < files.length; i++) {
					if (files[i].size > this.maxUploadMb * 1048576) {
						this.showClientError(this.fileInput, 'One or more files exceed the maximum size.');
						firstInvalid = this.fileInput;
						break;
					}
				}
			}
		}

		if (step === 5) {
			['jmrs_consent_permission', 'jmrs_consent_assessment', 'jmrs_consent_privacy'].forEach(
				function (name) {
					var el = this.form.querySelector('[name="' + name + '"]');
					if (el && !el.checked) {
						this.showClientError(el, 'This consent is required.');
						if (!firstInvalid) {
							firstInvalid = el;
						}
					}
				}.bind(this)
			);
		}

		if (firstInvalid) {
			this.announce('Please fix the errors on this step.');
			if (typeof firstInvalid.focus === 'function') {
				firstInvalid.focus();
			}
			return false;
		}
		return true;
	};

	function emailElInvalid(value) {
		var v = String(value || '').trim();
		if (!v) {
			return false;
		}
		return v.indexOf('@') < 1 || v.indexOf('.') < 0;
	}

	PublicReferralWizard.prototype.renderFileSummary = function () {
		if (!this.fileSummary) {
			return;
		}
		this.fileSummary.innerHTML = '';
		if (!this.fileInput || !this.fileInput.files || !this.fileInput.files.length) {
			var empty = document.createElement('li');
			empty.textContent = 'No files selected';
			this.fileSummary.appendChild(empty);
			return;
		}
		Array.prototype.forEach.call(this.fileInput.files, function (file) {
			var li = document.createElement('li');
			li.textContent = file.name + ' (' + Math.max(1, Math.round(file.size / 1024)) + ' KB)';
			this.fileSummary.appendChild(li);
		}.bind(this));
	};

	PublicReferralWizard.prototype.val = function (name) {
		var el = this.form.querySelector('[name="' + name + '"]');
		if (!el) {
			return '';
		}
		if (el.type === 'radio') {
			var checked = this.form.querySelector('[name="' + name + '"]:checked');
			return checked ? checked.value : '';
		}
		if (el.type === 'checkbox') {
			return el.checked ? el.value : '';
		}
		if (el.tagName === 'SELECT') {
			var opt = el.options[el.selectedIndex];
			return opt ? (opt.text || opt.value) : '';
		}
		return el.value || '';
	};

	PublicReferralWizard.prototype.raw = function (name) {
		var el = this.form.querySelector('[name="' + name + '"]');
		if (!el) {
			return '';
		}
		if (el.type === 'radio') {
			var checked = this.form.querySelector('[name="' + name + '"]:checked');
			return checked ? checked.value : '';
		}
		return el.value || '';
	};

	PublicReferralWizard.prototype.fillDl = function (id, rows) {
		var dl = this.root.querySelector('#' + id);
		if (!dl) {
			return;
		}
		dl.innerHTML = '';
		rows.forEach(function (row) {
			if (!row.value) {
				return;
			}
			var dt = document.createElement('dt');
			dt.textContent = row.label;
			var dd = document.createElement('dd');
			var text = String(row.value);
			if (row.truncate && text.length > 220) {
				var short = text.slice(0, 220) + '…';
				dd.textContent = short + ' ';
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'jmrs-public-referral__link-btn';
				btn.textContent = 'Read more';
				btn.setAttribute('aria-expanded', 'false');
				btn.addEventListener('click', function () {
					var expanded = btn.getAttribute('aria-expanded') === 'true';
					if (expanded) {
						dd.firstChild.textContent = short + ' ';
						btn.textContent = 'Read more';
						btn.setAttribute('aria-expanded', 'false');
					} else {
						dd.firstChild.textContent = text + ' ';
						btn.textContent = 'Show less';
						btn.setAttribute('aria-expanded', 'true');
					}
				});
				dd.appendChild(btn);
			} else {
				dd.textContent = text;
			}
			dl.appendChild(dt);
			dl.appendChild(dd);
		});
	};

	PublicReferralWizard.prototype.populateReview = function () {
		var typeSelect = this.form.querySelector('#jmrs_referrer_type');
		var typeLabel =
			typeSelect && typeSelect.options[typeSelect.selectedIndex]
				? typeSelect.options[typeSelect.selectedIndex].text
				: '';

		this.fillDl('jmrs-summary-about', [
			{ label: 'Referrer type', value: typeLabel },
			{ label: 'Name', value: this.raw('jmrs_referrer_name') },
			{ label: 'Organisation', value: this.raw('jmrs_referrer_organisation') },
			{ label: 'Email', value: this.raw('jmrs_referrer_email') },
			{ label: 'Phone', value: this.raw('jmrs_referrer_phone') },
			{ label: 'Relationship', value: this.raw('jmrs_relationship_to_client') }
		]);

		var address = [
			this.raw('jmrs_address_line_1'),
			this.raw('jmrs_address_line_2'),
			this.raw('jmrs_city'),
			this.raw('jmrs_postcode')
		]
			.filter(Boolean)
			.join(', ');

		this.fillDl('jmrs-summary-person', [
			{ label: 'Name', value: (this.raw('jmrs_client_first_name') + ' ' + this.raw('jmrs_client_last_name')).trim() },
			{ label: 'Email', value: this.raw('jmrs_client_email') },
			{ label: 'Phone', value: this.raw('jmrs_client_phone') },
			{ label: 'Date of birth', value: this.raw('jmrs_client_date_of_birth') },
			{ label: 'Address', value: address }
		]);

		var service = this.form.querySelector('#jmrs_service_type_id');
		var serviceLabel =
			service && service.options[service.selectedIndex] ? service.options[service.selectedIndex].text : '';
		var contact = this.form.querySelector('#jmrs_preferred_contact_method');
		var contactLabel =
			contact && contact.options[contact.selectedIndex] ? contact.options[contact.selectedIndex].text : '';
		var priority = this.raw('jmrs_priority');
		var priorityLabel = priority === 'urgent' ? 'Urgent' : priority === 'routine' ? 'Routine' : '';

		this.fillDl('jmrs-summary-care', [
			{ label: 'Service', value: serviceLabel },
			{ label: 'Start date', value: this.raw('jmrs_care_start_date') },
			{ label: 'Contact method', value: contactLabel },
			{ label: 'Priority', value: priorityLabel },
			{ label: 'Requirements', value: this.raw('jmrs_care_requirements'), truncate: true },
			{ label: 'Additional info', value: this.raw('jmrs_additional_information'), truncate: true }
		]);

		var docs = this.root.querySelector('#jmrs-summary-docs');
		if (docs) {
			docs.innerHTML = '';
			if (!this.fileInput || !this.fileInput.files || !this.fileInput.files.length) {
				docs.textContent = 'No documents selected';
			} else {
				var ul = document.createElement('ul');
				ul.className = 'jmrs-public-referral__file-summary';
				Array.prototype.forEach.call(this.fileInput.files, function (file) {
					var li = document.createElement('li');
					li.textContent = file.name;
					ul.appendChild(li);
				});
				docs.appendChild(ul);
			}
		}
	};

	PublicReferralWizard.prototype.onSubmit = function (event) {
		if (this.form.getAttribute('data-jmrs-submitting') === '1') {
			event.preventDefault();
			return;
		}

		// Final step client consent check when submitting from wizard.
		if (this.root.classList.contains('jmrs-js') && this.step !== 5) {
			event.preventDefault();
			this.goTo(5, { announce: true, focus: true });
			return;
		}

		if (this.root.classList.contains('jmrs-js') && !this.validateStep(5)) {
			event.preventDefault();
			return;
		}

		var submitter =
			event.submitter ||
			this.form.querySelector('button[type="submit"]:not([hidden]), input[type="submit"]');

		preserveSubmitter(this.form, submitter);

		var label = this.form.getAttribute('data-jmrs-busy-label') || 'Sending…';
		if (submitter) {
			submitter.setAttribute('aria-busy', 'true');
			if (!submitter.getAttribute('data-jmrs-original-label')) {
				submitter.setAttribute(
					'data-jmrs-original-label',
					submitter.tagName === 'INPUT' ? submitter.value : submitter.textContent
				);
			}
			if (submitter.tagName === 'INPUT') {
				submitter.value = label;
			} else {
				submitter.textContent = label;
			}
		}

		this.form.setAttribute('data-jmrs-submitting', '1');

		try {
			document.dispatchEvent(new CustomEvent('jmrs:publicReferralSubmitted', { detail: {} }));
		} catch (e) {
			/* ignore */
		}

		window.setTimeout(function () {
			if (!submitter) {
				return;
			}
			submitter.disabled = true;
			var others = this.form.querySelectorAll('button[type="submit"], input[type="submit"]');
			Array.prototype.forEach.call(others, function (btn) {
				if (btn !== submitter) {
					btn.disabled = true;
				}
			});
		}.bind(this), 0);
	};

	ready(function () {
		var success = document.getElementById('jmrs-public-success');
		if (success) {
			success.focus();
			Array.prototype.forEach.call(document.querySelectorAll('[data-jmrs-print]'), function (btn) {
				btn.addEventListener('click', function () {
					window.print();
				});
			});
		}

		var root = document.querySelector('.jmrs-public-referral:not(.jmrs-public-referral--success)');
		if (!root || !root.querySelector('.jmrs-public-referral__form')) {
			return;
		}
		new PublicReferralWizard(root);
	});
})();
