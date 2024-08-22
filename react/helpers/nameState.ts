import React from 'react'
import queryString from 'query-string'
import _ from 'lodash'

export class nameState {
	private state
	private setter

	constructor(state: [any, React.Dispatch<React.SetStateAction<any>>] | null) {
		[this.state, this.setter] = state ?? ['', null]
	}

	private getKeyFromMultiName = (name) => name && name.substr(name.length - 2, 2) === '[]' ? name.substr(0, name.length - 2) : null

	public setState(name: string | null, value) {
		if (name && this.setter) {
			const key = this.getKeyFromMultiName(name)
			const names = this.splitNames(key ?? name)
			const add = this.setFromArray(names, value)

			const update = name && _.merge(Object.assign({}, this.state), add) || value
			this.setter(update)
		}
	}

	private splitNames(name) {
		const ret = []
		const matches = name.match(/\[([^\[]+)]/g)
		if (!matches?.length) {
			ret.push(name)
		} else {
			const first = name.substr(0, name.indexOf(matches[0]))
			ret.push(first)
			matches.map(key => ret.push(key.substr(1, key.length - 2)))
		}
		return ret
	}

	private setFromArray(names, value) {
		const ret = {}
		const name = names.shift()
		if (names.length) {
			ret[name] = this.setFromArray(names, value)
		} else {
			ret[name] = value
		}

		return ret
	}

	public getState(name = null) {
		if (name && this.state) {
			const key = this.getKeyFromMultiName(name)
			return key && (this.getFromKeyString(key) ?? []) || this.getFromKeyString(name)
		}
		return this.state
	}

	private getFromKeyString(name) {
		return this.splitNames(name).reduce(
			(previousValue, currentValue) => previousValue ? previousValue[currentValue] : previousValue,
			this.state,
		)
	}

	public toQueryString() {
		return queryString.stringify(this.state, { skipNull: true, skipEmptyString: true, arrayFormat: 'comma' })
	}
}
