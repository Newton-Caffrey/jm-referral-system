(function () {
	'use strict';

	var root = document.querySelector('[data-jmrs-mgmt]');
	if (!root) {
		return;
	}

	function selectTab(key) {
		['pipeline', 'homes', 'team', 'recs'].forEach(function (k) {
			var tab = document.getElementById('jmrs-tab-' + k);
			var view = document.getElementById('jmrs-view-' + k);
			if (!tab || !view) {
				return;
			}
			var on = k === key;
			tab.setAttribute('aria-selected', on ? 'true' : 'false');
			view.hidden = !on;
		});
	}

	['pipeline', 'homes', 'team', 'recs'].forEach(function (k) {
		var tab = document.getElementById('jmrs-tab-' + k);
		if (tab) {
			tab.addEventListener('click', function () {
				selectTab(k);
			});
		}
	});

	var printBtn = document.getElementById('jmrs-mgmt-print');
	if (printBtn) {
		printBtn.addEventListener('click', function () {
			window.print();
		});
	}

	function bindStageGo(el) {
		el.addEventListener('click', function () {
			go(el.getAttribute('data-stage-key') || '');
		});
		if (el.tagName === 'BUTTON') {
			el.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					go(el.getAttribute('data-stage-key') || '');
				}
			});
		}
	}

	function go(key) {
		selectTab('pipeline');
		var el = document.getElementById('jmrs-stage-' + key);
		if (el) {
			el.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	function renderFunnel() {
		var svg = document.getElementById('jmrs-mgmt-funnel');
		var legend = document.getElementById('jmrs-mgmt-legend');
		if (!svg || !legend) {
			return;
		}

		var raw = svg.getAttribute('data-funnel') || '[]';
		var stages;
		try {
			stages = JSON.parse(raw);
		} catch (e) {
			stages = [];
		}
		if (!Array.isArray(stages) || !stages.length) {
			return;
		}

		var vals = stages.map(function (s) {
			return Number(s.reached) || 0;
		});
		var max = Math.max.apply(null, vals.concat([1]));
		var W = 1200;
		/* Tighter viewBox than prototype (260) — no bottom “taken forward” caption. */
		var H = 168;
		var pad = 10;
		var colW = (W - pad * 2) / stages.length;
		var maxH = H - 20;

		function h(v) {
			return (0.16 + 0.84 * (v / max)) * maxH;
		}

		var displayFont = "'Bricolage Grotesque', 'Segoe UI', Arial, sans-serif";
		var uiFont = "'Inter Tight', system-ui, sans-serif";

		var html = '';
		stages.forEach(function (s, i) {
			var x0 = pad + i * colW;
			var x1 = x0 + colW;
			var hL = h(vals[i]);
			var hR = h(i < stages.length - 1 ? vals[i + 1] : vals[i] * 0.86);
			var cy = maxH / 2 + 6;
			var pts = [
				[x0, cy - hL / 2],
				[x1, cy - hR / 2],
				[x1, cy + hR / 2],
				[x0, cy + hL / 2]
			]
				.map(function (p) {
					return p.join(',');
				})
				.join(' ');
			var nowN = Number(s.here_now) || 0;
			var colour = s.colour || '#2B4C7E';
			var name = String(s.name || '');
			/* Click-only on SVG — no tabindex (avoids portal [tabindex]:focus-visible ring artefacts). */
			html +=
				'<g class="jmrs-mgmt-seg" data-stage-key="' +
				String(s.key || '') +
				'" aria-hidden="true">' +
				'<polygon points="' +
				pts +
				'" fill="' +
				colour +
				'" opacity="0.95"></polygon>' +
				'<text x="' +
				(x0 + colW / 2) +
				'" y="' +
				(cy - 2) +
				'" text-anchor="middle" fill="#fff" style="font-family:' +
				displayFont +
				';font-weight:800;font-size:30px;letter-spacing:-.03em">' +
				vals[i] +
				'</text>' +
				'<text x="' +
				(x0 + colW / 2) +
				'" y="' +
				(cy + 16) +
				'" text-anchor="middle" fill="#fff" opacity=".9" style="font-family:' +
				uiFont +
				';font-weight:600;font-size:12px">' +
				nowN +
				' here now</text></g>';
		});
		svg.innerHTML = html;

		legend.innerHTML = stages
			.map(function (s, i) {
				var colour = s.colour || '#2B4C7E';
				var nowN = Number(s.here_now) || 0;
				var name = String(s.name || '');
				return (
					'<button type="button" class="jmrs-mgmt__leg" style="border-top-color:' +
					colour +
					'" data-stage-key="' +
					String(s.key || '') +
					'" aria-label="' +
					name +
					': ' +
					vals[i] +
					' reached, ' +
					nowN +
					' here now">' +
					'<span class="jmrs-mgmt__leg-num" style="color:' +
					colour +
					'">' +
					vals[i] +
					'</span>' +
					'<span class="jmrs-mgmt__leg-name">' +
					name +
					'</span>' +
					'<span class="jmrs-mgmt__leg-sub">' +
					nowN +
					' here now</span></button>'
				);
			})
			.join('');

		svg.querySelectorAll('.jmrs-mgmt-seg').forEach(bindStageGo);
		legend.querySelectorAll('.jmrs-mgmt__leg').forEach(bindStageGo);
	}

	renderFunnel();
	selectTab('pipeline');
})();
