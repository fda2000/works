import styled from 'styled-components'
import React, { useRef, useState } from 'react'
import { nameState } from '../../helpers/nameState'
import { useDispatch } from 'react-redux'
import { useTypedSelector } from '../../hooks/useTypedSelector'

const SelectEl = styled.select`
	display: none !important;
`

const DropDownContainer = styled.div`
	width: 100%;
	position: relative;
`

const DropDownHeader = styled.div`
  display: flex;
  justify-content: left;
  align-items: center;
  width: 100%;
  padding: 0 40px 0 10px;
  border: 2px solid ${({theme}) => theme.colors.lightGray};
  border-radius: ${({theme}) => theme.radius};
  height: 40px;
  background: ${({theme}) => theme.colors.background};

  text-overflow: ellipsis;
  overflow: hidden;

  &:after {
    content: '〉';
    position: absolute;
    transition: all 0.25s;
    color: ${({theme}) => theme.colors.lightGray};
    transform: rotate(90deg) scaleX(1.7);
    margin-top: 15px;
    right: 15px;
    z-index: 10;
  }

  &.open:after {
    color: ${({theme}) => theme.colors.black};
    transform: rotate(0deg) scaleX(1.7);
    margin-top: 0;
    right: 9px;
  }
`

const DropDownListContainer = styled.div`
	position: absolute;
	z-index: 100;
`

const DropDownList = styled.ul`
	${({ theme }) => theme.editField}
	height: auto;
	margin: -2px 0 0 0;
	&:first-child {
		padding-top: 0.8em;
	}
`

const ListItem = styled.li`
	list-style: none;
	margin: 10px 0;
	cursor: pointer;
	color: ${({ theme }) => theme.colors.grayText};
	&:hover {
		color: ${({ theme }) => theme.colors.black};
	}
`

const Select = ({ values, name = null, setValue = null, ...more }) => {
	const data = setValue instanceof nameState ? setValue : new nameState(setValue)
	const dataValue = data.getState(name)
	const header = useRef(null)
	const drop = useRef(null)

	const dispatch = useDispatch()
	const [uuid] = useState(Math.random())
	const selected = useTypedSelector((state) => state.select);
	const isOpen = uuid === selected

	const toggling = () => {
		dispatch({type: 'SET_SELECT', value: isOpen? null : uuid})
		drop.current.style.width = header.current.offsetWidth + 'px'
	}

	const select = (id) => {
		dispatch({type: 'SET_SELECT', value: null})
		data.setState(name, id)
	}

	const getCurrentIdentify = (el) => {
		if (el.id !== undefined) {
			return el.id
		}
		return el.key
	}

	const valuesArr = Array.isArray(values) ? values : []
	return (
		<>
			<DropDownContainer {...more}>
				<DropDownHeader
					className={isOpen ? 'open' : ''}
					ref={header}
					onClick={toggling}
				>{(valuesArr.find(element => getCurrentIdentify(element) === dataValue)?.label ?? valuesArr.find(element => getCurrentIdentify(element) === dataValue)?.name)}</DropDownHeader>
				<DropDownListContainer ref={drop}>
					{isOpen && (
						<DropDownList>
							{valuesArr.map((item) => (
								<ListItem key={name + 'list' + getCurrentIdentify(item)} onClick={() => select(getCurrentIdentify(item))}>{(item.label ?? item.name)}</ListItem>
							))}
						</DropDownList>
					)}
				</DropDownListContainer>
			</DropDownContainer>

			<SelectEl onChange={event => select(event.target.value)} value={dataValue ?? ''} {...more}>
				{valuesArr.map((item) => (
					<option key={name + 'option' + getCurrentIdentify(item)} value={getCurrentIdentify(item)}>{(item.label ?? item.name)}</option>
				))}
			</SelectEl>
		</>
	)
}

export default Select
