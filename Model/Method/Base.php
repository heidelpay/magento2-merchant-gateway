<?php

namespace Heidelpay\Gateway2\Model\Method;

use Heidelpay\Gateway2\Model\Config;
use heidelpayPHP\Heidelpay;
use heidelpayPHP\Resources\Basket;
use heidelpayPHP\Resources\EmbeddedResources\BasketItem;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;

class Base extends AbstractMethod
{
    const KEY_REDIRECT_URL = 'redirect_url';
    const KEY_RESOURCE_ID = 'resource_id';

    protected $_code = Config::METHOD_BASE;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var boolean
     */
    protected $_canCapture = false;

    /**
     * @var boolean
     */
    protected $_canCapturePartial = false;

    /**
     * @var boolean
     */
    protected $_canRefund = false;

    /**
     * @var boolean
     */
    protected $_canRefundInvoicePartial = false;

    /**
     * @var boolean
     */
    protected $_canUseInternal = false;

    /**
     * @var Heidelpay
     */
    protected $_client;

    /**
     * @var Config
     */
    protected $_moduleConfig;

    /**
     * @var StoreInterface
     */
    protected $_store;

    /**
     * @var UrlInterface
     */
    protected $_urlBuilder;

    /**
     * Base constructor.
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param Config $moduleConfig
     * @param StoreInterface $store
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param DirectoryHelper|null $directory
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        Config $moduleConfig,
        StoreInterface $store,
        UrlInterface $urlBuilder,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    )
    {
        $this->_moduleConfig = $moduleConfig;
        $this->_store = $store;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
        $this->_urlBuilder = $urlBuilder;
    }

    /**
     * Returns the configuration for the checkout page.
     *
     * @return array
     */
    public function getFrontendConfig()
    {
        return [
            'publicKey' => $this->_moduleConfig->getPublicKey(),
        ];
    }

    /**
     * Returns a Basket for the given Order.
     *
     * @param Order $order
     *
     * @return Basket
     */
    protected function _getBasketFromOrder(Order $order)
    {
        $basket = new Basket();
        $basket->setAmountTotal($order->getGrandTotal());
        $basket->setAmountTotalDiscount($order->getDiscountAmount());
        $basket->setAmountTotalVat($order->getTaxAmount());
        $basket->setCurrencyCode($order->getOrderCurrencyCode());
        $basket->setOrderId($order->getIncrementId());

        foreach ($order->getAllVisibleItems() as $orderItem) {
            $totalInclTax = $orderItem->getRowTotalInclTax();
            if ($totalInclTax === null) {
                $totalInclTax = $orderItem->getRowTotal();
            }

            $basketItem = new BasketItem();
            $basketItem->setAmountNet($orderItem->getRowTotal());
            $basketItem->setAmountDiscount($orderItem->getDiscountAmount());
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
     * Returns the gateway client.
     *
     * @return Heidelpay
     */
    protected function _getClient()
    {
        if ($this->_client === null) {
            $this->_client = $this->_moduleConfig->getHeidelpayClient($this->_store->getLocaleCode());
        }

        return $this->_client;
    }

    /**
     * Returns an absolute URL for the given route.
     *
     * @param string $routePath
     *
     * @return string
     */
    protected function _getUrl($routePath)
    {
        return $this->_urlBuilder->getUrl($routePath);
    }

    /**
     * @return string
     */
    protected function _getAuthorizationCallbackUrl()
    {
        return $this->_getUrl('hpg2/payment/authorizationCallback');
    }

    /**
     * @return string
     */
    protected function _getChargeCallbackUrl()
    {
        return $this->_getUrl('hpg2/payment/chargeCallback');
    }
}