import React, { useCallback, useState } from 'react'
import { useDropzone } from 'react-dropzone'
import styled from 'styled-components'
import Byte from './Byte'

const DropZoneContainer = styled.div`
	border: 3px dashed ${({theme}) => theme.colors.gray};
	width: 340px;
	min-height: 90px;
	display: flex;
	justify-content: center;
	align-items: center;
	border-radius: 18px;
	padding: 9px;
	color: ${({theme}) => theme.colors.grayText};
	cursor: pointer;
`
const DragActive = styled.div`
`
const DragNoActive = styled.div`
`
const FileName = styled.div`
	text-align: center;
	color: ${({theme}) => theme.colors.ok};
`
const ImagePreview = styled.img`
	max-width: 100%;
	max-height: 100%;
`

const DropZone = (
	{dropState, text, showPreview} :
		{
			dropState: [File|null, React.Dispatch<React.SetStateAction<File|null>>],
			text?: string,
			showPreview?: boolean
		}
) => {
	const labelText = text ?? 'Нажмите, либо перетащите сюда файл'
	const [dropFile, setDropFile] = dropState
	const [preview, setPreview] = useState(null)

	const onDrop = useCallback(acceptedFiles => {
		acceptedFiles.forEach((file) => {
			const reader = new FileReader()
			reader.onabort = () => console.log('file reading was aborted')
			reader.onerror = () => console.log('file reading has failed')
			reader.onload = () => {
				const src = URL.createObjectURL(file)
				showPreview && file.type.indexOf('image/') === 0 && setPreview(src)
				setDropFile(file)
			}
			setPreview(null)
			reader.readAsArrayBuffer(file)
		})
	}, [])
	const {getRootProps, getInputProps, isDragActive} = useDropzone({onDrop})

	return (
		<DropZoneContainer {...getRootProps()}>
			<input {...getInputProps()} />
			{
				isDragActive ?
					<DragActive>Бросай</DragActive> :
					<DragNoActive>
						{labelText}
						{dropFile && <FileName>{dropFile.name} (<Byte>{dropFile.size}</Byte>)</FileName>}
						{dropFile && preview && <a href={preview} rel="noreferrer" target="_blank" title={dropFile.name}>
							<ImagePreview src={preview} alt={dropFile.name} />
						</a>}
					</DragNoActive>
			}
		</DropZoneContainer>
	)
}

export default DropZone;
