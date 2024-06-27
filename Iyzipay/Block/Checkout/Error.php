<?php

namespace Iyzico\Iyzipay\Block\Checkout;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Repository;

class Error extends Template
{
    protected $_request;
    protected $_assetRepository;

    public function __construct(
        Template\Context $context,
        RequestInterface $request,
        Repository $assetRepository,
        array $data = []
    ) {
        $this->_request = $request;
        $this->_assetRepository = $assetRepository;
        parent::__construct($context, $data);
    }

    public function getErrorCode()
    {
        return $this->_request->getParam('code', 'Unknown Error Code');
    }

    public function getErrorMessage()
    {
        return $this->_request->getParam('message', 'Unknown Error Message');
    }

    public function getLogoUrl()
    {
        return $this->_assetRepository->getUrl('Iyzico_Iyzipay::iyzico/iyzico_logo.png');
    }

    public function getCartUrl()
    {
        return $this->getUrl('checkout/cart');
    }
}
