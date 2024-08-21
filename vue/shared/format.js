function Format() {
	this.formatMoney = (value) => Number.isNaN(parseFloat(value)) ? '' :
		parseFloat(value).toLocaleString(undefined, {style: 'currency', currency: 'RUB'})

	this.formatMoneyRubOnly = (value) => Number.isNaN(parseFloat(value)) ? '' :
		parseFloat(value).toLocaleString(undefined, {style: 'currency', currency: 'RUB', maximumFractionDigits: 0})

	this.formatInt = (value) => Number.isNaN(parseInt(value)) ? '' :
		parseInt(value).toLocaleString(undefined, {})

	this.formatFloat = (value) => Number.isNaN(parseFloat(value)) ? '' :
		parseFloat(value).toLocaleString(undefined, {maximumFractionDigits: 2, minimumFractionDigits: 2})

	this.formatPercent = (value) => this.formatFloat(value) === '' ? '' : this.formatFloat(value) + '%'
}

module.exports = Format;
