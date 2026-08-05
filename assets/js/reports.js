(function () {
	'use strict';

	function hasValues(values) {
		if (!Array.isArray(values) || values.length === 0) {
			return false;
		}
		return values.some(function (value) {
			return Number(value) > 0;
		});
	}

	function palette(count) {
		var colors = [
			'#2271b1',
			'#72aee6',
			'#135e96',
			'#00a32a',
			'#dba617',
			'#d63638',
			'#8c5e00',
			'#9b51e0',
			'#3858e9',
			'#1d2327'
		];
		var out = [];
		var i;
		for (i = 0; i < count; i += 1) {
			out.push(colors[i % colors.length]);
		}
		return out;
	}

	function initCharts(config) {
		if (!config || !Array.isArray(config.charts) || typeof Chart === 'undefined') {
			return;
		}

		config.charts.forEach(function (chartConfig) {
			if (!chartConfig || !chartConfig.id) {
				return;
			}

			var canvas = document.getElementById('jmrs-chart-' + chartConfig.id);
			if (!canvas) {
				return;
			}

			var labels = Array.isArray(chartConfig.labels) ? chartConfig.labels : [];
			var values = Array.isArray(chartConfig.values) ? chartConfig.values : [];
			var emptyEl = document.getElementById('jmrs-chart-empty-' + chartConfig.id);

			if (!hasValues(values)) {
				canvas.classList.add('jmrs-chart-hidden');
				if (emptyEl) {
					emptyEl.hidden = false;
				}
				return;
			}

			if (emptyEl) {
				emptyEl.hidden = true;
			}

			var type = chartConfig.type || 'bar';
			var indexAxis = chartConfig.indexAxis || 'x';
			var colors = palette(values.length);
			var isDoughnut = type === 'doughnut' || type === 'pie';

			new Chart(canvas.getContext('2d'), {
				type: type,
				data: {
					labels: labels,
					datasets: [
						{
							label: chartConfig.title || '',
							data: values,
							backgroundColor: isDoughnut ? colors : colors[0],
							borderColor: isDoughnut ? '#ffffff' : colors[0],
							borderWidth: isDoughnut ? 2 : 1,
							tension: type === 'line' ? 0.25 : 0,
							fill: type === 'line'
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					indexAxis: indexAxis,
					plugins: {
						legend: {
							display: isDoughnut,
							position: 'bottom'
						},
						title: {
							display: false
						}
					},
					scales: isDoughnut
						? {}
						: {
								x: {
									beginAtZero: true,
									ticks: {
										precision: 0
									}
								},
								y: {
									beginAtZero: true,
									ticks: {
										precision: 0
									}
								}
						  }
				}
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var data = window.jmrsReportsData || {};
		initCharts(data);
	});
})();
