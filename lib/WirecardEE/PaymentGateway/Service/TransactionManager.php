<?php
/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/LICENSE
 */

namespace WirecardEE\PaymentGateway\Service;

use Mage_Sales_Model_Order_Payment_Transaction;
use Psr\Log\LoggerInterface;
use Wirecard\PaymentSdk\BackendService;
use Wirecard\PaymentSdk\Response\Response;
use Wirecard\PaymentSdk\Transaction\Operation;
use Wirecard\PaymentSdk\Transaction\Transaction;
use WirecardEE\PaymentGateway\Mapper\ResponseMapper;
use WirecardEE\PaymentGateway\Payments\PaymentInterface;

/**
 * @since 1.0.0
 */
class TransactionManager
{
    const TYPE_INITIAL = 'initial';
    const TYPE_NOTIFY = 'notify';
    const TYPE_BACKEND = 'backend';
    const TYPE_RETURN = 'return';
    const TYPE_INITIAL_REQUEST = 'initial-request';

    // Key for type in additional information in transactions
    const TYPE_KEY = 'source_type';

    const REFUNDABLE_BASKET_KEY = 'refundable_basket';
    const ADDITIONAL_AMOUNT_KEY = 'additional';

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     *
     * @since 1.0.0
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * This method creates transactions following a simple rule: initial and backend transactions are always saved
     * and overwritten by notifications.
     *
     * @param string                  $type
     *
     * @param \Mage_Sales_Model_Order $order
     * @param Response                $response
     * @param array                   $context
     *
     * @return void
     * @throws \Mage_Core_Exception
     * @since 1.0.0
     */
    public function createTransaction(
        $type,
        \Mage_Sales_Model_Order $order,
        Response $response,
        array $context = []
    ) {
        $mageTransactionModel = $this->getOrderPaymentTransactionModel();
        $responseMapper       = new ResponseMapper($response);
        $transactionId        = $responseMapper->getTransactionId();
        $parentTransactionId  = $responseMapper->getParentTransactionId();

        if (! $transactionId) {
            // We're not handling responses without transaction ids at all.
            return null;
        }

        $mageTransactionModel->setOrderPaymentObject($order->getPayment());

        switch ($type) {
            case self::TYPE_INITIAL_REQUEST:
            case self::TYPE_INITIAL:
            case self::TYPE_RETURN:
            case self::TYPE_BACKEND:
                // Initial transactions are always saved as new transactions.
                if ($mageTransactionModel->loadByTxnId($transactionId)->getId()) {
                    // In some rare cases a notification might has already been arrived before the initial transaction.
                    // Since we don't want initial transactions to overwrite notifications we're going to leave here.
                    return;
                }

                $mageTransactionModel->setTxnId($transactionId);
                if ($parentTransactionId) {
                    $parentTransactionMageModel = $this->findTransactionById($order, $parentTransactionId);
                    if ($parentTransactionMageModel && $parentTransactionMageModel->getId()) {
                        $mageTransactionModel->setParentId($parentTransactionMageModel->getId());
                        $mageTransactionModel->setParentTxnId($parentTransactionId, $mageTransactionModel->getTxnId());
                    }
                }

                $mageTransactionModel->setTxnType(self::getMageTransactionType($response->getTransactionType()));
                $mageTransactionModel->setData('payment_method', $responseMapper->getPaymentMethod());
                $mageTransactionModel->setAdditionalInformation(
                    Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                    array_merge($response->getData(), [
                        self::TYPE_KEY => $type,
                    ], $context)
                );

                try {
                    $mageTransactionModel->save();
                } catch (\Exception $e) {
                    // Catching possible race conditions: in case the notification has arrived by now we're not going
                    // to overwrite it by an initial transaction.
                    $this->logger->info("Caught possible race condition by not saving transaction ($transactionId)");
                }

                $this->logger->info("Created transaction (type: $type) for transaction $transactionId");

                return;

            case self::TYPE_NOTIFY:
                // Since notifications are the source of truth for transactions they're overwriting the
                // initial transaction.
                $mageTransactionModel->loadByTxnId($transactionId);

                // Be sure not to overwrite notifications!
                if ($mageTransactionModel->getId()) {
                    $additionalInformation = $mageTransactionModel->getAdditionalInformation(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
                    );
                    if (! empty($additionalInformation[self::TYPE_KEY])
                        && $additionalInformation[self::TYPE_KEY] === self::TYPE_NOTIFY) {
                        return;
                    }
                    // Be sure to keep refundable basket information
                    if (isset($additionalInformation[self::REFUNDABLE_BASKET_KEY])) {
                        $context = array_merge(
                            $context,
                            [self::REFUNDABLE_BASKET_KEY => $additionalInformation[self::REFUNDABLE_BASKET_KEY]]
                        );
                    }
                }

                $mageTransactionModel->setTxnId($transactionId);
                if ($parentTransactionId) {
                    $parentTransactionMageModel = $this->findTransactionById($order, $parentTransactionId);
                    if ($parentTransactionMageModel && $parentTransactionMageModel->getId()) {
                        $mageTransactionModel->setParentId($parentTransactionMageModel->getId());
                        $mageTransactionModel->setParentTxnId($parentTransactionId, $mageTransactionModel->getTxnId());
                    }
                }

                $mageTransactionModel->setTxnType(self::getMageTransactionType($response->getTransactionType()));
                $mageTransactionModel->setData('payment_method', $responseMapper->getPaymentMethod());
                $mageTransactionModel->setAdditionalInformation(
                    Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                    array_merge($response->getData(), [
                        self::TYPE_KEY => $type,
                    ], $context)
                );

                try {
                    $mageTransactionModel->save();
                } catch (\Exception $e) {
                    // Being unable to save at this point is very likely due to a transaction id collision, which means
                    // an initial transaction with this id has been saved during the execution of this method. Let's
                    // try again to find this transaction and overwrite it.
                    if ($mageTransactionModel->loadByTxnId($transactionId)->getId()) {
                        $mageTransactionModel->setTxnId($transactionId);
                        $mageTransactionModel->setTxnType(
                            self::getMageTransactionType($response->getTransactionType())
                        );
                        $mageTransactionModel->setAdditionalInformation(
                            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                            array_merge($response->getData(), [
                                self::TYPE_KEY => $type,
                            ])
                        );

                        $mageTransactionModel->save();
                    }

                    $this->logger->info("Unable to save transaction ($transactionId)");
                }

                $this->logger->info("Replaced transaction (" . $mageTransactionModel->getId() . ") from notify");

                return;
        }

        $this->logger->error("Unable to create transaction due to unknown type ($type)");
        throw new \RuntimeException("Unknown transaction type");
    }

    /**
     * @param \Mage_Sales_Model_Order $order
     *
     * @return \Mage_Sales_Model_Order_Payment_Transaction|null
     *
     * @since 1.0.0
     */
    public function findInitialNotification(\Mage_Sales_Model_Order $order)
    {
        try {
            /** @var \Mage_Sales_Model_Resource_Order_Payment_Transaction_Collection $transactions */
            $transactions = \Mage::getResourceModel('sales/order_payment_transaction_collection');
            $transactions->addOrderIdFilter($order->getId());
            $transactions->setOrder('transaction_id', 'ASC');

            if (! $transactions->count() === 0) {
                return null;
            }

            foreach ($transactions as $transaction) {
                /** @var \Mage_Sales_Model_Order_Payment_Transaction $transaction */
                $additionalInformation = $transaction->getAdditionalInformation(
                    \Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
                );
                if (empty($additionalInformation[TransactionManager::TYPE_KEY])) {
                    continue;
                }
                if ($additionalInformation[TransactionManager::TYPE_KEY] === TransactionManager::TYPE_NOTIFY
                    && $transaction->getTxnType() !== Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT) {
                    return $transaction;
                }
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    /**
     * @param \Mage_Sales_Model_Order $order
     *
     * @param PaymentInterface        $payment
     * @param BackendService          $backendService
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction[]
     */
    public function findRefundableTransactions(
        \Mage_Sales_Model_Order $order,
        PaymentInterface $payment,
        BackendService $backendService
    ) {
        try {
            $refundableTransactions = [];
            $transactions           = \Mage::getResourceModel('sales/order_payment_transaction_collection');
            $transactions->addOrderIdFilter($order->getId());
            $transactions->setOrder('transaction_id', 'ASC');

            if (! $transactions->count() === 0) {
                return null;
            }

            foreach ($transactions as $transaction) {
                /** @var \Mage_Sales_Model_Order_Payment_Transaction $transaction */
                $additionalInformation = $transaction->getAdditionalInformation(
                    \Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
                );
                if ($additionalInformation[self::TYPE_KEY] !== self::TYPE_NOTIFY
                    || empty($additionalInformation[self::REFUNDABLE_BASKET_KEY])) {
                    continue;
                }

                $backendTransaction = $payment->getBackendTransaction(
                    $order,
                    Operation::REFUND,
                    $transaction
                );
                $backendTransaction->setParentTransactionId($transaction->getTxnId());

                if (! array_key_exists(
                    Operation::REFUND,
                    $backendService->retrieveBackendOperations($backendTransaction))
                ) {
                    continue;
                }

                $refundableTransactions[] = $transaction;
            }

            return $refundableTransactions;
        } catch (\Exception $e) {
        }

        return [];
    }

    /**
     * @param $transactionType
     *
     * @return string
     *
     * @since 1.0.0
     */
    public static function getMageTransactionType($transactionType)
    {
        switch ($transactionType) {
            case Transaction::TYPE_DEBIT:
            case Transaction::TYPE_PURCHASE:
                return Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
            case Transaction::TYPE_AUTHORIZATION:
                return Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;
            case Transaction::TYPE_VOID_AUTHORIZATION:
            case Transaction::TYPE_VOID_CAPTURE:
            case Transaction::TYPE_VOID_DEBIT:
            case Transaction::TYPE_VOID_PURCHASE:
                return Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID;
            case Transaction::TYPE_CAPTURE_AUTHORIZATION:
                return Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
            case Transaction::TYPE_REFUND_CAPTURE:
            case Transaction::TYPE_REFUND_DEBIT:
            case Transaction::TYPE_REFUND_PURCHASE:
            case Transaction::TYPE_CREDIT:
                return Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND;
        }

        return Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT;
    }

    /**
     * @param \Mage_Sales_Model_Order $order
     * @param string                  $transactionId
     *
     * @return bool|Mage_Sales_Model_Order_Payment_Transaction
     *
     * @since 1.0.0
     */
    protected function findTransactionById(\Mage_Sales_Model_Order $order, $transactionId)
    {
        try {
            /** @var \Mage_Sales_Model_Resource_Order_Payment_Transaction_Collection $collection */
            $transactions = \Mage::getResourceModel('sales/order_payment_transaction_collection');
            $transactions->setOrderFilter($order);
            $transactions->addPaymentIdFilter($order->getPayment()->getId());

            foreach ($transactions as $transaction) {
                /** @var \Mage_Sales_Model_Order_Payment_Transaction $transaction */
                if ($transaction->getTxnId() === $transactionId) {
                    return $transaction;
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * @return Mage_Sales_Model_Order_Payment_Transaction|\Mage_Core_Model_Abstract
     *
     * @since 1.0.0
     */
    protected function getOrderPaymentTransactionModel()
    {
        return \Mage::getModel('sales/order_payment_transaction');
    }

    /**
     * @return \Mage_Core_Helper_Abstract|\WirecardEE_PaymentGateway_Helper_Data
     *
     * @since 1.0.0
     */
    protected function getHelper()
    {
        return \Mage::helper('paymentgateway');
    }
}
