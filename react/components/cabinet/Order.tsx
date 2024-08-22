import styled from 'styled-components'
import {IOrder} from '../../interfaces/cabinet'
import 'moment/locale/ru'
import React, {useEffect, useState} from 'react'
import {MomentRusMonth} from '../../pages/_app'
import OrderItems from './OrderItems'
import {declOfNum} from '../../helpers/rus'
import Money from '../ui/Money'
import DropZone from "../ui/DropZone";
import {useCookies} from "react-cookie";
import MpSvgSelector from "./marketplace/mpSvg/MpSvgSelector";
import { ApiQuery } from '../../helpers/auth'

const OrderBlock = styled.div`
`
const OrderStr = styled.div`
  font-size: 18px;
  border-bottom: 1px solid ${({theme}) => theme.colors.border};
  margin-bottom: 30px;
  padding-bottom: 10px;

  & h4 {
	width: 250px;
	font-weight: normal;
	color: ${({theme}) => theme.colors.grayText};
  }

  &.no-separate {
	border-bottom: 0;
	margin-bottom: 0;
	padding-bottom: 0;
	display: flex;

	& h4 {
	  width: 250px;
	}
  }
`
const OrderTab = styled.div`
  & h2 .csv-xml{
	display: flex;
	align-items: center;
	font-weight: normal;
	& span {
	  cursor: pointer;
	}
  }
  & h2 .csv-xml .csv{
	margin: 0 0 0 10px;
  }
  &.separate {
	border-bottom: 1px solid ${({theme}) => theme.colors.border};
	margin-bottom: 90px;
  }

  & h2 {
	margin: 40px 0;
	display: flex;
	justify-content: space-between;
	align-items: center;
  }

  & ${OrderStr}:last-of-type {
	border-bottom: none;
  }
`
const OrderTotal = styled.div`
  padding: 40px;
  margin-bottom: 40px;
  list-style: none;
  background: ${({theme}) => theme.colors.backgroundGray};
  border-radius: ${({theme}) => theme.radius};

  & ${OrderStr} {
	border-bottom: none;
	margin-bottom: 10px;
  }

  & ${OrderStr} h4, & ${OrderStr} p {
	width: 50%;
	display: inline-block;
	color: inherit;
  }

  & ${OrderStr} p {
	text-align: right;
  }
`
const OrderStrBold = styled(OrderStr)`
  &${OrderStr} {
	border-top: 1px solid ${({theme}) => theme.colors.border};
	padding-top: 40px;
	padding-bottom: 0;
	margin-bottom: 0;
  }

  &${OrderStr} h4, &${OrderStr} p {
	font-weight: bold;
  }
`
const InfoRow = styled.div`
  &.transport-label-upload {
	color: #70ae0f;
  }
  &.transport-label-no-upload {
	color: #e10000;
  }

  &.transport-label {
	cursor: pointer;
	display: flex;
	align-items: center;
	width: 100px;
	justify-content: space-between;
	margin: 5px 0 10px 0;
  }
`
const Dott = styled.div`
  display: flex;
  justify-content: center;
  align-content: center;
  width: min-content;
  margin: 0 7px 0 7px;
`
const Order = ({order}: { order: IOrder }) => {
	const [isOpen, setIsOpen] = useState({})
	const dropState = useState();
	const [dropFile] = dropState;
	const [cookies] = useCookies()
	const token = cookies?.SessionToken
	useEffect(() => {
		if (dropFile == undefined) {
			return
		}
		const formData = new FormData()
		formData.append('label', dropFile)
		formData.append('order', order.id)
		formData.append('isSessionToken', 'true')
		//formData.append('ApiKey', token)

		const api = new ApiQuery(token).addQuery('label', 'orderLabel', '', formData)
		api.runQueries().then(res => {
			const { label } = res
			//ну и наверно дальше что-то?..
		})
	}, [dropFile])

	const {API_URL} = process.env
	const downloadFile = async (order) => {
		window.open(API_URL + 'personal/getTransportLabel?order=' + order.id + '&Session-Token=' + token, '_blank')
	}
	const downloadCsv = async () => {
		window.open(API_URL + 'personal/getCsv?order=' + order.id + '&Session-Token=' + token, '_blank')
	}
	const downloadXml = async () => {
		window.open(API_URL + 'personal/getXml?order=' + order.id + '&Session-Token=' + token, '_blank')
	}

	return (
		<>
			<OrderTab className={'separate'}>
				<h2>Основное</h2>
				<OrderBlock>
					<OrderStr className={'no-separate'}>
						<h4>Тип заказа</h4>
						<p>{order.typeLabel}</p>
					</OrderStr>
					<OrderStr className={'no-separate'}>
						<h4>Дата оформления</h4>
						<MomentRusMonth>{order.dateCreated}</MomentRusMonth>
					</OrderStr>
					<OrderStr className={'no-separate'}>
						<h4>Дата отгрузки</h4>
						<MomentRusMonth>{order.dateShipped}</MomentRusMonth>
					</OrderStr>
					{order.externalId && <OrderStr className={'no-separate'}>
						<h4>№ заказа на маркетплейсе</h4>
						<p>{order.externalId}</p>
					</OrderStr>}
					<OrderStr className={'no-separate'}>
						<h4>
							Статус
							{/*{typeof order.status?.isGreenLightStatus !== 'undefined' &&*/}
							{/*	(order.status.isGreenLightStatus && <BulbIconGreen /> || <BulbIconGray />)*/}
							{/*}*/}
						</h4>
						{/*<p><OrderStatus {...order.status}>{order.status.name}</OrderStatus></p>*/}
						<p>{order.status.name}</p>
					</OrderStr>
				</OrderBlock>
			</OrderTab>
			<OrderTab>
				<h2>Состав <span className={'csv-xml'}>
					<MpSvgSelector width={18} height={18} name={'download'}/>
					<span className={'csv'} onClick={() => downloadCsv()}>CSV</span>
					<Dott>&bull; </Dott>
					<span onClick={() => downloadXml()}>XML</span>
				</span>
				</h2>
			</OrderTab>
			<OrderItems items={order.items} orderId={order.id} isOpen={isOpen} setIsOpen={setIsOpen}/>
			<OrderTotal>
				<OrderStr>
					<h4>Товары</h4>
					<p>{declOfNum(`${order.items.length} наименование`)}</p>
				</OrderStr>
				<OrderStr>
					<h4>Количество</h4>
					<p>{declOfNum(`${order.items.reduce((sum, item) => sum + Number(item.quantity), 0)} штука`)}</p>
				</OrderStr>
				<OrderStrBold>
					<h4>К оплате</h4>
					<p><Money>{order.sum}</Money></p>
				</OrderStrBold>
			</OrderTotal>

			<OrderTab>
				<h2>Загрузка этикетки</h2>
				<OrderStr>
					{order.isTransportLabel && <>
						<InfoRow className={'transport-label-upload'}>
							Этикетка загружена
						</InfoRow>
						<InfoRow
							onClick={() => downloadFile(order)}
							className={'transport-label'}
						>
							<MpSvgSelector width={17} height={17} name={'download'}/>
							Этикетка
						</InfoRow></>}
					{!order.isTransportLabel && <InfoRow className={'transport-label-no-upload'}>
						Этикетка не загружена
					</InfoRow>}
					{!order.isFbo && <DropZone dropState={dropState}/>}
					{/*<p>{order.receiver.name}</p>*/}
				</OrderStr>


				<h2>Получатель</h2>
				<OrderStr>
					<h4>Имя и фамилия</h4>
					<p>{order.receiver.name}</p>
				</OrderStr>
				<OrderStr>
					<h4>Телефон</h4>
					<p>{order.receiver.phone}</p>
				</OrderStr>
				<OrderStr>
					<h4>Почта</h4>
					<p>{order.receiver.email}</p>
				</OrderStr>
			</OrderTab>
			<OrderTab>
				<h2>Оплата</h2>
				<OrderStr>
					<h4>Способ</h4>
					<p>{order.payment.type.label}</p>
				</OrderStr>
				<OrderStr>
					<h4>Стоимость</h4>
					<p><Money>{order.payment.totalCost}</Money></p>
				</OrderStr>
			</OrderTab>
			<OrderTab>
				<h2>Доставка</h2>
				<OrderStr>
					<h4>Способ</h4>
					<p title={order.delivery.type.label}>{order.delivery.type.name}</p>
				</OrderStr>
				<OrderStr>
					<h4>Город</h4>
					<p>{order.delivery.city}</p>
				</OrderStr>
				<OrderStr>
					<h4>Адрес</h4>
					<p>{order.delivery.address}</p>
				</OrderStr>
				{order.delivery.date &&
					<OrderStr>
						<h4>Дата доставки</h4>
						<p><MomentRusMonth>{order.delivery.date}</MomentRusMonth></p>
					</OrderStr>
				}
				{order.userComment &&
					<OrderStr>
						<h4>Комментарий заказчика</h4>
						<p>{order.userComment}</p>
					</OrderStr>
				}
			</OrderTab>
		</>
	)
}

export default Order
