<?php

namespace App\Enums;

class OrderStatusEnum extends AbstractEnum
{
    const ORDER_NOT_PAID   =   'order.Not_Paid';
    const ORDER_PARTIAL     =   'order.Partial';
    const ORDER_PAID     =   'order.Paid';
    const ORDER_VOID     =   'order.Void';
    const ORDER_CHANGE     =   'order.Change';
}