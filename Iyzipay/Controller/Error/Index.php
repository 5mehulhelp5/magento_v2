<?php

namespace Iyzico\Iyzipay\Controller\Error;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;

/**
 * Class Index
 *
 * This class is used to display error messages in the frontend.
 *
 * @package Iyzico\Iyzipay\Controller\Error
 * @extends Action
 */
class Index extends Action
{
    protected $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $errorCode = $this->getRequest()->getParam('errorCode', 'Unknown Error Code');
        $errorMessage = $this->getRequest()->getParam('errorMessage', 'Unknown Error Message');

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getLayout()->getBlock('error_page')
            ->setData('error_code', $errorCode)
            ->setData('error_message', $errorMessage);

        return $resultPage;
    }
}
