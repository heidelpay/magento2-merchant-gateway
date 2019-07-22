<?php

namespace Heidelpay\Gateway2\Model\Method;

use Heidelpay\Gateway2\Model\Config;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Resources\Payment;
use heidelpayPHP\Resources\TransactionTypes\Cancellation;
use heidelpayPHP\Resources\TransactionTypes\Charge;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class Creditcard extends Base
{
    const KEY_RESOURCE_ID = 'resource_id';

    protected $_code = Config::METHOD_CREDITCARD;

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Authorize payment abstract method
     *
     * @param DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     * @throws HeidelpayApiException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @deprecated 100.2.0
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        if (!$this->canAuthorize()) {
            throw new LocalizedException(__('The authorize action is not available.'));
        }

        /** @var string $resourceId */
        $resourceId = $payment->getAdditionalInformation(self::KEY_RESOURCE_ID);

        /** @var Order $order */
        $order = $payment->getOrder();
        $order->setState(Order::STATE_PENDING_PAYMENT);

        $authorization = $this->_getClient()->authorize(
            $amount,
            $order->getOrderCurrencyCode(),
            $resourceId,
            $this->_getUrl('checkout/onepage/success'),
            $order->getCustomerId(),
            $order->getIncrementId(),
            null,
            $this->_getBasketFromOrder($order),
            null
        );

        if ($authorization->isError()) {
            throw new LocalizedException(__('Failed to authorize payment.'));
        }

        return $this;
    }

    /**
     * Capture payment abstract method
     *
     * @param DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     * @throws HeidelpayApiException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @deprecated 100.2.0
     */
    public function capture(InfoInterface $payment, $amount)
    {
        if (!$this->canCapture()) {
            throw new LocalizedException(__('The capture action is not available.'));
        }

        /** @var Order $order */
        $order = $payment->getOrder();
        $order->setState(Order::STATE_PROCESSING);

        /** @var Payment $hpPayment */
        $hpPayment = null;

        try {
            $hpPayment = $this->_getClient()->fetchPaymentByOrderId($order->getIncrementId());
        } catch (HeidelpayApiException $e) {
            $hpPayment = null;
        }

        if ($hpPayment !== null) {
            $charge = $this->_getClient()->chargeAuthorization($hpPayment->getId(), $amount);
        } else {
            $charge = $this->_captureDirect($payment, $amount);
        }

        if ($charge->isError()) {
            throw new LocalizedException(__('Failed to charge payment.'));
        }

        return $this;
    }

    /**
     * Captures a payment with an direct charge.
     *
     * @param InfoInterface $payment
     * @param $amount
     * @return Charge
     * @throws HeidelpayApiException
     */
    protected function _captureDirect(InfoInterface $payment, $amount)
    {
        /** @var Order $order */
        $order = $payment->getOrder();

        /** @var string $resourceId */
        $resourceId = $payment->getAdditionalInformation(self::KEY_RESOURCE_ID);

        return $this->_getClient()->charge(
            $amount,
            $order->getOrderCurrencyCode(),
            $resourceId,
            $this->_getUrl('checkout/onepage/success'),
            $order->getCustomerId(),
            $order->getIncrementId(),
            null,
            $this->_getBasketFromOrder($order),
            null,
            null,
            null
        );
    }

    /**
     * Cancel specified amount for payment
     *
     * @param DataObject|InfoInterface $payment
     * @return $this
     * @throws LocalizedException
     * @throws HeidelpayApiException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @deprecated 100.2.0
     */
    public function cancel(InfoInterface $payment)
    {
        /** @var Order $order */
        $order = $payment->getOrder();

        /** @var Payment $hpPayment */
        $hpPayment = $this->_getClient()->fetchPaymentByOrderId($order->getIncrementId());

        /** @var Cancellation $refund */
        $cancellation = $hpPayment->cancel();

        if ($cancellation->isError()) {
            throw new LocalizedException(__('Failed to cancel payment.'));
        }

        return $this;
    }
}