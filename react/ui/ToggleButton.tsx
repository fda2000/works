import styled from 'styled-components'
import { nameState } from '../../helpers/nameState'

const StyledToggleButton = styled.button<{on:boolean}>`
	& {
		opacity: ${props => props.on ? 1 : 0.5};
	}
`


const ToggleButton = ({ name = null, val = '', text = '', setValue = null, ...more }) => {
	const data = setValue instanceof nameState ? setValue : new nameState(setValue)

	return (
		<StyledToggleButton
			on={data.getState(name) === val}
			onClick={() => data.setState(name, data.getState(name) !== val ? val : null)}
			name={name}
			{...more}
		>
			{text}
		</StyledToggleButton>
	)
}

export default ToggleButton
