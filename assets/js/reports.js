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

	function chartHasRenderableData(chartConfig) {
		if (!chartConfig) {
			return false;
		}

		if (Array.isArray(chartConfig.series) && chartConfig.series.length > 0) {
			if (Array.isArray(chartConfig.labels) && chartConfig.labels.length > 0) {
				return true;
			}
			return chartConfig.series.some(function (series) {
				return hasValues(series && series.values);
			});
		}

		return hasValues(chartConfig.values);
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

	function buildDatasets(chartConfig, type) {
		var isDoughnut = type === 'doughnut' || type === 'pie';
		var series = Array.isArray(chartConfig.series) ? chartConfig.series : null;

		if (series && series.length > 0 && !isDoughnut) {
			var seriesColors = palette(Math.max(series.length, 2));
			return series.map(function (item, index) {
				return {
					label: (item && item.label) || '',
					data: Array.isArray(item && item.values) ? item.values : [],
					backgroundColor: seriesColors[index % seriesColors.length],
					borderColor: seriesColors[index % seriesColors.length],
					borderWidth: 1
				};
			});
		}

		var values = Array.isArray(chartConfig.values) ? chartConfig.values : [];
		var colors = palette(values.length);

		return [
			{
				label: chartConfig.title || '',
				data: values,
				backgroundColor: isDoughnut ? colors : colors[0],
				borderColor: isDoughnut ? '#ffffff' : colors[0],
				borderWidth: isDoughnut ? 2 : 1,
				tension: type === 'line' ? 0.25 : 0,
				fill: type === 'line'
			}
		];
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
			var emptyEl = document.getElementById('jmrs-chart-empty-' + chartConfig.id);
			var type = chartConfig.type || 'bar';
			var indexAxis = chartConfig.indexAxis || 'x';
			var isDoughnut = type === 'doughnut' || type === 'pie';
			var isGrouped = Array.isArray(chartConfig.series) && chartConfig.series.length > 0 && !isDoughnut;

			if (!chartHasRenderableData(chartConfig)) {
				canvas.classList.add('jmrs-chart-hidden');
				if (emptyEl) {
					emptyEl.hidden = false;
				}
				return;
			}

			if (emptyEl) {
				emptyEl.hidden = true;
			}

			new Chart(canvas.getContext('2d'), {
				type: type,
				data: {
					labels: labels,
					datasets: buildDatasets(chartConfig, type)
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					indexAxis: indexAxis,
					plugins: {
						legend: {
							display: isDoughnut || isGrouped,
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
									stacked: false,
									ticks: {
										precision: 0
									}
								},
								y: {
									beginAtZero: true,
									stacked: false,
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
