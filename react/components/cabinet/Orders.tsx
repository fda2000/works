import styled from 'styled-components'
import {IOrder, IOrderFilter} from "../../interfaces/cabinet"
import 'moment/locale/ru'
import React, {useEffect, useState} from "react";
import AppReadMore from "../ui/AppReadMore";
import Link from "next/link";
import {useRouter} from "next/router";
import {BulbIconGray, BulbIconGreen, MomentRusMonth} from "../../pages/_app";
import OrderItems from "./OrderItems";
import Pagination from "../ui/pagination/Pagination";
import OrderFilters from "./OrderFilters";
import Money from "../ui/Money";
import {ApiQuery} from "../../helpers/auth";
import {toast} from "react-toastify";
import {useCookies} from "react-cookie";

const CabinetOrders = styled.ul`
  padding: 0;
  list-style: none;
`
const CabinetOrder = styled.li`
  margin-bottom: 80px;
  list-style: none;
`
const OrderHead1 = styled.ul`
  padding: 0;
  list-style: none;
  font-weight: 500;
  font-size: 24px;

  & li:last-of-type {
	float: right;
	font-weight: normal;
	position: relative;
	top: -22px;
  }
`
const OrderHead2 = styled.div`
  padding: 0;
  margin: 15px 0 40px 0;
  color: ${({theme}) => theme.colors.grayText};
  list-style: disc;
  //
  //& li:first-of-type {
  //    list-style: none;
  //    float: left;
  //    margin-right: 20px;
  //}
`
const CabinetBalanceBtn = styled.button`
  background-color: transparent;
  border-radius: 0;
  border: none;
`
const BtnAddToFbs = styled.div`
`
const NoOrders = styled.h2`
  text-align: center;
`
const ToOrder = styled.div`
  cursor: pointer;
  display: flex;
  width: max-content;
  transition: color 0.3s;

  :hover {
	color: #a09a9a;
  }
`
const OrderInfo = styled.div`
  display: flex;
  flex-direction: column;
`
const InfoRow = styled.div`
	&.transport-label {
	  cursor: pointer;
	  text-decoration: underline;
	  &:hover {
		text-decoration: none;
	  }
	}
`
// export const OrderStatus = styled.span`
//   color: ${(props) =>
// 		  props?.isRefused ? 'red' :
// 				  (props?.isFinished ? 'green' : '')
//   }
// `

const Orders = ({
					items: orders,
					total,
					onPage,
					filters
				}: { items: IOrder[], total: number, onPage: number, filters: IOrderFilter }) => {
	const [isOpen, setIsOpen] = useState({})
	const [cookies] = useCookies()
	const token = cookies?.SessionToken
	const router = useRouter()
	const {pathname} = router

	const updateState = useState(false)
	const [, setUpdate] = updateState

	useEffect(() => {
	}, [updateState])
	const addToFbs = async (orderId, index) => {
		const request = await new ApiQuery(token)
			.addQuery('data', 'addToFbs', `orderId=${orderId}`)
			.runQueriesProps()
		const response = request['props']['data']
		if (response.status) {
			toast.success('Заказ добавлен в отгрузку FBS')
			orders[index]['assemblyPullId'] = response['assemblyPullId']
			setUpdate(true)
		} else {
			toast.error(response.message)
		}
	}

	const {API_URL} = process.env
	const downloadFile = async (order) => {
		window.open(API_URL + 'personal/getTransportLabel?order=' + order.id + '&Session-Token=' + token, '_blank')
	}
	return (
		<>
			<OrderFilters {...filters}/>
			<CabinetOrders>
				{!!orders && orders.length && orders.map((order, index) =>
						<CabinetOrder key={order.id} className="Order">
							<OrderHead1>
								<Link href={`${pathname}/${order.id}`}>
									<ToOrder>№ {order.id}</ToOrder>
								</Link>
								{/*{typeof order.status?.isGreenLightStatus !== 'undefined' &&*/}
								{/*	(order.status.isGreenLightStatus && <BulbIconGreen/> || <BulbIconGray/>)*/}
								{/*}*/}
								<li><Money>{order.sum}</Money></li>
							</OrderHead1>
							<OrderHead2>
								<OrderInfo>
									<InfoRow>{order.typeLabel}</InfoRow>
									{/*<OrderStatus {...order.status}>Статус: {order.status.name}</OrderStatus>*/}
									<InfoRow>Статус: {order.status.name}</InfoRow>
									{order.externalId && <InfoRow>
										№ заказа на маркетплейсе: {order.externalId}</InfoRow>}
									<InfoRow>Дата
										создания: <MomentRusMonth>{order.dateCreated}</MomentRusMonth></InfoRow>
									<InfoRow>Дата
										отгрузки: <MomentRusMonth>{order.dateShipped}</MomentRusMonth></InfoRow>
									{order.isTransportLabel && <InfoRow
										onClick={() => downloadFile(order)}
										className={'transport-label'}
									>
										Этикетка
									</InfoRow>}
								</OrderInfo>
								{order.assemblyPullId && <CabinetBalanceBtn>
									<Link href={'/cabinet/assemblyPull/' + order.assemblyPullId} passHref>
										<AppReadMore>Заказ в отгрузке FBS №{order.assemblyPullId}</AppReadMore>
									</Link>
								</CabinetBalanceBtn>}
								{!order.assemblyPullId && order.canAddToAssemblyPull && <CabinetBalanceBtn>
									<BtnAddToFbs onClick={() => addToFbs(order.id, index)}>
										<AppReadMore>Добавить в отгрузку FBS</AppReadMore>
									</BtnAddToFbs>
								</CabinetBalanceBtn>}
							</OrderHead2>
							<OrderItems items={order.items} orderId={order.id} isOpen={isOpen} setIsOpen={setIsOpen}/>
							<Link href={`${pathname}/${order.id}`} passHref>
								<AppReadMore/>
							</Link>
						</CabinetOrder>
					) ||
					<NoOrders>Заказов не найдено</NoOrders>
				}
			</CabinetOrders>
			{!!total && total > onPage && <Pagination total={total} sizes={[onPage]}/>}
		</>
	)
}

export default Orders
