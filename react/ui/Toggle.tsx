import styled from "styled-components"
import { useState } from "react";

const StyledToggle = styled('label')<IToggleProps>`
  position: relative;
  display: flex;
  justify-content: flex-start;
  align-items: center;
  width: 40px;
  height: 24px;
  padding: 2px;
  border-radius: 12px;
  cursor: pointer;
  overflow: hidden;
  margin: ${({margin}) => margin ?? '0'};
`

const ToggleBackground = styled.div`
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  z-index: 1;
  width: 100%;
  height: 100%;
  background-color: ${({theme}) => theme.colors.lightGray};
  transition: background-color 0.2s;
`

const ToggleSwitcher = styled.span`
  position: relative;
  z-index: 2;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background-color: ${({theme}) => theme.colors.background};
  transition: transform 0.2s;
`

const ToggleCheckbox = styled.input.attrs(({checked, disabled}) => ({
  type: 'checkbox',
  checked: checked,
  disabled: disabled
}))`
  display: none;

  &:checked {
    + ${ToggleBackground} {
      background-color: ${({theme}) => theme.colors.black};
      transition: background-color 0.2s;

      + ${ToggleSwitcher} {
        transform: translateX(calc(100% - 4px));
        transition: transform 0.2s;
      }
    }
  }
`

interface IToggleProps {
  checked?: boolean
  disabled?: boolean
  margin?: string
}

const Toggle = ({checked, disabled, margin}:IToggleProps) => {
  const [check, setChecked] = useState(checked);

  return (
    <StyledToggle margin={margin}>
      <ToggleCheckbox
        checked={check}
        disabled={disabled}
        onChange={() => setChecked(!check)}
      />
      <ToggleBackground/>
      <ToggleSwitcher/>
    </StyledToggle>
  )
};

export default Toggle;
