<?php
/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/LICENSE
 */

/**
 * ThreeDS Payment helper object, can be accessed with Mage::helper('paymentgateway/threeds')
 *
 * @since 1.3.0
 */
class WirecardEE_PaymentGateway_Helper_Threeds extends Mage_Payment_Helper_Data
{

    /**
     * Get last login timestamp
     * Depends on shop configuration
     * Configuration->Advanced->System => set Log => Enable Log to "Yes".
     *
     * @return int
     */
    public function getCustomerLastLogin()
    {
        $customer = $this->getCustomerSession()->getCustomer();
        /** @var Mage_Log_Model_Customer $customerLog */
        $customerLog = Mage::getModel('log/customer');
        $customerLog->loadByCustomer($customer->getId());

        return $customerLog->getLoginAtTimestamp();
    }

    /**
     * get customer session
     *
     * @return Mage_Customer_Model_Session
     */
    public function getCustomerSession()
    {
        /** @var Mage_Customer_Model_Session $customerSession */
        $customerSession = Mage::getSingleton('customer/session');

        return $customerSession;
    }

    /**
     * Get configured challenge indicator
     *
     * @return mixed|string
     * @throws Exception
     */
    public function getChallengeIndicator()
    {
        return Mage::getStoreConfig('payment/wirecardee_paymentgateway_creditcard/challenge_indicator');
    }

    /**
     * Check if a token is a new one
     * handle the special token ids: 'wirecardee--new-card' and 'wirecardee--new-card-save', the indicating
     * a non-one-click checkout and a one-click-checkout using a new card, skip the db query in this case
     *
     * @param $tokenId
     *
     * @return bool
     * @throws Exception
     */
    public function isNewToken($tokenId)
    {
        if ($tokenId === null) {
            return false;
        }

        // return true for non-one-click checkout and for a new card to be saved
        if (in_array($tokenId, ['wirecardee--new-card', 'wirecardee--new-card-save'])) {
            return true;
        }

        /** @var WirecardEE_PaymentGateway_Model_Resource_CreditCardVaultToken_Collection $vaultTokenModelColl */
        $vaultTokenModelColl = Mage::getModel('paymentgateway/creditCardVaultToken')->getCollection();

        $vaultTokenModelColl->getTokenForCustomer($tokenId, $this->getCustomerSession()->getCustomerId());

        return $vaultTokenModelColl->getFirstItem()->isEmpty();
    }

    /**
     * creation date of used card token
     *
     * @param $tokenId
     *
     * @return mixed|string
     * @throws Exception
     */
    public function getCardCreationDate($tokenId)
    {
        if ($tokenId === null) {
            return new DateTime();
        }

        if (!$this->getCustomerSession()->isLoggedIn()) {
            return new DateTime();
        }

        /** @var WirecardEE_PaymentGateway_Model_Resource_CreditCardVaultToken_Collection $vaultTokenModelColl */
        $vaultTokenModelColl = Mage::getModel('paymentgateway/creditCardVaultToken')->getCollection();

        $vaultTokenModelColl->getTokenForCustomer($tokenId, $this->getCustomerSession()->getCustomerId());
        /** @var WirecardEE_PaymentGateway_Model_CreditCardVaultToken $vaultToken */
        $vaultToken = $vaultTokenModelColl->getFirstItem();
        if ($vaultToken->isEmpty()) {
            return new DateTime();
        }

        return $vaultToken->getCreatedAt();
    }

    /**
     * return datetime of first address usage
     *
     * @param $addressId
     *
     * @return DateTime|null
     * @throws Exception
     */
    public function getAddressFirstUsed($addressId)
    {
        if (is_null($addressId)) {
            return null;
        }

        $orderCollection = $this->getMageOrderCollection();

        $orderCollection->join(['oa' => 'sales/order_address'], 'oa.entity_id = main_table.shipping_address_id');

        $orderCollection->addFieldToFilter('oa.customer_address_id', $addressId);

        $orderCollection->addAttributeToSelect('created_at')
            ->addAttributeToSort('created_at')
            ->setPageSize(1)
            ->setCurPage(1);

        $first = $orderCollection->getFirstItem();
        if ($first->isEmpty()) {
            return null;
        }

        return new DateTime($first->getCreatedAt());
    }

    /**
     * retreive successful number of orders within the last 6 months
     *
     * @param $customerId
     *
     * @return int
     * @throws Exception
     */
    public function getSuccessfulOrdersLastSixMonths($customerId)
    {
        $orderCollection = $this->getMageOrderCollection();

        $orderCollection->addFieldToFilter('customer_id', $customerId);

        $now       = new DateTime();
        $dateStart = $now->sub(new DateInterval('P6M'))->format('Y-m-d H:i:s');
        $orderCollection->addFieldToFilter('created_at', ['from' => $dateStart]);

        $successfulStates = [
            Mage_Sales_Model_Order::STATE_COMPLETE,
            Mage_Sales_Model_Order::STATE_PROCESSING,
            Mage_Sales_Model_Order::STATE_CLOSED,
            Mage_Sales_Model_Order::STATE_CANCELED
        ];
        $orderCollection->addFieldToFilter('state', ['in' => $successfulStates]);

        return $orderCollection->count();
    }

    /**
     * check if order has at least one reordered item
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return bool
     */
    public function hasReorderedItems(Mage_Sales_Model_Order $order)
    {
        /** @var Mage_Sales_Model_Order_Item[] */
        $items = $order->getAllVisibleItems();

        $productIds = array_map(function ($i) {
            /** @var Mage_Sales_Model_Order_Item $i */
            return $i->getProductId();
        }, $items);

        $orderCollection = $this->getMageOrderCollection();

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');

        $joinTable = $resource->getTableName('sales/order_item');

        $sql = $orderCollection->getSelectCountSql()
            ->join(['oi' => $joinTable], 'oi.order_id = main_table.entity_id', [])
            ->where('main_table.customer_id = ?', $this->getCustomerSession()->getCustomerId())
            ->where('oi.product_id IN (?)', $productIds);

        return $orderCollection->getConnection()->fetchOne($sql) > 0;
    }

    /**
     * check if order has at least one virtual item (electronic delivery)
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return bool
     */
    public function hasVirtualItems(Mage_Sales_Model_Order $order)
    {
        /** @var Mage_Sales_Model_Order_Item[] */
        $items = $order->getAllVisibleItems();

        foreach ($items as $item) {
            /** @var Mage_Sales_Model_Order_Item $item */
            if ($item->getIsVirtual()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Mage_Sales_Model_Resource_Order_Collection
     */
    protected function getMageOrderCollection()
    {
        return Mage::getModel('sales/order')->getCollection();
    }
}
