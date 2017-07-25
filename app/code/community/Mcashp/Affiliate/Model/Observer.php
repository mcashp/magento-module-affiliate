<?php

/**
 * Class Mcashp_Affiliate_Model_Observer
 */
class Mcashp_Affiliate_Model_Observer
{
    /**
     * @param \Varien_Event_Observer $observer
     *
     * @return $this|void
     */
    public function track(Varien_Event_Observer $observer)
    {
        /** @var Mcashp_Affiliate_Helper_Data $affiliateHelper */
        $affiliateHelper = Mage::helper('mcashpaffiliate');

        if ( ! $affiliateHelper->checkTrackingRequest()) {
            return $this;
        }

        $queryParameters = $_GET;
        unset($queryParameters[Mcashp_Affiliate_Helper_Data::CONFIG_COOKIE_NAME]);

        $url = strtok(Mage::helper('core/url')->getCurrentUrl(), '?');
        $query = http_build_query($queryParameters);

        $url = $url . ($query ? '?' . $query : '');

        Mage::log('Tracking: ' . $affiliateHelper->getTracking(), null, 'mcashp.log');

        header('Location: ' . $url, 302);
        exit();
    }

    /**
     * @param \Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function login(Varien_Event_Observer $observer)
    {
        /** @var Mcashp_Affiliate_Helper_Data $affiliateHelper */
        $affiliateHelper = Mage::helper('mcashpaffiliate');

        if ( ! $affiliateHelper->getConfigActive()) {
            return $this;
        }

        if ( ! $tracking = $affiliateHelper->getTrackingCookie()) {
            return $this;
        }

        $affiliateHelper->setTracking($tracking, $observer->getCustomer()->getId());

        return $this;
    }

    /**
     * @param \Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function register(Varien_Event_Observer $observer)
    {
        return $this->login($observer);
    }

    /**
     * @param \Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function quoteSaveBefore(Varien_Event_Observer $observer)
    {
        /** @var Mcashp_Affiliate_Helper_Data $affiliateHelper */
        $affiliateHelper = Mage::helper('mcashpaffiliate');

        if ( ! $affiliateHelper->getConfigActive()) {
            return $this;
        }

        if ( ! $tracking = $affiliateHelper->getTracking()) {
            return $this;
        }

        /** @var \Mage_Sales_Model_Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        $quote->setMcashpTracking($tracking);

        return $this;
    }

    /**
     * @param \Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function lead(Varien_Event_Observer $observer)
    {
        /** @var Mcashp_Affiliate_Helper_Data $affiliateHelper */
        $affiliateHelper = Mage::helper('mcashpaffiliate');

        if ( ! $affiliateHelper->getConfigActive()) {
            return $this;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        $commission = $affiliateHelper->getConfigCommission();
        if ($commission < Mcashp_Affiliate_Helper_Data::CONFIG_COMMISSION_MIN) {
            $commission = Mcashp_Affiliate_Helper_Data::CONFIG_COMMISSION_MIN;
        } elseif ($commission > Mcashp_Affiliate_Helper_Data::CONFIG_COMMISSION_MAX) {
            $commission = Mcashp_Affiliate_Helper_Data::CONFIG_COMMISSION_MAX;
        }

        $tracking = null;
        if ($quote = $affiliateHelper->getQuote($order->getQuoteId() ?: false)) {
            $tracking = $quote->getMcashpTracking($tracking);
        }

        if ( ! $tracking) {
            $tracking = $affiliateHelper->getTracking();
        }

        $order->setMcashpTracking($tracking);
        $order->setMcashpCommission($commission);
        $order->save();

        Mage::log(sprintf('Lead: %d %s%', $order->getId(), $commission), null, 'mcashp.log');

        $affiliateHelper->mcashpEvent('lead', $tracking, 0);

        return $this;
    }

    /**
     * @param \Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function sale(Varien_Event_Observer $observer)
    {
        /** @var Mcashp_Affiliate_Helper_Data $affiliateHelper */
        $affiliateHelper = Mage::helper('mcashpaffiliate');

        if ( ! $affiliateHelper->getConfigActive()) {
            return $this;
        }

        /** @var \Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();
        if ( ! $order = $invoice->getOrder()) {
            return $this;
        }

        $tracking = $order->getMcashpTracking();
        $commission = $order->getMcashpCommission();

        if ( ! $tracking || ! $commission) {
            return $this;
        }

        $total = $invoice->getBaseSubtotalInclTax() / 100 * $commission;
        $currency = $invoice->getBaseCurrencyCode();

        $invoice->setMcashpTracking($tracking);
        $invoice->setMcashpCommission($total);
        $invoice->save();

        Mage::log(sprintf('Sale: %s %s%s %s%%', $order->getId(), $total, $currency, $commission), null, 'mcashp.log');

        $res = $affiliateHelper->mcashpEvent('sale', $tracking, $total);
        Mage::log('API: ' . $res, null, 'mcashp.log');


        return $this;
    }
}
