<?php

/**
 * iyzico Payment Gateway For Magento 2
 * Copyright (C) 2018 iyzico
 *
 * This file is part of Iyzico/Iyzipay.
 *
 * Iyzico/Iyzipay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Iyzico\Iyzipay\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\InstallSchemaInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (!$setup->tableExists('iyzico_order')) {
            $this->createIyzicoOrderTable($setup);
        }

        if (!$setup->tableExists('iyzico_order_job')) {
            $this->createIyzicoOrderJobTable($setup);
        }

        if (!$setup->tableExists('iyzico_card')) {
            $this->createIyzicoCardTable($setup);
        }

        $this->addColumnsToSalesOrderAndQuote($setup);

        $setup->endSetup();
    }

    private function createIyzicoOrderTable(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()
            ->newTable($setup->getTable('iyzico_order'))
            ->addColumn(
                'iyzico_order_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true,
                ],
                'iyzico Order Id'
            )
            ->addColumn(
                'payment_id',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false],
                'iyzico Payment Id'
            )
            ->addColumn(
                'order_id',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'Order Id'
            )
            ->addColumn(
                'total_amount',
                Table::TYPE_DECIMAL,
                '10,2',
                ['nullable' => false],
                'iyzico Total Amount'
            )
            ->addColumn(
                'status',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'iyzico Status'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->setComment('iyzico Order');
        $setup->getConnection()->createTable($table);
    }

    private function createIyzicoCardTable(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()
            ->newTable($setup->getTable('iyzico_card'))
            ->addColumn(
                'iyzico_card_id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true,
                ],
                'iyzico Card - Magento Card Id'
            )
            ->addColumn(
                'customer_id',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false],
                'iyzico Card - Magento Customer Id'
            )
            ->addColumn(
                'card_user_key',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'iyzico Card - Card User Key'
            )
            ->addColumn(
                'api_key',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'iyzico Card - Api Key'
            )
            ->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->setComment('iyzico Card');
        $setup->getConnection()->createTable($table);
    }

    private function createIyzicoOrderJobTable(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()
            ->newTable($setup->getTable('iyzico_order_job'))
            ->addColumn(
                'id',
                Table::TYPE_INTEGER,
                null,
                [
                    'identity' => true,
                    'unsigned' => true,
                    'nullable' => false,
                    'primary' => true,
                ],
                'Id'
            )
            ->addColumn(
                'magento_order_id',
                Table::TYPE_INTEGER,
                null,
                ['unsigned' => true, 'nullable' => false],
                'Magento Order Id'
            )
            ->addColumn(
                'iyzico_payment_token',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'iyzico Payment Token'
            )
            ->addColumn(
                'iyzico_conversationId',
                Table::TYPE_TEXT,
                255,
                ['nullable' => false],
                'iyzico Payment Conversation Id'
            )
            ->addColumn(
                'expire_at',
                Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false],
                'Payment Expire At'
            )
            ->addIndex(
                $setup->getIdxName(
                    'iyzico_order_job',
                    ['iyzico_payment_token']
                ),
                ['iyzico_payment_token']
            )
            ->addIndex(
                $setup->getIdxName(
                    'iyzico_order_job',
                    ['iyzico_conversationId']
                ),
                ['iyzico_conversationId']
            )
            ->addForeignKey(
                $setup->getFkName(
                    'iyzico_order_job',
                    'magento_order_id',
                    'sales_order',
                    'entity_id'
                ),
                'magento_order_id',
                $setup->getTable('sales_order'),
                'entity_id',
                Table::ACTION_CASCADE
            )
            ->setComment('iyzico Order Job List');
        $setup->getConnection()->createTable($table);
    }

    private function addColumnsToSalesOrderAndQuote(SchemaSetupInterface $setup)
    {
        $feeOptions = [
            "type" => Table::TYPE_DECIMAL,
            "length" => "10,2",
            "visible" => false,
            "required" => false,
            "comment" => "Installment Fee",
        ];

        $feeCountOptions = [
            "type" => Table::TYPE_INTEGER,
            "visible" => false,
            "required" => false,
            "comment" => "Installment Count",
        ];


        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'installment_fee',
            $feeOptions
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'installment_fee',
            $feeOptions
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('sales_order'),
            'installment_count',
            $feeCountOptions
        );

        $setup->getConnection()->addColumn(
            $setup->getTable('quote'),
            'installment_count',
            $feeCountOptions
        );
    }
}
