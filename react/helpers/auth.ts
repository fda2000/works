import { GetServerSidePropsResult } from 'next'
import { QueryClient } from 'react-query'
import pickBy from 'lodash/pickBy'
import isEmpty from 'lodash/isEmpty'
import queryString from 'query-string'
import { ParsedUrlQuery } from 'querystring'

const queryClient = new QueryClient()
const { API_URL } = process.env

interface ApiItem {
	url: string
	addSession?: boolean
	needSession?: boolean
	noCache?: boolean
}

class ApiQueryItem {
	//миллисекунды
	/** минута */
	private static SHORT_CACHE = 60000
	/** день */
	private static LONG_CACHE = 86400000

	private static queries: { [key: string]: ApiItem } = {
		getOrdersFilters: {
			url: 'personal/getOrdersFilters?',
			needSession: true,
		},
		getOrdersHistory: {
			url: 'personal/getOrdersHistory?',
			needSession: true,
		},
		getOrderInfo: {
			url: 'personal/getOrderInfo?',
			needSession: true,
		}
	}

	private apiItem: ApiItem
	private readonly params: string = ''
	private readonly formData: FormData = null

	constructor(name: string, params: string, formData: FormData | { [key: string]: any } = null) {
		this.apiItem = ApiQueryItem.queries[name]
		this.params = decodeURI(params)

		if (formData) {
			if (formData instanceof FormData) {
				this.formData = formData
			} else {
				this.formData = new FormData()
				this.appendFormData(formData)
			}
		}

		return this
	}

	private getUrl(): string {
		return this.apiItem.url + this.params
	}

	private getStale(token: string): number {
		if (this.apiItem?.noCache) {
			return 0
		}
		if (token && (this.apiItem.addSession || this.apiItem.needSession)) {
			return ApiQueryItem.SHORT_CACHE
		}
		if (this.params.indexOf('&filter[') > -1) {
			return ApiQueryItem.SHORT_CACHE
		}

		return ApiQueryItem.LONG_CACHE
	}

	async fetchQuery(token: string) {
		if (this.apiItem?.needSession && !token) {
			return false
		}
		const url = this.getUrl()
		const stale = this.getStale(token)
		let init = null
		if (token) {
			init = {
				headers: {
					'session-token': token,
				},
			}
		} else {
			init = {}
		}

		if (this.formData) {
			init.body = this.formData
			init.method = 'POST'
		}

		return queryClient.fetchQuery(url + '|' + token, async () => {
// console.log(API_URL + url, stale, init, 'fetchQuery')
			try {
				const res = await fetch(API_URL + encodeURI(url), init)
// res.headers.get('content-type').indexOf('text/html') !==-1 && console.log(await res.text(), 'res')
				return await res.json()
			} catch (e) {
// console.log(e, 'e3333333333333333333333333333')
				return null
			}
		}, { staleTime: stale })
	}

	private appendFormData(data: { [key: string]: any }, prefix = '') {
		for (const dataKey in data) {
			if (data.hasOwnProperty(dataKey) && data[dataKey] !== null && data[dataKey] !== false) {
				let key = prefix ? prefix + '[' + dataKey + ']' : dataKey
				if (typeof data[dataKey] === 'object' && !(data[dataKey] instanceof File)) {
					this.appendFormData(data[dataKey], key)
				} else {
					this.formData.append(key, data[dataKey])
				}
			}
		}
	}
}

export class ApiQuery {
	private static DEFAULT_ON_PAGE = 30

	private readonly token: string = ''
	private queries: ApiQueryItem[] = []
	private rawData

	constructor(token: string) {
		this.token = token ? token : ''
		return this
	}

	public addQuery(key: string, name: string, params: string = '', formData: FormData | { [key: string]: any } = null) {
		this.queries[key] = new ApiQueryItem(name, params, formData)
		return this
	}

	public async runQueries(): Promise<any> {
		//запускаем параллельно
		const result = {}
		this.rawData = {}
		for (const key in this.queries) {
			result[key] = this.queries[key].fetchQuery(this.token)
		}

		for (const key in result) {
			try {
				const data = await result[key]
				this.rawData[key] = data
				if (typeof data === 'object') {
					result[key] = data ? (data.data ?? data) : null
				} else {
					result[key] = data
				}
			} catch (e) {
				result[key] = null
			}
		}

		return result
	}

	public getRawData() {
		return this.rawData
	}

	public async runQueriesProps(allQueriesNeed = false): Promise<GetServerSidePropsResult<any> | { notFound: true }> {
		const response = await this.runQueries()
		for (const key in response) {
			if (
				response[key] === false ||
				(!response[key] && allQueriesNeed)
			) {
				return {
					notFound: true,
				}
			}
		}

		return {
			props: {
				...response,
				sessionToken: this.token,
			},
		}
	}

	public static clearAllCache() {
		queryClient.clear()
	}

	public static getPageParams({/*perPage, */page = 1, sort = null }, onPage = 0) {
		onPage = onPage > 0 ? onPage : ApiQuery.DEFAULT_ON_PAGE
		return `perPage=${onPage}&page=${page > 0 ? page : 1}` + (sort ? `&sort=${sort}` : '')
	}

	public static getCatalogFilterParams(query: ParsedUrlQuery) {
		//TODO переделать, кривовато...
		const filtersQuery = pickBy(
			{ ...(query || {}) },
			(q, key) => !isEmpty(q) && ['page', 'perPage', 'slug', 'sort', 'id', 'slugCollection'].indexOf(key) === -1,
		)
		const newQuery = Object.entries(filtersQuery).map(([key, val]) => {
			const filterName = `filter[${key}]`
			// @ts-ignore
			return { [filterName]: val.split(',') }
		})

		const newQueryStringify = newQuery.map((item) => {
			return queryString.stringify(
				{
					...item,
				},
				{ arrayFormat: 'index' },
			)
		})

		return newQueryStringify.join('&')
	}

	public static getFilterParams(query, filters: string[]) {
		const ret = []
		filters.map((element) => {
			if (element.substr(element.length - 2, 2) === '[]') {
				const name = element.substr(0, element.length - 2)
				return query[name] && query[name].split(',').map((val) => ret.push(`${name}[]=${encodeURI(val)}`))
			} else {
				typeof query[element] !== 'undefined' && ret.push(`${encodeURI(element)}=${encodeURI(query[element])}`)
			}
		})

		return ret.join('&')
	}
}
