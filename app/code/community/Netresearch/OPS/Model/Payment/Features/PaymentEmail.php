<?php

/**
 * PaymentEmail.php
 * @author  paul.siedler@netresearch.de
 * @copyright Copyright (c) 2015 Netresearch GmbH & Co. KG
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License
 */

/**
 * Class Netresearch_OPS_Model_Payment_Features_PaymentEmail
 */
class Netresearch_OPS_Model_Payment_Features_PaymentEmail
{

    protected function getConfig()
    {
        return Mage::getModel('ops/config');
    }

    /**
     * Check if payment email is available for order
     *
     * @param $order
     * @return bool
     */
    public function isAvailableForOrder($order)
    {
        if($order instanceof Mage_Sales_Model_Order){
            $status = $order->getPayment()->getAdditionalInformation('status');
            return Mage::helper('ops/payment')->isPaymentInvalid($status);
        }

        return false;
    }


    /**
     * Resends the payment information and returns true/false, depending if succeeded or not
     *
     * @param Mage_Sales_Model_Order $order
     * @return boolean success state
     */
    public function resendPaymentInfo(Mage_Sales_Model_Order $order)
    {

        // reset payment method so the customer can choose freely from all available methods
        $this->setPaymentMethodToGeneric($order);

        $config = $this->getConfig();
        $template = $config->getResendPaymentInfoTemplate($order->getStoreId());
        $emailTemplate = Mage::getModel('core/email_template')->loadDefault($config->getResendPaymentInfoTemplate($order->getStoreId()));

        $identity = $config->getResendPaymentInfoIdentity($order->getStoreId());
        $emailTemplate->setSenderName(Mage::getStoreConfig('trans_email/ident_'.$identity.'/name'))
            ->setSenderEmail(Mage::getStoreConfig('trans_email/ident_'.$identity.'/email'));

        $parameters = array(
            "order" => $order,
            "paymentLink" => $this->generatePaymentLink($order)
        );

        return $emailTemplate->send($order->getCustomerEmail(), $order->getCustomerName(), $parameters);

    }

    /**
     * Generates the payment url
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    protected function generatePaymentLink(Mage_Sales_Model_Order $order)
    {
        $opsOrderId = Mage::helper('ops/order')->getOpsOrderId($order);

        $params = array(
            'orderID' => $opsOrderId
        );

        $secret = $this->getConfig()->getShaInCode($order->getStoreId());
        $raw = Mage::helper('ops/payment')->getSHAInSet($params, $secret);

        $params['SHASIGN'] = strtoupper(Mage::helper('ops/payment')->shaCrypt($raw));

        $url = Mage::getModel('ops/config')->getPaymentRetryUrl($params);
        return $url;
    }

    /**
     * Set payment method to Netresearch_OPS_Model_Payment_Other
     *
     * @param Mage_Sales_Model_Order $order
     * @throws Exception
     */
    protected function setPaymentMethodToGeneric(Mage_Sales_Model_Order $order)
    {
        $order->getPayment()->setMethod(Netresearch_OPS_Model_Payment_Other::CODE)->save();
    }

}