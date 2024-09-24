<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OrderBundle\Tests\Functional\DataFixtures\LoadOrders as BaseLoadOrders;
use Oro\Bundle\OrderBundle\Tests\Functional\DataFixtures\LoadOrderUsers;
use Oro\Bundle\OrderBundle\Tests\Functional\DataFixtures\LoadPaymentTermData;

class LoadOrders extends BaseLoadOrders
{
    const MAIN_ORDER = 'main_order';
    const SUB_ORDER_1 = 'sub_order1';
    const SUB_ORDER_2 = 'sub_order2';
    const SUB_ORDER_3 = 'sub_order3';

    /**
     * @var array
     */
    protected $orders = [
        self::MAIN_ORDER => [
            'user' => LoadOrderUsers::ORDER_USER_1,
            'customerUser' => self::ACCOUNT_USER,
            'poNumber' => '1234567890',
            'customerNotes' => 'Test customer user notes',
            'currency' => 'USD',
            'subtotal' => 100.00,
            'total' => 120.00,
            'paymentTerm' => LoadPaymentTermData::PAYMENT_TERM_NET_10,
        ],
        self::SUB_ORDER_1 => [
            'user' => LoadOrderUsers::ORDER_USER_1,
            'customerUser' => self::ACCOUNT_USER,
            'poNumber' => '1234567890',
            'customerNotes' => 'Test customer user notes',
            'currency' => 'USD',
            'subtotal' => 40.00,
            'total' => 45.00,
            'paymentTerm' => LoadPaymentTermData::PAYMENT_TERM_NET_10,
        ],
        self::SUB_ORDER_2 => [
            'user' => LoadOrderUsers::ORDER_USER_1,
            'customerUser' => self::ACCOUNT_USER,
            'poNumber' => '1234567890',
            'customerNotes' => 'Test customer user notes',
            'currency' => 'USD',
            'subtotal' => 50.00,
            'total' => 60.00,
            'paymentTerm' => LoadPaymentTermData::PAYMENT_TERM_NET_10,
        ],
        self::SUB_ORDER_3 => [
            'user' => LoadOrderUsers::ORDER_USER_1,
            'customerUser' => self::ACCOUNT_USER,
            'poNumber' => '1234567890',
            'customerNotes' => 'Test customer user notes',
            'currency' => 'USD',
            'subtotal' => 10.00,
            'total' => 15.00,
            'paymentTerm' => LoadPaymentTermData::PAYMENT_TERM_NET_10,
        ]
    ];

    #[\Override]
    protected function createOrder(ObjectManager $manager, $name, array $orderData)
    {
        $order = parent::createOrder($manager, $name, $orderData);

        if ($name !== self::MAIN_ORDER) {
            $mainOrder = $this->getReference(self::MAIN_ORDER);
            $order->setParent($mainOrder);
        }
    }
}
