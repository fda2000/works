import styled from 'styled-components'
import {imageOrDefault} from "../../helpers/image-or-default"
import Image from 'next/image'
import {getCatalogMenuItem} from "../../hooks";
import React from "react";
import {declOfNum} from "../../helpers/rus";
import Money from "../ui/Money";

const maxOrderItems = 3

const OrderItemsList = styled.ul`
    padding: 20px;
    margin-bottom: 40px;
    list-style: none;
    background: ${({theme}) => theme.colors.white};
    border-radius: ${({theme}) => theme.radius};
`
const OrderItem = styled.li`
    padding: 20px 0;
    border-bottom: 1px solid ${({theme}) => theme.colors.border};
    &:last-of-type {
        border-bottom: none;
    }
    
    & ul {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    & li {
        display: table-cell;
        vertical-align: top;
        width: 360px;
        padding: 0 10px;
    }
    & li:first-of-type {
        width: 100px;
        padding-left: 0;
    }
    & li:last-of-type {
        text-align: right;
        width: 140px;
        padding-right: 0;
    }
    & li:last-of-type span {
        color: ${({theme}) => theme.colors.grayText};
    }
`
const ProductImage = styled(Image)`
    border: 1px solid ${({theme}) => theme.colors.border} !important;
    border-radius: ${({theme}) => theme.radius};
`

const AssortmentColorImage = styled(Image)`
    border: 1px solid ${({theme}) => theme.colors.border} !important;
    border-radius: 50%;
    overflow: hidden;
`
const AssortmentProp = styled.span`
    margin: 0 10px;
`

const OrderItems = ({items, orderId, isOpen, setIsOpen}) => {
    return (
        <OrderItemsList>
            {!!items && items.map((item, key) =>
                (key < maxOrderItems || isOpen[orderId]) &&
                <OrderItem key={orderId + '_' + item.assortment.id}>
                    <ul>
                        <li>
                            <ProductImage
                                src={imageOrDefault(item.product?.imgSrc)}
                                alt={item.product.name}
                                width={100}
                                height={100}
                                unoptimized
                            />
                        </li>
                        <li>
                            <p><a
                                href={getCatalogMenuItem().staticUrl + item.product.slug.substr(1, item.product.slug.length - 2)}>
                                {item.product.name}
                            </a></p>
                            {!!item.assortment?.colorImgSrc &&
                                <AssortmentColorImage
                                    src={imageOrDefault(item.assortment.colorImgSrc)}
                                    alt={item.assortment.colorName}
                                    width={16}
                                    height={16}
                                    unoptimized
                                />
                            }
                            {item.assortment?.colorName &&
                                <AssortmentProp>{item.assortment.colorName}</AssortmentProp>
                            }
                            {item.assortment?.sizeName &&
                                <AssortmentProp>{item.assortment.sizeName}</AssortmentProp>
                            }
                        </li>
                        <li>
                            <Money>{item.price}</Money>
                            {item.quantity > 1 &&
                                <span> x {item.quantity}</span>
                            }
                        </li>
                    </ul>
                </OrderItem>
            )}
            {items.length > maxOrderItems &&
                <OrderItem>
                    <a href="#" onClick={(event) => {
                        event.preventDefault()
                        setIsOpen({
                            ...isOpen,
                            [orderId]: !isOpen[orderId]
                        })
                    }
                    }>
                        {!isOpen[orderId] && <>Еще {declOfNum(`${items.length - maxOrderItems} товар`)}</>}
                        {isOpen[orderId] && <>Скрыть</>}
                    </a>
                </OrderItem>
            }
        </OrderItemsList>
    )
}

export default OrderItems
