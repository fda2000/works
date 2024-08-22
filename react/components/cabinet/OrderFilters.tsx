import styled from 'styled-components'
import 'moment/locale/ru'
import React, { useState } from 'react'
import SearchIcon from '../../public/images/icons/search.svg'
import Radio, { StyledRadio } from '../ui/Radio'
import AppButton from '../ui/AppButton'
import { useRouter } from 'next/router'
import Checkbox, { StyledCheckbox } from '../ui/Checkbox'
import queryString from 'query-string'
import { IOrderFilter } from '../../interfaces/cabinet'
import InputText, { InputTextStyled } from '../ui/InputText'
import { nameState } from '../../helpers/nameState'


const OrderFiltersStyled = styled.div`
    margin-bottom: 100px;
`
const SearchIconFilter = styled(SearchIcon)`
    position: absolute;
    margin: 18px 0 0 20px;
    filter: contrast(10%)
`
const FilterText = styled(InputText)`
	&${InputTextStyled} {
		padding-left: 60px;
	}
`
const CheckGroup = styled.div`
    margin: 20px 0;

    & ${StyledRadio}, & ${StyledCheckbox} {
        display: inline-flex;
        margin: 5px 35px 5px 0;
    }
    & ${StyledCheckbox} {
        margin-right: 13px;
    }
    & ${StyledRadio}:last-of-type, & ${StyledCheckbox}:last-of-type {
        margin-right: 0;
    }
`

const OrderFilters = (params: IOrderFilter) => {
	const { search, status } = params
	const router = useRouter()
	const { pathname, query } = router

	const filterState = new nameState(
		useState({
			filterString: query.filterString ?? '',
			filterBy: query.filterBy ?? 'order',
			filterStatus: (typeof query['filterStatus'] === 'string' ? query['filterStatus'].split(',') : query['filterStatus[]']) ?? [],
		})
	)

	const handleSearch = async () => {
		const filterValues = filterState.getState()
		const values = {
			...filterValues,
			filterBy: filterValues.filterString ? filterValues.filterBy : '',
		}
		const query = queryString.stringify(values, { skipNull: true, skipEmptyString: true, arrayFormat: 'comma' })
		const url = pathname + (query ? '?' + query : '')
		await router.push(url, url, { scroll: false })
	}

	return (
		<OrderFiltersStyled>
			<SearchIconFilter />
			<FilterText
				onKeyDown={(event) => event.key === 'Enter' && handleSearch()}
				setValue={filterState}
				type='search'
				name='filterString'
				placeholder='Поиск по заказам'
			/>

			<CheckGroup>
				{search.map((item, index) =>
					<Radio
						key={index}
						text={item.name}
						val={item.id}
						name='filterBy'
						setValue={filterState}
					/>,
				)}
			</CheckGroup>

			<CheckGroup>
				{status.map((item, index) =>
					<Checkbox
						key={index}
						text={item.name}
						val={item.id}
						name='filterStatus[]'
						setValue={filterState}
					/>,
				)}
			</CheckGroup>

			<AppButton width='200px' black onClick={handleSearch}>Найти заказы</AppButton>
		</OrderFiltersStyled>
	)
}

export default OrderFilters
