<?php

/**
 * Class Mcashp_Affiliate_Helper_Data
 */
class Mcashp_Affiliate_Helper_Data
    extends Mage_Core_Helper_Abstract
    implements Mcashp_Affiliate_Helper_DataInterface
{
    /**
     * @param string $config
     *
     * @return mixed
     */
    protected function getConfig($config)
    {
        return Mage::getStoreConfig($config);
    }

    /**
     * @return bool
     */
    public function getConfigActive()
    {
        return (bool) $this->getConfig(self::CONFIG_ACTIVE);
    }

    /**
     * @return bool
     */
    public function getConfigTest()
    {
        return (bool) $this->getConfig(self::CONFIG_TEST);
    }

    /**
     * @return mixed
     */
    public function getConfigCommission()
    {
        return $this->getConfig(self::CONFIG_COMMISSION);
    }

    /**
     * @return mixed
     */
    public function getConfigCookieLifetime()
    {
        return $this->getConfig(self::CONFIG_COOKIE_LIFETIME);
    }

    /**
     * @return string|null
     */
    public function getTrackingRequest()
    {
        $tracking = Mage::app()->getRequest()->getParam(self::CONFIG_COOKIE_NAME);
        if (1 !== preg_match(self::ATTRIBUTE_TRACKING_REGEX, $tracking)) {
            return null;
        }

        return $tracking;
    }

    /**
     * @return string
     */
    public function getTrackingCookie()
    {
        return Mage::getModel('core/cookie')->get(self::CONFIG_COOKIE_NAME);
    }

    /**
     * @return bool
     */
    public function checkTrackingRequest()
    {
        $tracking = $this->getTrackingRequest();
        if (null === $tracking) {
            return false;
        }

        if ($this->getConfigActive()) {
            $this->setTracking($tracking);
        }

        return true;
    }

    /**
     * @param string   $tracking
     * @param int|null $customerId
     * @param int|null $quoteId
     */
    public function setTracking($tracking, $customerId = null, $quoteId = null)
    {
        if (null === $customerId && Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        }

        if (null === $quoteId) {
            $quoteId = Mage::getModel('checkout/cart')->getQuote()->getId();
        }

        $lifetime = (int) $this->getConfig(self::CONFIG_COOKIE_LIFETIME);
        $lifetimeMin = self::CONFIG_COOKIE_LIFETIME_MIN;
        $lifetimeMax = self::CONFIG_COOKIE_LIFETIME_MAX;
        if ( ! is_numeric($lifetime) || ! $lifetime < $lifetimeMin) {
            $lifetime = $lifetimeMin;
        } elseif ($lifetime > $lifetimeMax) {
            $lifetime = $lifetimeMax;
        }
        $lifetime = (int) $lifetime * self::CONFIG_COOKIE_LIFETIME_MP;

        Mage::getModel('core/cookie')->set(self::CONFIG_COOKIE_NAME, $tracking, $lifetime, '/');

        $this->setQuoteTracking($quoteId, $tracking);
        $this->setCustomerTracking($customerId, $tracking);
    }

    /**
     * @param string|null $customerId
     *
     * @return string|null
     */
    public function getTracking($customerId = null)
    {
        $tracking = $this->getTrackingCookie();
        if ( ! $tracking) {
            $tracking = $this->getCustomerTracking($customerId);
        }

        return $tracking;
    }

    /**
     * @param string|null $customerId
     *
     * @return \Mage_Customer_Model_Customer|null
     */
    public function getCustomer($customerId = null)
    {
        if (null === $customerId && Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        }

        if ( ! is_numeric($customerId)) {
            return null;
        }

        return Mage::getModel('customer/customer')->load($customerId);
    }

    /**
     * @param string|null $customerId
     *
     * @return string|null
     */
    public function getCustomerTracking($customerId = null)
    {
        if ( ! $customer = $this->getCustomer($customerId)) {
            return null;
        }

        return $customer->getData(self::ATTRIBUTE_TRACKING);
    }

    /**
     * @param int    $customerId
     * @param string $tracking
     *
     * @return bool
     */
    public function setCustomerTracking($customerId, $tracking)
    {
        if ( ! $customerId || ! $tracking) {
            return false;
        }

        if ( ! $customer = $this->getCustomer($customerId)) {
            return false;
        }

        $customer->setData(self::ATTRIBUTE_TRACKING, $tracking)->save();

        return true;
    }

    /**
     * @param string|null $quoteId
     *
     * @return \Mage_Sales_Model_Quote|null
     */
    public function getQuote($quoteId = null)
    {
        if (null === $quoteId) {
            $quoteId = Mage::getModel('checkout/cart')->getQuote()->getId();
        }

        if ( ! is_numeric($quoteId)) {
            return null;
        }

        return Mage::getModel('sales/quote')->load($quoteId);
    }

    /**
     * @param int    $quoteId
     * @param string $tracking
     *
     * @return bool
     */
    public function setQuoteTracking($quoteId, $tracking)
    {
        if ( ! $quoteId || ! $tracking) {
            return false;
        }

        /** @var \Mage_Sales_Model_Quote $quote */
        if ( ! $quote = $this->getQuote($quoteId)) {
            return false;
        }

        $quote->setMcashpTracking($tracking);
        $quote->save();

        return true;
    }

    /**
     * @param string|null $orderId
     *
     * @return \Mage_Sales_Model_Order|null
     */
    public function getOrder($orderId = null)
    {
        if ( ! is_numeric($orderId)) {
            return null;
        }

        return Mage::getModel('sales/order')->loadByIncrementId($orderId);
    }

    /**
     * @param string $action
     * @param string $tracking
     * @param string $payoutAmount
     * @param string $payoutAmountFormat
     * @param string $type
     *
     * @return mixed|string
     */
    public function mcashpEvent($action, $tracking, $payoutAmount, $payoutAmountFormat = 'float', $type = 'rev')
    {
        $apiKey = $this->getConfig('mcashpcore/webmaster/api');
        if ( ! $apiKey) {
            return false;
        }

        $data = array(
            'k' => $action,
            'type' => $type,
            'tracking' => $tracking,
            'payout_amount' => $payoutAmount,
            'payout_amount_format' => $payoutAmountFormat,
            'test' => $this->getConfigTest() ? 1 : 0,
        );

        $url = 'https://www.mcashp.com/api/' . $apiKey . '/stats/new';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }

        curl_close($ch);

        return $result;
    }
}
