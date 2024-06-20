<?php

namespace Iyzico\Iyzipay\Controller\Error;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\View\Result\Page;

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
    /**
     * PageFactory Instance
     *
     * @var PageFactory $_resultPageFactory
     */
    protected PageFactory $_resultPageFactory;

    /**
     * Error Index Constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @return void
     */
    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        $this->resultPag_resultPageFactoryeFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Execute Method
     *
     * This method is used to display error messages in the frontend.
     *
     * @return Page
     */
    public function execute()
    {
        $errorCode = $this->getRequest()->getParam('errorCode', 'Unknown Error Code');
        $errorMessage = $this->getRequest()->getParam('errorMessage', 'Unknown Error Message');

        $resultPage = $this->_resultPageFactory->create();
        $resultPage->getLayout()->getBlock('error_page')
            ->setData('error_code', $errorCode)
            ->setData('error_message', $errorMessage);

        return $resultPage;
    }
}
