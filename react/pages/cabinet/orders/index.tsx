import CabinetLayout from '../../../components/layouts/CabinetLayout'
import {ApiQuery} from "../../../helpers/auth";
import Orders from "../../../components/cabinet/Orders";
import {GetServerSideProps} from "next";

const onPage = 10

const CabinetOrdersPage = ({orders, filters}) => {
    return <CabinetLayout title={'Заказы'}>
        <Orders {...orders} filters={filters} onPage={onPage}/>
    </CabinetLayout>
}

export const getServerSideProps: GetServerSideProps = async (context) => await new ApiQuery(context.req.cookies['SessionToken'])
    .addQuery('filters', 'getOrdersFilters')
    .addQuery('orders', 'getOrdersHistory',
        `${ApiQuery.getPageParams(context.query, onPage)}&${ApiQuery.getFilterParams(context.query, ['filterString', 'filterBy', 'filterStatus[]'])}`
    )
    .runQueriesProps(true)

export default CabinetOrdersPage
