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

namespace Iyzico\Iyzipay\Block\Order;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;

use function uksort;

/**
 * Class IyzipayOrderTotals
 *
 * This class is used to add new total to totals array after specific total or before last total by default
 *
 * @package Iyzico\Iyzipay\Block\Order
 * @extends Template
 *
 * This class is used in etc/di.xml
 */
class IyzipayOrderTotals extends Template
{

    /**
     * Associated array of totals
     * array($totalCode => $totalObject)
     *
     * @var array $_totals
     */
    protected array $_totals;

    /**
     * Order Object
     *
     * @var Order|null $_order
     */
    protected ?Order $_order = null;

    /**
     * Core registry object
     *
     * @var Registry|null $_coreRegistry
     */
    protected ?Registry $_coreRegistry = null;

    /**
     * IyzipayOrderTotals Constructor
     *
     * @param  Context  $context
     * @param  Registry  $registry
     * @param  array  $data
     */
    public function __construct(Context $context, Registry $registry, array $data = [])
    {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Add new total to totals array after specific total or before last total by default
     *
     * @param  DataObject  $total
     * @param  null|string  $after
     * @return  $this
     */
    public function addTotal(DataObject $total, string $after = null)
    {
        if ($after !== null && $after != 'last' && $after != 'first') {
            $totals = [];
            $added = false;
            foreach ($this->_totals as $code => $item) {
                $totals[$code] = $item;
                if ($code == $after) {
                    $added = true;
                    $totals[$total->getCode()] = $total;
                }
            }
            if (!$added) {
                $last = array_pop($totals);
                $totals[$total->getCode()] = $total;
                $totals[$last->getCode()] = $last;
            }
            $this->_totals = $totals;
        } elseif ($after == 'last') {
            $this->_totals[$total->getCode()] = $total;
        } elseif ($after == 'first') {
            $totals = [$total->getCode() => $total];
            $this->_totals = array_merge($totals, $this->_totals);
        } else {
            $last = array_pop($this->_totals);
            $this->_totals[$total->getCode()] = $total;
            $this->_totals[$last->getCode()] = $last;
        }
        return $this;
    }

    /**
     * Add new total to totals array before specific total or after first total by default
     *
     * @param  DataObject  $total
     * @param  null|string  $before
     * @return  $this
     */
    public function addTotalBefore(DataObject $total, $before = null)
    {
        if ($before !== null) {
            if (!is_array($before)) {
                $before = [$before];
            }
            foreach ($before as $beforeTotals) {
                if (isset($this->_totals[$beforeTotals])) {
                    $totals = [];
                    foreach ($this->_totals as $code => $item) {
                        if ($code == $beforeTotals) {
                            $totals[$total->getCode()] = $total;
                        }
                        $totals[$code] = $item;
                    }
                    $this->_totals = $totals;
                    return $this;
                }
            }
        }
        $totals = [];
        $first = array_shift($this->_totals);
        $totals[$first->getCode()] = $first;
        $totals[$total->getCode()] = $total;
        foreach ($this->_totals as $code => $item) {
            $totals[$code] = $item;
        }
        $this->_totals = $totals;
        return $this;
    }

    /**
     * Get Total object by code
     *
     * @param  string  $code
     * @return mixed
     */
    public function getTotal($code)
    {
        if (isset($this->_totals[$code])) {
            return $this->_totals[$code];
        }
        return false;
    }

    /**
     * Delete total by specific
     *
     * @param  string  $code
     * @return  $this
     */
    public function removeTotal($code)
    {
        unset($this->_totals[$code]);
        return $this;
    }

    /**
     * Apply sort orders to totals array.
     * Array should have next structure
     * array(
     *  $totalCode => $totalSortOrder
     * )
     *
     * @param  array  $order
     * @return  $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function applySortOrder($order)
    {
        uksort(
            $this->_totals,
            function ($code1, $code2) use ($order) {
                return ($order[$code1] ?? 0) <=> ($order[$code2] ?? 0);
            }
        );
        return $this;
    }

    /**
     * Get totals array for visualization
     *
     * @param  array|null  $area
     * @return array
     */
    public function getTotals($area = null)
    {
        $totals = [];
        if ($area === null) {
            $totals = $this->_totals;
        } else {
            $area = (string) $area;
            foreach ($this->_totals as $total) {
                $totalArea = (string) $total->getArea();
                if ($totalArea == $area) {
                    $totals[] = $total;
                }
            }
        }
        return $totals;
    }

    /**
     * Format total value based on order currency
     *
     * @param  DataObject  $total
     * @return  string
     */
    public function formatValue($total)
    {
        if (!$total->getIsFormated()) {
            return $this->getOrder()->formatPrice($total->getValue());
        }
        return $total->getValue();
    }

    /**
     * Get order object
     *
     * @return Order
     */
    public function getOrder()
    {
        if ($this->_order === null) {
            if ($this->hasData('order')) {
                $this->_order = $this->_getData('order');
            } elseif ($this->_coreRegistry->registry('current_order')) {
                $this->_order = $this->_coreRegistry->registry('current_order');
            } elseif ($this->getParentBlock()->getOrder()) {
                $this->_order = $this->getParentBlock()->getOrder();
            }
        }
        return $this->_order;
    }

    /**
     * Sets order.
     *
     * @param  Order  $order
     * @return $this
     */
    public function setOrder($order)
    {
        $this->_order = $order;
        return $this;
    }

    /**
     * Initialize self totals and children blocks totals before html building
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function _beforeToHtml()
    {
        $this->_initTotals();
        foreach ($this->getLayout()->getChildBlocks($this->getNameInLayout()) as $child) {
            if (method_exists($child, 'initTotals') && is_callable([$child, 'initTotals'])) {
                $child->initTotals();
            }
        }
        return parent::_beforeToHtml();
    }

    /**
     * Initialize order totals array
     *
     * @return $this
     */
    protected function _initTotals()
    {
        $source = $this->getSource();

        $this->_totals = [];
        if ((double) $this->getSource()->getInstallmentFee() != 0) {
            $this->_totals['installment_fee'] = new DataObject(
                [
                    'code' => 'installment_fee',
                    'field' => 'installment_fee',
                    'strong' => true,
                    'value' => $source->getInstallmentFee(),
                    'label' => $source->getInstallmentCount().' '.__('Installment'),
                ]
            );
        }

        $this->_totals['subtotal'] = new DataObject(
            ['code' => 'subtotal', 'value' => $source->getSubtotal(), 'label' => __('Subtotal')]
        );

        // Kargo ücreti ekleme
        $this->addShippingTotal($source);

        if ((double) $this->getSource()->getDiscountAmount() != 0) {
            if ($this->getSource()->getDiscountDescription()) {
                $discountLabel = __('Discount (%1)', $source->getDiscountDescription());
            } else {
                $discountLabel = __('Discount');
            }
            $this->_totals['discount'] = new DataObject(
                [
                    'code' => 'discount',
                    'field' => 'discount_amount',
                    'value' => $source->getDiscountAmount(),
                    'label' => $discountLabel,
                ]
            );
        }

        $this->_totals['grand_total'] = new DataObject(
            [
                'code' => 'grand_total',
                'field' => 'grand_total',
                'strong' => true,
                'value' => $source->getGrandTotal(),
                'label' => __('Grand Total'),
            ]
        );

        if ($this->getOrder()->isCurrencyDifferent()) {
            $this->_totals['base_grandtotal'] = new DataObject(
                [
                    'code' => 'base_grandtotal',
                    'value' => $this->getOrder()->formatBasePrice($source->getBaseGrandTotal()),
                    'label' => __('Grand Total to be Charged'),
                    'is_formated' => true,
                ]
            );
        }
        return $this;
    }


    /**
     * Get totals source object
     *
     * @return Order
     */
    public function getSource()
    {
        return $this->getOrder();
    }

    /**
     * Add shipping total
     *
     * @param  Order|Order\Invoice  $source
     * @retrurn void
     */
    private function addShippingTotal($source)
    {
        if (!$source->getIsVirtual() && ($source->getShippingAmount() !== null || $source->getShippingDescription())) {
            $shippingLabel = __('Shipping & Handling');

            if (!isset($this->_totals['discount'])) {
                if ($source->getCouponCode()) {
                    $shippingLabel .= " ({$source->getCouponCode()})";
                } elseif ($source->getDiscountDescription()) {
                    $shippingLabel .= " ({$source->getDiscountDescription()})";
                }
            }
            $this->_totals['shipping'] = new DataObject(
                [
                    'code' => 'shipping',
                    'field' => 'shipping_amount',
                    'value' => $source->getShippingAmount(),
                    'label' => $shippingLabel,
                ]
            );
        }
    }

}
