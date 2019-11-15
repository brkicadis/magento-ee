<?php
/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/LICENSE
 */

namespace WirecardEE\PaymentGateway\Payments;

use Wirecard\PaymentSdk\Config\Config;
use Wirecard\PaymentSdk\Config\PaymentMethodConfig;
use Wirecard\PaymentSdk\Entity\Redirect;
use Wirecard\PaymentSdk\Transaction\Operation;
use Wirecard\PaymentSdk\Transaction\PtwentyfourTransaction;
use Wirecard\PaymentSdk\TransactionService;
use WirecardEE\PaymentGateway\Actions\Action;
use WirecardEE\PaymentGateway\Data\OrderSummary;
use WirecardEE\PaymentGateway\Data\PaymentConfig;
use WirecardEE\PaymentGateway\Payments\Contracts\ProcessPaymentInterface;

class PtwentyfourPayment extends Payment implements ProcessPaymentInterface
{
    const NAME = PtwentyfourTransaction::NAME;

    /**
     * @var PtwentyfourTransaction
     */
    private $transactionInstance;

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @return PtwentyfourTransaction
     *
     * @since 1.0.0
     */
    public function getTransaction()
    {
        if (! $this->transactionInstance) {
            $this->transactionInstance = new PtwentyfourTransaction();
        }
        return $this->transactionInstance;
    }

    /**
     * @param string $selectedCurrency
     *
     * @return Config
     *
     * @since 1.0.0
     */
    public function getTransactionConfig($selectedCurrency)
    {
        $config = parent::getTransactionConfig($selectedCurrency);
        $config->add(new PaymentMethodConfig(
            PtwentyfourTransaction::NAME,
            $this->getPaymentConfig()->getTransactionMAID(),
            $this->getPaymentConfig()->getTransactionSecret()
        ));
        return $config;
    }

    /**
     * @return PaymentConfig
     *
     * @since 1.0.0
     */
    public function getPaymentConfig()
    {
        $paymentConfig = new PaymentConfig(
            $this->getPluginConfig('api_url'),
            $this->getPluginConfig('api_user'),
            $this->getPluginConfig('api_password')
        );

        $paymentConfig->setTransactionMAID($this->getPluginConfig('api_maid'));
        $paymentConfig->setTransactionSecret($this->getPluginConfig('api_secret'));
        $paymentConfig->setTransactionOperation($this->getPluginConfig('transaction_type'));
        $paymentConfig->setSendBasket($this->getPluginConfig('send_basket'));
        $paymentConfig->setFraudPrevention($this->getPluginConfig('fraud_prevention'));
        $paymentConfig->setOrderIdentification($this->getPluginConfig('order_identification'));

        return $paymentConfig;
    }

    /**
     * @param OrderSummary $orderSummary
     * @param TransactionService $transactionService
     * @param Redirect $redirect
     *
     * @return null|Action
     *
     * @since 1.0.0
     */
    public function processPayment(
        OrderSummary $orderSummary,
        TransactionService $transactionService,
        Redirect $redirect
    ) {
        $transaction = $this->getTransaction();

        $transaction->setOrderDetail(sprintf(
            '%s - %.2f %s',
            $orderSummary->getOrder()->getRealOrderId(),
            $orderSummary->getOrder()->getBaseGrandTotal(),
            $orderSummary->getOrder()->getBaseCurrencyCode()
        ));

        return null;
    }

    /**
     * @return string
     *
     * @since 1.0.0
     */
    public function getRefundOperation()
    {
        return Operation::CANCEL;
    }
}
