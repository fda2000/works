<template>
	<div :class="{ wait: wait }">
		<h1>Аналитика категорий</h1>

		<help-block>
			<p>Считается выручка за выбранный период с разбивкой по разделам товаров.</p>
			<p>Отчет считается по атомам. Дата берется по дате события в истории атома - реализации или возврата.</p>
			<p>Возвраты считаются по суммам, начисленным клиентам в отчетном периоде.</p>
			<p>Услуги считаются по заказам реализации в истории атомов. Они считаются по движениям денег за указанный
				период:</p>
			<ul>
				<li>Движения по основному ЛС, привязанные к заказам.
					Доля каждого раздела определяется долей количества позиций в заказах в штуках.
				</li>
				<li>Движения по резервному ЛС, привязанные к заказам.
					Доля каждого раздела определяется долей количества позиций в заказах в штуках.
				</li>
				<li>Движения по основному ЛС, привязанные к отгрузке FBS.
					Доля каждого раздела определяется долей количества позиций в заказах (всех, привязанных к отгрузке FBS) в
					штуках.
				</li>
			</ul>
			<p>Формулы конкретных столбцов указаны во всплывающих подсказках к заголовкам столбцов.</p>
		</help-block>

		<by-types-date-selector :types="['m', 'Y', 'p']" v-model="dates"/>

		<button @click.prevent="handleCsv" class="csv">Скачать CSV</button>

		<p>
			Раздел:
			<v-select
				v-if="catalogues"
				:options="catalogues"
				v-model="catalogue"
				label="name"
				:reduce="item => item.id"
				class="catalogue"
			/>
		</p>

		<button
			v-if="items && catalogues && dates.dateType !== 'p'"
			@click.prevent="clickChart"
		>
			Показать динамику
		</button>

		<apexchart
			v-if="chartDate"
			ref="chartSell"
			:options="Object.assign({title: {text: this.fields.find(item => item.key === 'sell').label}}, chartOptions)"
			:series="chartDataSellFormatted"
			:height="chartOptions['height']"
		/>
		<apexchart
			v-if="chartDate"
			ref="chartReturns"
			:options="Object.assign({title: {text: this.fields.find(item => item.key === 'returns').label}}, chartOptions)"
			:series="chartDataReturnsFormatted"
			:height="chartOptions['height']"
		/>

		<b-table
			v-if="items"
			:items="items"
			:fields="filteredFields"
			:tbody-tr-class="(item) => !item['catalogueId'] && 'all'"
			class="blue-table"
		/>
	</div>
</template>

<style scoped>
.catalogue {
	width: 400px;
}

.blue-table {
	width: 100%;
}

.blue-table :global(.all) {
	font-weight: bold;
}

.csv {
	float: right;
}
</style>

<script>
import "vue-select/dist/vue-select.css";
import vSelect from "vue-select";

import ApiClient from "../../../shared/apiClient"
import HelpBlock from "./helpBlock.vue";
import LineDateSelector from "../lineDateSelector.vue";
import ByTypesDateSelector from "../byTypesDateSelector.vue";
import Format from "../../../shared/format";
import defaultChartOptions from "./defaultChartOptions"

export default {
	components: {ByTypesDateSelector, LineDateSelector, HelpBlock, vSelect},

	data: function () {
		return {
			items: null,
			dates: {
				dateFrom: new Date(),
				dateTo: new Date(),
				dateType: null,
				fullUrl: {}
			},
			catalogues: [],
			formatter: new Format(),
			chartDate: null,
			chartDataSell: {},
			chartDataReturns: {},
			wait: false
		}
	},

	computed: {
		fields: function () {
			return [
				{
					key: 'catalogueId',
					label: 'Раздел',
					formatter: this.formatName
				},
				{
					key: 'sell',
					label: 'Продажа',
					headerTitle: 'Мы продали на X ₽',
					formatter: this.formatter.formatMoney
				},
				{
					key: 'sellCount',
					label: 'Продажа, шт.',
					headerTitle: 'Мы продали X штук товара',
					formatter: this.formatter.formatInt
				},
				{
					key: 'ratio',
					label: 'Доля',
					headerTitle: 'Продажа / Продажа за Все разделы',
					formatter: this.formatter.formatPercent
				},
				{
					key: 'profitPercent',
					label: 'Рентабельность товарная',
					headerTitle: 'Маржа товарная / Продажа * 100%',
					formatter: this.formatter.formatPercent
				},
				{
					key: 'services',
					label: 'Услуги',
					headerTitle: 'Нам заплатили за услуги',
					formatter: this.formatter.formatMoney
				},
				{
					key: 'margin',
					label: 'Маржа',
					headerTitle: 'Общая = Маржа товарная + Услуги',
					formatter: this.formatter.formatMoney
				},
				{
					key: 'returns',
					label: 'Возвраты',
					headerTitle: 'Сумма, которую мы вернули нашим клиентам',
					formatter: this.formatter.formatMoney
				},
				{
					key: 'returnsPercent',
					label: 'Процент возврата',
					headerTitle: 'Возвраты / Продажа * 100%',
					formatter: this.formatter.formatPercent
				}
			]
		},

		filteredFields: function () {
			return this.fields.map((field) => Object.assign(field, {
				sortable: true,
				class: field.formatter === this.formatName ? 'text-left' : 'text-right'
			}))
		},

		catalogue: {
			get() {
				return this.catalogues.find((item) => item.id === parseInt(this.$route.query.catalogue))
			},
			set(catalogue) {
				const query = {...this.$route.query, catalogue: catalogue}
				!catalogue && (delete query.catalogue)

				this.$router.push({query: query})
				this.getList()
			}
		},

		chartOptions: function () {
			return Object.assign({}, defaultChartOptions, {
				xaxis: {
					type: 'datetime'
				},
				yaxis: {
					labels: {
						formatter: this.formatter.formatMoney
					}
				},
				dataLabels: {
					enabled: true,
					formatter: this.formatter.formatMoney
				},
				colors: this.chartColors
			})
		},

		chartColors: function () {
			// Выбираем максимально различные цвета для данных, скоммунизжено из инета
			const colorsNum = Math.max(
				Object.keys(this.chartDataSell).length,
				Object.keys(this.chartDataReturns).length,
			)
			const colors = []
			for (let colorNum = 0; colorNum < colorsNum; colorNum++) {
				const current = colorNum * (360 / colorsNum) % 360
				colors.push('hsl(' + current + ', 100%, 50%)')
			}
			return colors;
		},

		chartDataSellFormatted: function () {
			return this.formatSeries(this.chartDataSell)
		},
		chartDataReturnsFormatted: function () {
			return this.formatSeries(this.chartDataReturns)
		}
	},

	watch: {
		'dates.fullUrl': {
			handler: function () {
				this.getList()
			}
		},
		'$route.query.catalogue': {
			handler: function () {
				this.getList()
			}
		}
	},

	mounted() {
		this.getList()
	},

	methods: {
		formatSeries(data) {
			const series = []
			for (const catalogueId in data) {
				const items = data[catalogueId]
				if (items.length) {
					const name = this.formatName(parseInt(catalogueId)).replace(/\u00A0/g, ' ')
					series.push({
						name: name,
						data: items
					})
				}
			}

			return series
		},

		clickChart() {
			if (this.chartDate) {
				this.chartDate = null
			} else {
				this.chartDate = new Date(this.dates.dateTo)
				this.chartDataSell = {}
				this.chartDataReturns = {}
				this.getChart(this.items)
			}
		},

		async getChart(items) {
			if (!this.chartDate) {
				return
			}

			const date = this.chartDate.dateToString()
			const chartDataSell = Object.assign({}, this.chartDataSell)
			const chartDataReturns = Object.assign({}, this.chartDataReturns)

			items.map(item => {
				const array1 = chartDataSell[item['catalogueId']] || []
				array1.unshift({x: date, y: item['sell']})
				chartDataSell[item['catalogueId']] = array1

				const array2 = chartDataReturns[item['catalogueId']] || []
				array2.unshift({x: date, y: item['returns']})
				chartDataReturns[item['catalogueId']] = array2
			})
			this.chartDataSell = chartDataSell
			this.chartDataReturns = chartDataReturns

			const start = new Date(date).getTime()
			const end = new Date(this.dates.fullUrl.dateTo).getTime()
			this.$refs.chartSell && this.$refs.chartSell.zoomX(start, end)
			this.$refs.chartReturns && this.$refs.chartReturns.zoomX(start, end)

			let from = null
			if (this.dates.dateType === 'm') {
				const month = this.chartDate.getMonth() - 1
				if (month < 0) {
					return;
				}

				this.chartDate.setDate(1)
				this.chartDate.setMonth(month)
				from = new Date(this.chartDate)
				this.chartDate.setDate(this.chartDate.getLastDay())
			} else {
				const year = this.chartDate.getFullYear() - 1
				if (year < 2019) {
					return;
				}

				this.chartDate.setDate(1)
				this.chartDate.setMonth(0)
				this.chartDate.setFullYear(year)
				from = new Date(this.chartDate)
				this.chartDate.setDate(this.chartDate.getLastDay())
				this.chartDate.setMonth(11)
			}

			const url = Object.assign({}, this.$route.query, this.dates.fullUrl, {
				dateFrom: from.dateToString(),
				dateTo: this.chartDate.dateToString(),
			})

			this.wait = true
			const response = await new ApiClient(this).send('?action=list', url)
			this.wait = false
			await this.getChart(response.data.items)
		},

		formatName(id) {
			const find = this.catalogues.find(item => item.id === id)
			return find && find.name || 'Все'
		},

		handleCsv: async function () {
			const url = Object.assign({}, this.$route.query, this.dates.fullUrl)
			new ApiClient(this).download('?action=csv', url)
		},

		async getList() {
			this.chartDate = null
			if (this.wait) {
				return
			}

			const url = Object.assign({}, this.$route.query, this.dates.fullUrl)
			this.wait = true
			const response = await new ApiClient(this).send('?action=list', url)
			this.wait = false

			this.items = response.data.items
			this.catalogues = response.data.catalogues.map((item) => Object.assign(item,
				//неразрывный пробел UTF
				{name: item.name.replace(/ /g, '\u00A0')}
			))
		}
	}
}
</script>
