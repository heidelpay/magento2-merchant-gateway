<?php

namespace Heidelpay\MGW\Helper;

use Heidelpay\MGW\Model\Config;
use heidelpayPHP\Constants\ApiResponseCodes;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Heidelpay;
use heidelpayPHP\Resources\Basket;
use heidelpayPHP\Resources\Customer;
use heidelpayPHP\Resources\CustomerFactory;
use heidelpayPHP\Resources\EmbeddedResources;
use heidelpayPHP\Resources\EmbeddedResources\BasketItem;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Helper for generating heidelpay resources for new orders
 *
 * Copyright (C) 2019 heidelpay GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link  https://docs.heidelpay.com/
 *
 * @author Justin Nuß
 *
 * @package  heidelpay/magento2-merchant-gateway
 */
class Order
{
    /**
     * @var Config
     */
    private $_moduleConfig;

    /**
     * @var StoreInterface
     */
    private $_store;

    /**
     * Order constructor.
     * @param Config $moduleConfig
     * @param StoreInterface $store
     */
    public function __construct(Config $moduleConfig, StoreInterface $store)
    {
        $this->_moduleConfig = $moduleConfig;
        $this->_store = $store;
    }

    /**
     * Returns a Basket for the given Order.
     *
     * @param OrderModel $order
     *
     * @return Basket
     */
    public function createBasketForOrder(OrderModel $order): Basket
    {
        $basket = new Basket();
        $basket->setAmountTotalGross($order->getGrandTotal());
        $basket->setAmountTotalDiscount(abs($order->getDiscountAmount()));
        $basket->setCurrencyCode($order->getOrderCurrencyCode());
        $basket->setOrderId($order->getIncrementId());

        foreach ($order->getAllVisibleItems() as $orderItem) {
            /** @var OrderModel\Item $orderItem */

            $totalInclTax = $orderItem->getRowTotalInclTax();
            if ($totalInclTax === null) {
                $totalInclTax = $orderItem->getRowTotal();
            }

            $basketItem = new BasketItem();
            $basketItem->setAmountNet($orderItem->getRowTotal());
            $basketItem->setAmountDiscount(abs($orderItem->getDiscountAmount()));
            $basketItem->setAmountGross($totalInclTax);
            $basketItem->setAmountPerUnit($orderItem->getPrice());
            $basketItem->setAmountVat($orderItem->getTaxAmount());
            $basketItem->setQuantity($orderItem->getQtyOrdered());
            $basketItem->setTitle($orderItem->getName());

            $basket->addBasketItem($basketItem);
        }

        return $basket;
    }

    /**
     * Returns metadata for the given order.
     *
     * @param OrderModel $order
     * @return array
     */
    public function createMetadataForOrder(OrderModel $order): array
    {
        return [
            'customer_id' => $order->getCustomerId(),
            'customer_group_id' => $order->getCustomerGroupId(),
            'store_id' => $order->getStoreId(),
        ];
    }

    /**
     * Returns a new or updated Heidelpay Customer resource for the given quote.
     *
     * @param Quote $quote
     * @param string $email
     * @return Customer
     * @throws HeidelpayApiException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function createCustomerFromQuote(Quote $quote, string $email): ?Customer
    {
        /** @var Quote\Address $billingAddress */
        $billingAddress = $quote->getBillingAddress();

        /** @var Customer $customer */
        $customer = CustomerFactory::createCustomer(
            $billingAddress->getFirstname(),
            $billingAddress->getLastname()
        );

        $customer->setEmail($email);
        $customer->setPhone($billingAddress->getTelephone());

        $company = $billingAddress->getCompany();
        if (!empty($company)) {
            $customer->setCompany($company);
        }

        $this->updateGatewayAddressFromMagento($customer->getBillingAddress(), $billingAddress);
        $this->updateGatewayAddressFromMagento($customer->getShippingAddress(), $quote->getShippingAddress());

        /** @var Heidelpay $client */
        $client = $this->_moduleConfig->getHeidelpayClient();

        return $client->createCustomer($customer);
    }

    /**
     * Updates an Heidelpay address from an address in Magento.
     *
     * @param EmbeddedResources\Address $gatewayAddress
     * @param Quote\Address $magentoAddress
     */
    private function updateGatewayAddressFromMagento(
        EmbeddedResources\Address $gatewayAddress,
        Quote\Address $magentoAddress
    ): void
    {
        $streetLines = $magentoAddress->getStreet();
        $streetLines = array_map('trim', $streetLines);
        $streetLines = array_unique($streetLines);
        $street = implode(' ', $streetLines);

        $gatewayAddress->setCity($magentoAddress->getCity());
        $gatewayAddress->setCountry($magentoAddress->getCountry());
        $gatewayAddress->setStreet($street);
        $gatewayAddress->setZip($magentoAddress->getPostcode());
    }
}
