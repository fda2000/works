const ru = require("apexcharts/dist/locales/ru.json")

const defaultChartOptions = {
	chart: {
		height: 500,
		type: 'line',
		dropShadow: {
			enabled: true,
			color: '#000',
			top: 18,
			left: 7,
			blur: 10,
			opacity: 0.2
		},
		toolbar: {
			show: true
		},
		defaultLocale: 'ru',
		locales: [ru]
	},
	dataLabels: {
		enabled: true
	},
	stroke: {
		curve: 'smooth'
	},
	grid: {
		borderColor: "#e7e7e7",
		row: {
			colors: [
				'#f3f3f3',
				'transparent'
			],
			opacity: 0.5
		}
	},
	markers: {
		size: 1
	},
	legend: {
		floating: true,
		horizontalAlign: "right",
		offsetX: -5,
		offsetY: -25,
		position: "top"
	}
}

module.exports = defaultChartOptions;
