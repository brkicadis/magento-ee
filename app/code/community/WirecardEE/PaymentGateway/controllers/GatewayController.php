<?php
/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/LICENSE
 */

use Psr\Log\LoggerInterface;
use Wirecard\PaymentSdk\BackendService;
use Wirecard\PaymentSdk\Entity\Redirect;
use Wirecard\PaymentSdk\Response\Response;
use Wirecard\PaymentSdk\Response\SuccessResponse;
use Wirecard\PaymentSdk\TransactionService;
use WirecardEE\PaymentGateway\Actions\Action;
use WirecardEE\PaymentGateway\Actions\ErrorAction;
use WirecardEE\PaymentGateway\Actions\RedirectAction;
use WirecardEE\PaymentGateway\Data\BasketMapper;
use WirecardEE\PaymentGateway\Data\OrderSummary;
use WirecardEE\PaymentGateway\Data\UserMapper;
use WirecardEE\PaymentGateway\Exception\UnknownActionException;
use WirecardEE\PaymentGateway\Service\NotificationHandler;
use WirecardEE\PaymentGateway\Service\PaymentFactory;
use WirecardEE\PaymentGateway\Service\PaymentHandler;
use WirecardEE\PaymentGateway\Service\ReturnHandler;
use WirecardEE\PaymentGateway\Service\TransactionManager;

class WirecardEE_PaymentGateway_GatewayController extends Mage_Core_Controller_Front_Action
{
    /**
     * Gets payment from `PaymentFactory`, assembles the `OrderSummary` and executes the payment through the
     * `PaymentHandler` service. Further action depends on the response from the handler.
     *
     * @return Mage_Core_Controller_Varien_Action
     * @throws Mage_Core_Model_Store_Exception
     * @throws UnknownActionException
     * @throws \WirecardEE\PaymentGateway\UnknownPaymentException
     */
    public function indexAction()
    {
        $paymentName = $this->getRequest()->getParam('method');
        $payment     = (new PaymentFactory())->create($paymentName);
        $handler     = new PaymentHandler(\Mage::app()->getStore(), $this->getLogger());
        $order       = $this->getCheckoutSession()->getLastRealOrder();

        $this->getHelper()->validateBasket();

        $action = $handler->execute(
            new TransactionManager($this->getLogger()),
            new OrderSummary(
                $payment,
                $order,
                new BasketMapper($order, $payment->getTransaction()),
                new UserMapper(
                    $order,
                    Mage::helper('paymentgateway')->getClientIp(),
                    Mage::app()->getLocale()->getLocaleCode()
                ),
                $this->getHelper()->getDeviceFingerprintId($payment->getPaymentConfig()->getTransactionMAID())
            ),
            new TransactionService(
                $payment->getTransactionConfig(),
                $this->getLogger()
            ),
            new Redirect(
                $this->getUrl('paymentgateway/gateway/return', ['method' => $paymentName]),
                $this->getUrl('paymentgateway/gateway/cancel', ['method' => $paymentName])
            ),
            $this->getUrl('paymentgateway/gateway/notify', ['method' => $paymentName])
        );

        return $this->handleAction($action);
    }

    /**
     * After paying the user gets redirected to this action, where the `ReturnHandler` takes care about what to do
     * next (e.g. redirecting to the "Thank you" page, rendering templates, ...).
     *
     * @return Mage_Core_Controller_Varien_Action
     * @throws UnknownActionException
     * @throws \WirecardEE\PaymentGateway\UnknownPaymentException
     */
    public function returnAction()
    {
        $returnHandler = new ReturnHandler($this->getLogger());
        $request       = $this->getRequest();
        $payment       = (new PaymentFactory())->create($request->getParam('method'));

        $this->getHelper()->validateBasket();

        try {
            $response = $returnHandler->handleRequest(
                $request,
                new TransactionService($payment->getTransactionConfig(), $this->getLogger())
            );

            $transactionManager = new TransactionManager($this->getLogger());
            $transactionManager->createTransaction(
                $this->getCheckoutSession()->getLastRealOrder(),
                $response
            );

            $action = $response instanceof SuccessResponse
                ? $this->updateOrder($returnHandler, $response)
                : $returnHandler->handleResponse($response);
        } catch (\Exception $e) {
            $this->getLogger()->error($e->getMessage());
            $action = new ErrorAction(0, 'Return processing failed');
        }

        return $this->handleAction($action);
    }

    /**
     * @param ReturnHandler $returnHandler
     * @param Response      $response
     *
     * @return RedirectAction
     */
    protected function updateOrder(ReturnHandler $returnHandler, Response $response)
    {
        $this->getHelper()->destroyDeviceFingerprintId();

        return $returnHandler->handleSuccess($response, $this->getUrl('checkout/onepage/success'));
    }

    /**
     * This method is called by Wirecard servers to modify the state of an order. Notifications are generally the
     * source of truth regarding orders, meaning the `NotificationHandler` will most likely overwrite things
     * by the `ReturnHandler`.
     */
    public function notifyAction()
    {
        $notificationHandler = new NotificationHandler($this->getLogger());
        $request             = $this->getRequest();
        $payment             = (new PaymentFactory())->create($request->getParam('method'));

        try {
            $backendService = new BackendService($payment->getTransactionConfig());
            $notification   = $backendService->handleNotification($request->getRawBody());

            $notificationHandler->handleResponse($notification, $backendService);
        } catch (\Exception $e) {
            $this->logException('Notification handling failed', $e);
        }
    }

    /**
     * In case a payment has been canceled this action is called. It basically restores the basket (if it has not been
     * cancelled!) and redirects the user to the checkout.
     *
     * @return Mage_Core_Controller_Varien_Action
     */
    public function cancelAction()
    {
        if ($order = $this->getCheckoutSession()->getLastRealOrder()) {
            if ($order->getStatus() !== Mage_Sales_Model_Order::STATE_CANCELED) {

                $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());

                if ($quote->getId()) {
                    $quote->setIsActive(1)
                          ->setReservedOrderId(null)
                          ->save();
                    $this->getCheckoutSession()->replaceQuote($quote);
                }
            }
        }

        return $this->_redirect('checkout/onepage');
    }

    /**
     * @param Action $action
     *
     * @return Mage_Core_Controller_Varien_Action
     * @throws UnknownActionException
     */
    protected function handleAction(Action $action)
    {
        if ($action instanceof RedirectAction) {
            return $this->_redirectUrl($action->getUrl());
        }

        if ($action instanceof ErrorAction) {
            exit($action->getMessage());
        }

        throw new UnknownActionException(get_class($action));
    }

    /**
     * @param           $message
     * @param Exception $exception
     */
    private function logException($message, \Exception $exception)
    {
        $this->getLogger()->error(
            $message . ' - ' . get_class($exception) . ': ' . $exception->getMessage()
        );
    }

    /**
     * @return false|Mage_Core_Model_Abstract|Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function getOrderPaymentTransaction()
    {
        return Mage::getModel('sales/order_payment_transaction');
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return Mage::registry('logger');
    }

    /**
     * @param       $route
     * @param array $params
     *
     * @return string
     */
    protected function getUrl($route, $params = [])
    {
        return Mage::getUrl($route, $params);
    }

    /**
     * @return Mage_Core_Helper_Abstract|WirecardEE_PaymentGateway_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('paymentgateway');
    }

    /**
     * @return Mage_Checkout_Model_Session|Mage_Core_Model_Abstract
     */
    protected function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }
}
