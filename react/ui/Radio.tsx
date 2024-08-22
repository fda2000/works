import IconCheck from '../../public/images/icons/check.svg'
import styled from 'styled-components'
import { nameState } from '../../helpers/nameState'

const RadioText = styled.span`
  font-size: 18px;
  line-height: 28px;
  transition: color 0.25s;
`

const RadioIcon = styled.i`
  display: flex;
  justify-content: center;
  align-items: center;
  width: 24px;
  height: 24px;
  border: 2px solid ${({ theme }) => theme.colors.lightGray};
  border-radius: 50%;
  color: ${({ theme }) => theme.colors.lightGray};
  margin-right: 16px;
  transition: border-color 0.25s;

  svg {
    opacity: 0;
    transition: opacity 0.25s;
  }
`

const RadioEl = styled.input.attrs(({ value, name }) => ({
	type: 'radio',
	value: value,
	name,
}))`
  display: none;

  &:checked {
    + ${RadioIcon} {
      border-color: ${({ theme }) => theme.colors.black};
      color: ${({ theme }) => theme.colors.black};
      transition: border-color 0.25s;

      svg {
        opacity: 1;
        transition: opacity 0.25s;
      }

      + ${RadioText} {
        color: ${({ theme }) => theme.colors.black};
        transition: color 0.25s;
      }
    }
  }
`

export const StyledRadio = styled.label`
  display: flex;
  align-items: center;
  flex-flow: row nowrap;
  cursor: pointer;
  color: ${({ theme }) => theme.colors.grayText};

  @media ${({ theme }) => theme.media.desktop} {
    &:hover,
    &:focus {
      color: ${({ theme }) => theme.colors.black};

      ${RadioIcon} {
        border-color: ${({ theme }) => theme.colors.black};
        color: ${({ theme }) => theme.colors.black};
      }
    }
  }
`

interface IRadio {
	val: string | number | boolean
	name: string
	text?: string
	onChange?: (x: any) => void
	setValue?: any
}

const handlerChange = ({ event, name, val, onChange = null, data }) => {

	const isChecked = event.target.checked
	if (isChecked) {
		// setCheckbox(val, filterType)
	} else {
		// removeCheckbox(val, filterType)
	}

	onChange && onChange({
		target:
			{
				name: event.target.name,
				value: val,
			},
	})

	if (isChecked) {
		data.setState(name, val)
	}
}

const Radio = (params: IRadio) => {
	const {
		val,
		text,
		name,
		setValue = null,
	} = params
	const data = setValue instanceof nameState ? setValue : new nameState(setValue)

	return (
		<StyledRadio>
			<RadioEl value={params.val as string} {...params} checked={data.getState(name) === val} onChange={(event) => handlerChange({ ...params, event: event, data: data })} />
			<RadioIcon>
				<IconCheck />
			</RadioIcon>
			<RadioText>{text}</RadioText>
		</StyledRadio>
	)
}

export default Radio
