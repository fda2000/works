const Money = (props) => {
	const value = Number(props?.children)
	return (
		<>
			{!Number.isNaN(value) &&
			 <>{new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0 }).format(value)}</>
			}
		</>
	)
}

export default Money
