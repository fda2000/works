import styled from 'styled-components'
import { nameState } from '../../helpers/nameState'

export const InputTextStyled = styled.input`
	${({ theme }) => theme.editField}
`

const InputText = ({ name = null, setValue = null, ...more }) => {
	const data = setValue instanceof nameState ? setValue : new nameState(setValue)

	return (
		<InputTextStyled
			onChange={event => data.setState(name, event.target.value)}
			value={data.getState(name) ?? ''}
			{...more}
		/>
	)
}

export default InputText
