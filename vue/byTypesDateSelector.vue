<template>
	<div>
		<p>Период:
			<template v-for="type in types">
				<template v-if="currentType === type">{{ dateNames[type] }}</template>
				<router-link
					v-else
					:to="{query: {...$route.query, dateType: type}}"
				>
					{{ dateNames[type] }}
				</router-link>
				|
			</template>
		</p>

		<line-date-selector
			v-if="dateFormat"
			:format="dateFormat"
			v-model="dateToObject"
			:date-start="new Date('2019-01-01')"
			:date-end="new Date()"
		/>

		<template v-else>
			Период: c <input type="date" v-model="dateFrom"> по <input type="date" v-model="dateTo">
		</template>
	</div>
</template>
<script>
import LineDateSelector from "./lineDateSelector.vue";

export default {
	components: {LineDateSelector},
	props: ['types', 'value'],
	data: function () {
		return {
			dateFrom: this.$route.query.dateFrom,
			dateTo: this.$route.query.dateTo,
			dateNames: {
				Y: 'За год',
				m: 'За месяц',
				d: 'За день',
				p: 'За период'
			},
			dateFormats: {
				Y: 'Y',
				m: 'Ym',
				d: 'Ymd',
				p: ''
			}
		}
	},

	created() {
		this.loadRoute()
	},

	watch: {
		'$route.query.dateType': function () {
			this.loadRoute()
		},
		'$route.query.dateTo': function () {
			this.loadRoute()
		},
		'$route.query.dateFrom': function () {
			this.loadRoute()
		},

		value: {
			handler: function (val) {
				this.dateTo = val.dateTo.dateToString()
				this.dateFrom = val.dateFrom.dateToString()
				this.routePush()
			},
			deep: true
		},

		dateTo: {
			handler: function (val) {
				if (this.stringValue.dateTo !== val) {
					this.value.dateTo = new Date(val)
					this.emit()
				}
			}
		},

		dateFrom: {
			handler: function (val) {
				if (this.stringValue.dateFrom !== val) {
					this.value.dateFrom = new Date(val)
					this.emit()
				}
			}
		}
	},

	computed: {
		dateFormat: function () {
			return this.dateFormats[this.currentType]
		},
		currentType: function () {
			return this.value.dateType
		},

		default: function () {
			const now = this.getEndPeriod(new Date()) || new Date()
			const begin = this.getBeginPeriod(now) || now

			return {
				dateType: this.types[0],
				dateFrom: begin.dateToString(),
				dateTo: now.dateToString()
			}
		},

		stringValue: function () {
			return {
				dateType: this.value.dateType,
				dateFrom: this.value.dateFrom.dateToString(),
				dateTo: this.value.dateTo.dateToString()
			}
		},

		dateToObject: {
			get() {
				return this.value.dateTo
			},
			set(to) {
				this.value.dateTo = this.getEndPeriod(to) || to
				this.value.dateFrom = this.getBeginPeriod(this.value.dateTo) || this.value.dateFrom
			}
		}
	},

	methods: {
		getBeginPeriod(date) {
			const begin = new Date(date)
			if (this.currentType === 'Y') {
				begin.setMonth(0)
				begin.setDate(1)
			} else if (this.currentType === 'm') {
				begin.setDate(1)
			} else {
				return null
			}

			return begin
		},

		getEndPeriod(date) {
			const end = new Date(date)
			if (this.currentType === 'Y') {
				end.setMonth(11)
				end.setDate(end.getLastDay())
			} else if (this.currentType === 'm') {
				end.setDate(end.getLastDay())
			} else {
				return null
			}

			const now = new Date()
			return end > now ? now : end
		},

		loadRoute() {
			this.value.dateType = this.$route.query.dateType || this.default.dateType
			this.value.dateTo = this.$route.query.dateTo && new Date(this.$route.query.dateTo) || this.default.dateTo
			this.value.dateTo = this.getEndPeriod(this.value.dateTo) || this.value.dateTo
			this.value.dateFrom = this.$route.query.dateFrom && new Date(this.$route.query.dateFrom) || this.default.dateFrom
			this.value.dateFrom = this.getBeginPeriod(this.value.dateTo) || this.value.dateFrom
			this.emit()
		},

		emit() {
			const data = Object.assign({}, this.value, {fullUrl: this.stringValue})
			if (JSON.stringify(data) !== JSON.stringify(this.value)) {
				this.$emit('input', data)
			}
		},

		routePush() {
			const query = Object.assign({}, this.$route.query, this.stringValue)
			if (query.dateFrom === this.default.dateFrom) {
				delete query.dateFrom
			}
			if (query.dateTo === this.default.dateTo) {
				delete query.dateTo
			}
			if (query.dateType === this.default.dateType) {
				delete query.dateType
			}

			if (JSON.stringify(query) !== JSON.stringify(this.$route.query)) {
				this.$router.push({query: query})
			}
		}
	}
}
</script>
