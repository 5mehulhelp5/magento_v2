<?php

namespace Iyzico\Iyzipay\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class IyzipayCronSettings implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '* * * * *', 'label' => __('Every Minute')],
            ['value' => '*/3 * * * *', 'label' => __('Every 3 Minutes')],
            ['value' => '0 * * * *', 'label' => __('Every Hour')],
            ['value' => '0 */2 * * *', 'label' => __('Every 2 Hours')],
            ['value' => '0 */4 * * *', 'label' => __('Every 4 Hours')],
            ['value' => '0 */8 * * *', 'label' => __('Every 8 Hours')],
            ['value' => '0 */12 * * *', 'label' => __('Every 12 Hours')],
            ['value' => '0 0 * * *', 'label' => __('Every Day at Midnight')],
            ['value' => 'custom', 'label' => __('Custom')],
        ];
    }
}
