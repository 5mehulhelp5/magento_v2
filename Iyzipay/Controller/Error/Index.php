<?php

namespace Iyzico\Iyzipay\Controller\Error;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\State;

class Index extends Action
{
    protected $resultPageFactory;
    protected $assetRepository;
    protected $appState;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Repository $assetRepository,
        State $appState
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->assetRepository = $assetRepository;
        $this->appState = $appState;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Iyzipay Error'));

        $errorCode = $this->getRequest()->getParam('code');
        $errorMessage = $this->getRequest()->getParam('message');

        $block = $resultPage->getLayout()->getBlock('iyzipay_error');

        if ($block) {
            $block->setErrorCode($errorCode);
            $block->setErrorMessage($errorMessage);
            $block->setLogoUrl($this->getLogoUrl());
        }

        return $resultPage;
    }

    protected function getLogoUrl()
    {
        $params = [
            'area' => $this->appState->getAreaCode()
        ];
        return $this->assetRepository->getUrlWithParams('Iyzico_Iyzipay::iyzico/iyzico_logo.png', $params);
    }
}
