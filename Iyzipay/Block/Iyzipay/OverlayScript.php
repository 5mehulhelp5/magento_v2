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

namespace Iyzico\Iyzipay\Block\Iyzipay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class OverlayScript
 *
 * @package Iyzico\Iyzipay\Block\Iyzipay
 * @extends Template
 *
 * This class used in view/frontend/layout/default.xml and view/frontend/templates/oveerlayscript.phtml
 */
class OverlayScript extends Template
{
    /**
     * @var $_scopeConfig
     */
    protected $_scopeConfig;

    public function __construct(Context $context, ScopeConfigInterface $scopeConfig)
    {
        parent::__construct($context);
        $this->_scopeConfig = $scopeConfig;
    }

    public function render(): ?string
    {
        $position = $this->_scopeConfig->getValue('payment/iyzipay/overlayscript');
        $protectedShopId = $this->_scopeConfig->getValue('payment/iyzipay/protectedShopId');

        if ($position !== 'hidden') {
            return <<<HTML
                <script>
                    window.iyz = {
                        token: '{$protectedShopId}',
                        position: '{$position}',
                        ideaSoft: false
                    };
                </script>
                <script src="https://static.iyzipay.com/buyer-protection/buyer-protection.js" type="text/javascript">
                </script>
            HTML;
        }

        return null;
    }
}
