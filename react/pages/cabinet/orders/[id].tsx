import CabinetLayout from '../../../components/layouts/CabinetLayout'
import {ApiQuery} from "../../../helpers/auth";
import Order from "../../../components/cabinet/Order";
import {IOrder} from "../../../interfaces/cabinet";
import {GetServerSideProps} from "next";

const CabinetOrderPage = ({order}: { order: IOrder }) => {
    return <CabinetLayout title={`Заказ № ${order.id}`} id={3}>
        <Order order={order}/>
    </CabinetLayout>
}

export const getServerSideProps: GetServerSideProps = async (context) => await new ApiQuery(context.req.cookies['SessionToken'])
    .addQuery('order', 'getOrderInfo', `id=${context.query.id}`)
    .runQueriesProps(true)

export default CabinetOrderPage
