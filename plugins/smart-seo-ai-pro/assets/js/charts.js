/**
 * Smart SEO AI Suite Pro - Charting Utilities
 */
window.SmartSeoCharts = {
	renderBarChart: function(containerId, data) {
		const container = document.getElementById(containerId);
		if (!container) return;

		const bars = container.querySelectorAll('.bar-fill');
		if (bars.length >= 4) {
			bars[0].style.height = (data.seo || 0) + '%';
			bars[0].querySelector('span').textContent = (data.seo || 0) + '%';

			bars[1].style.height = (data.security || 0) + '%';
			bars[1].querySelector('span').textContent = (data.security || 0) + '%';

			bars[2].style.height = (data.perf || 0) + '%';
			bars[2].querySelector('span').textContent = (data.perf || 0) + '%';

			bars[3].style.height = (data.woo || 0) + '%';
			bars[3].querySelector('span').textContent = (data.woo || 0) + '%';
		}
	}
};
