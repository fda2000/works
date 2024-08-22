import styled from 'styled-components'
import { nameState } from '../../helpers/nameState'

export const TextareaStyled = styled.textarea`
	${({ theme }) => theme.editField}
	height: 150px;
`

const Textarea = ({ name = null, setValue = null, ...more }) => {
	const data = setValue instanceof nameState ? setValue : new nameState(setValue)

	return (
		<TextareaStyled
			onChange={event => data.setState(name, event.target.value)}
			value={data.getState(name)}
			{...more}
		/>
	)
}

export default Textarea
