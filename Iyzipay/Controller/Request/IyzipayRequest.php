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

namespace Iyzico\Iyzipay\Controller\Request;

use Exception;
use Iyzico\Iyzipay\Helper\ConfigHelper;
use Iyzico\Iyzipay\Helper\ObjectHelper;
use Iyzico\Iyzipay\Helper\UtilityHelper;
use Iyzico\Iyzipay\Library\Model\CheckoutFormInitialize;
use Iyzico\Iyzipay\Library\Options;
use Iyzico\Iyzipay\Library\Request\CreateCheckoutFormInitializeRequest;
use Iyzico\Iyzipay\Logger\IyziErrorLogger;
use Iyzico\Iyzipay\Model\IyziCardFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Framework\ObjectManagerInterface;


class IyzipayRequest implements ActionInterface
{

    protected CheckoutSession $checkoutSession;
    protected CustomerSession $customerSession;
    protected IyziCardFactory $iyziCardFactory;
    protected JsonFactory $resultJsonFactory;
    protected Quote $quote;
    protected IyziErrorLogger $errorLogger;
    protected ConfigHelper $configHelper;
    protected UtilityHelper $utilityHelper;
    private ObjectManagerInterface $objectManager;

    public function __construct
    (
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        IyziCardFactory $iyziCardFactory,
        JsonFactory $resultJsonFactory,
        Quote $quote,
        IyziErrorLogger $errorLogger,
        ConfigHelper $configHelper,
        UtilityHelper $utilityHelper,
        ObjectManagerInterface $objectManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->iyziCardFactory = $iyziCardFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->quote = $quote;
        $this->errorLogger = $errorLogger;
        $this->configHelper = $configHelper;
        $this->utilityHelper = $utilityHelper;
        $this->objectManager = $objectManager;
    }

    /**
     * Execute
     *
     * This function is responsible for executing the payment request.
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(): Json
    {
        $objectHelper = $this->getObjectHelper();
        $apiKey = $this->configHelper->getApiKey();
        $secretKey = $this->configHelper->getSecretKey();
        $customerId = $this->utilityHelper->getCustomerId($this->customerSession);
        $locale = $this->configHelper->getLocale();
        $basketId = $this->checkoutSession->getQuoteId();
        $conversationId = $this->utilityHelper->generateConversationId($basketId);
        $checkoutSession = $this->checkoutSession->getQuote();
        $basketItems = $objectHelper->createBasketItems($checkoutSession);
        $currency = $this->configHelper->getCurrency();
        $buyer = $objectHelper->createBuyer($checkoutSession, $this->customerSession->getEmail());
        $callbackUrl = $this->configHelper->getCallbackUrl() . "Iyzico_Iyzipay/response/iyzipayresponse";
        $price = $this->utilityHelper->calculateSubtotalPrice($checkoutSession);
        $paidPrice = $this->utilityHelper->parsePrice(round($checkoutSession->getGrandTotal(), 2));
        $paymentSource = "MAGENTO2|" . $this->configHelper->getMagentoVersion() . "|SPACE-2.1.1";
        $cardUserKey = $this->utilityHelper->getCustomerCardUserKey($this->iyziCardFactory, $customerId, $apiKey);
        $shippingAddress = $objectHelper->createShippingAddress($checkoutSession);
        $billingAddress = $objectHelper->createBillingAddress($checkoutSession);
        $baseUrl = $this->configHelper->getBaseUrl();

        $request = new CreateCheckoutFormInitializeRequest();
        $request->setLocale($locale);
        $request->setConversationId($conversationId);
        $request->setPrice($price);
        $request->setPaidPrice($paidPrice);
        $request->setCurrency($currency);
        $request->setBasketId($basketId);
        $request->setPaymentGroup("PRODUCT");
        $request->setCallbackUrl($callbackUrl);
        $request->setPaymentSource($paymentSource);
        $request->setBuyer($buyer);
        $request->setShippingAddress($shippingAddress);
        $request->setBillingAddress($billingAddress);
        $request->setBasketItems($basketItems);
        $request->setCardUserKey($cardUserKey);

        $options = new Options();
        $options->setBaseUrl($baseUrl);
        $options->setApiKey($apiKey);
        $options->setSecretKey($secretKey);

        $response = CheckoutFormInitialize::create($request, $options);

        $responseConversationId = $response->getConversationId();
        $responseToken = $response->getToken();
        $responseSignature = $response->getSignature();

        $calculateSignature = $this->utilityHelper->calculateHmacSHA256Signature([
            $responseConversationId,
            $responseToken
        ], $secretKey);

        if ($responseSignature === $calculateSignature) {
            return $this->createJsonResult($this->processSuccessfulResponse($response, $basketId));
        }

        return $this->createJsonResult([
            'success' => false,
            'message' => "Signature Mismatch",
            'code' => "0"
        ]);
    }

    /**
     * Get Object Helper
     *
     * This function is responsible for getting the object helper.
     *
     * @return ObjectHelper
     */
    private function getObjectHelper(): ObjectHelper
    {
        return new ObjectHelper($this->utilityHelper);
    }

    /**
     * Create Json Result
     *
     * This function is responsible for creating the json result.
     *
     * @param $result
     * @return Json
     */
    private function createJsonResult($result): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($result);
    }

    /**
     * Process Successful Response
     *
     * This function is responsible for processing the successful response.
     *
     * @param  CheckoutFormInitialize  $requestResponse
     * @param $basketId
     * @return array
     */
    private function processSuccessfulResponse(CheckoutFormInitialize $requestResponse, $basketId): array
    {
        $this->saveIyziOrderJobTable($requestResponse, $basketId);
        return ['success' => true, 'url' => $requestResponse->getPaymentPageUrl()];
    }

    /**
     * Save Iyzi Order Table
     *
     * This function is responsible for saving the iyzi order table.
     *
     * @param  CheckoutFormInitialize  $requestResponse
     * @param $quoteId
     */
    private function saveIyziOrderJobTable(CheckoutFormInitialize $requestResponse, $quoteId): void
    {
        $iyzicoOrderJob = $this->objectManager->create('Iyzico\Iyzipay\Model\IyziOrderJob');

        $iyzicoOrderJob->setData([
            'order_id' => null,
            'quote_id' => $quoteId,
            'iyzico_payment_token' => $requestResponse->getToken(),
            'iyzico_conversation_id' => $requestResponse->getConversationId(),
            'status' => $requestResponse->getStatus(),
        ]);

        try {
            $iyzicoOrderJob->save();
        } catch (Exception $e) {
            $this->errorLogger->critical($e->getMessage());
        }
    }

    /**
     * Store Session Data
     *
     * This function is responsible for storing the session data.
     *
     * @param $customerMail
     * @param $customerBasketId
     * @return void
     */
    private function storeSessionData($customerMail, $customerBasketId): void
    {
        $this->customerSession->setEmail($customerMail);
        $this->checkoutSession->setGuestQuoteId($customerBasketId);
    }
}
