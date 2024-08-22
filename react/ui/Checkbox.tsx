import styled from 'styled-components'
import IconCheck from '../../public/images/icons/check.svg'
import { lineClamp } from '../../styles/mixins'
import { nameState } from '../../helpers/nameState'

const CheckboxText = styled.span`
	display: inline-block;
	width: calc(100% - 24px - 16px);
	max-height: 56px;
	font-size: 18px;
	line-height: 28px;
	transition: color 0.25s;
	padding-right: 15px;
	hyphens: auto;
	cursor: pointer;
	${lineClamp(2, '56px')};

	@media ${({ theme }) => theme.media.desktop} {
		&:hover,
		&:focus {
			color: ${({ theme }) => theme.colors.black};
		}
	}
`

const CheckboxIcon = styled.i`
	display: flex;
	justify-content: center;
	align-items: center;
	width: 24px;
	height: 24px;
	border: 2px solid ${({ theme }) => theme.colors.lightGray};
	border-radius: ${({ theme }) => theme.radius};
	color: ${({ theme }) => theme.colors.lightGray};
	margin-right: 16px;
	margin-top: 2px;
	transition: border-color 0.25s;
	cursor: pointer;

	svg {
		opacity: 0;
		transition: opacity 0.25s;
	}

	@media ${({ theme }) => theme.media.desktop} {
		&:hover {
			border-color: ${({ theme }) => theme.colors.black};
			color: ${({ theme }) => theme.colors.black};
		}
	}
`

const CheckboxEl = styled.input.attrs(({ value, name }) => ({
	type: 'checkbox',
	value: value,
	name,
}))`
	display: none;

	&:checked {
		+ ${CheckboxIcon} {
			border-color: ${({ theme }) => theme.colors.black};
			color: ${({ theme }) => theme.colors.black};
			transition: border-color 0.25s;

			svg {
			  opacity: 1;
			  transition: opacity 0.25s;
			}

		+ ${CheckboxText} {
			color: ${({ theme }) => theme.colors.black};
			transition: color 0.25s;
		}
	 }
	}

	&:disabled {
		+ ${CheckboxIcon} {
			border-color: ${({ theme }) => theme.colors.lightGray};
			opacity: 0.5;
			cursor: not-allowed;

		+ ${CheckboxText} {
			color: ${({ theme }) => theme.colors.lightGray};
			opacity: 0.7;
			cursor: not-allowed;
		}
	 }

	 &:checked {
		+ ${CheckboxIcon} {
			svg {
				opacity: 1;
				transition: opacity 0.25s;
			}
		}
	 }
	}
`

export const StyledCheckbox = styled.label`
	display: flex;
	align-items: flex-start;
	flex-flow: row nowrap;
	color: ${({ theme }) => theme.colors.grayText};
`

const Checkbox = (params) => {
	let {
		val,
		text,
		name = null,
		checked = false,
		setValue = null,
		setCheckbox = null,
		removeCheckbox = null,
		filterType = null,

	} = params
	const data = setValue instanceof nameState ? setValue : new nameState(setValue)

	if (setValue) {
		const state = data.getState(name)
		checked = Array.isArray(state) ? state.indexOf(val) > -1 : !!state
	}

	const handlerChange = ({ event, val, onChange, name, data }) => {
		const isChecked = event.target.checked
		if (isChecked) {
			setCheckbox && setCheckbox(val, filterType)
		} else {
			removeCheckbox && removeCheckbox(val, filterType)
		}

		!!onChange && onChange({
			target:
				{
					name: event.target.name,
					value: isChecked ? val : null,
				},
		})


		let update = data.getState(name)
		if (Array.isArray(update)) {
			isChecked && update.indexOf(val) < 0 && update.push(val)
			!isChecked && update.indexOf(val) >= 0 && (update = update.filter(el => el !== val))
		} else {
			update = isChecked && val || update && delete update[val]
		}
		data.setState(name, update)
	}

	return (
		<StyledCheckbox>
			<CheckboxEl value={params.val} {...params} checked={checked} onChange={(event) => handlerChange({ ...params, event: event, data: data })} />
			<CheckboxIcon>
				<IconCheck />
			</CheckboxIcon>
			<CheckboxText>{text}</CheckboxText>
		</StyledCheckbox>
	)
}

export default Checkbox
