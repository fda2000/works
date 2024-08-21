<template>
	<div class="line-data-selector">
		<line-selector
			v-for="pattern in patterns"
			:key="pattern.key"
			:label="pattern.label"
			:list="pattern.getList(pattern)"
			:activeList="pattern.getList(pattern).map(item => item.id)"
			v-model="date[pattern.key]"
			@change="change"
		/>
	</div>
</template>
<script>
import LineSelector from "./lineSelector.vue";

export default {
	components: {LineSelector},
	props: ['dateStart', 'dateEnd', 'format', 'value'],
	data: function () {
		return {
			patternsConst: [
				{
					key: 'Y',
					label: 'Год',
					getDate: this.getFullYear,
					setDate: this.setFullYear,
					getList: this.getYearList
				},
				{
					key: 'q',
					label: 'Квартал',
					getDate: this.getQuarter,
					setDate: this.setQuarter,
					getList: this.getQuarterList
				},
				{
					key: 'm',
					label: 'Месяц',
					getDate: this.getMonth,
					setDate: this.setMonth,
					getList: this.getMonthList
				},
				{
					key: 'd',
					label: 'День',
					getDate: this.getDay,
					setDate: this.setDay,
					getList: this.getDayList
				}
			],

			date: {
				Y: null,
				m: null,
				d: null
			}
		}
	},

	created() {
		this.onValueChange()
	},

	watch: {
		value: {
			handler() {
				this.onValueChange()
			}
		},

		format: {
			handler() {
				this.onValueChange()
			}
		}
	},

	computed: {
		patterns: function () {
			return this.format.split('').map(alpha => this.getPattern(alpha)).filter(item => !!item)
		}
	},
	methods: {
		onValueChange() {
			this.patterns.map(pattern => this.date[pattern.key] = this.value instanceof Date ? pattern.getDate(this.value) : null)
		},
		change() {
			let changed = true;
			if (this.value instanceof Date) {
				changed = !!this.patterns.find(pattern => this.date[pattern.key] !== pattern.getDate(this.value))
			}

			if (changed) {
				const date = this.value ? new Date(this.value) : new Date()
				const ids = this.patterns.map(pattern => pattern.key)
				if (this.date.d) {
					date.setDate(1)
				}

				this.patternsConst.map(pattern => {
						if (ids.indexOf(pattern.key) !== -1 && this.date[pattern.key] !== null) {
							let value = this.date[pattern.key]
							if (pattern.key === 'd') {
								value = Math.min(value, this.getLastDay(date))
							}
							pattern.setDate(date, value)
						}
					}
				)

				this.$emit('input', date)
				this.$emit('change', this.date)
			}
		},
		getPattern(alpha) {
			return this.patternsConst.find(item => item.key === alpha)
		},
		getLastDay(date) {
			const endDate = new Date(date)
			endDate.setDate(1)
			endDate.setMonth(endDate.getMonth() + 1)
			endDate.setDate(0)
			return endDate.getDate()
		},

		getFullYear(date) {
			return date.getFullYear();
		},
		getQuarter(date) {
			return Math.ceil(date.getMonth() / 3) + 1;
		},
		getMonth(date) {
			return date.getMonth();
		},
		getDay(date) {
			return date.getDate();
		},
		setFullYear(date, val) {
			date.setFullYear(val);
		},
		setQuarter(date, val) {
			date.setMonth( (val - 1) * 3 )
		},
		setMonth(date, val) {
			const testDate = new Date(date)
			testDate.setDate(1)
			testDate.setMonth(val)
			const last = testDate.getLastDay()

			date.setDate(Math.min(date.getDate(), last))
			date.setMonth(val)
		},
		setDay(date, val) {
			date.setDate(val);
		},
		getYearList(pattern) {
			const yearList = []
			for (let year = pattern.getDate(this.dateStart); year <= pattern.getDate(this.dateEnd); year++) {
				yearList.push({
					id: year,
					name: year
				})
			}
			return yearList
		},

		getQuarterList(pattern) {
			const dateMonth = new Date(this.value)
			dateMonth.setDate(1)
			const quarterList = []

			for (let quarter = 1; quarter <= 4; quarter++) {
				pattern.setDate(dateMonth, quarter)
				const month = dateMonth.getMonth()

				if (
					(dateMonth.getFullYear() > this.dateStart.getFullYear() || month >= this.dateStart.getMonth())
					&&
					(dateMonth.getFullYear() < this.dateEnd.getFullYear() || month <= this.dateEnd.getMonth())
				) {
					quarterList.push({
						id: quarter,
						name: 'Q' + quarter
					})
				}
			}

			return quarterList
		},

		getMonthList(pattern) {
			const dateMonth = new Date(this.value)
			dateMonth.setDate(1)
			const monthList = []
			for (let month = 0; month <= 11; month++) {
				pattern.setDate(dateMonth, month)
				if (
					(dateMonth.getFullYear() > this.dateStart.getFullYear() || month >= this.dateStart.getMonth())
					&&
					(dateMonth.getFullYear() < this.dateEnd.getFullYear() || month <= this.dateEnd.getMonth())
				) {
					monthList.push({
						id: month,
						name: dateMonth.toLocaleString('ru', {month: 'long'})
					})
				}
			}
			return monthList
		},

		getDayList(pattern) {
			const dateDay = new Date(this.value)
			const endDate = this.getLastDay(dateDay)
			const dayList = []
			for (let day = 1; day <= endDate; day++) {
				dateDay.setDate(day)
				if (dateDay >= this.dateStart && dateDay <= this.dateEnd) {
					dayList.push({
						id: day,
						name: day
					})
				}
			}
			return dayList
		}
	}
}
</script>

<style scoped>
</style>
