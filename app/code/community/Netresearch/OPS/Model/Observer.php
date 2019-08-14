<?php
/**
 * @category   OPS
 * @package    Netresearch_OPS
 * @author     André Herrn <andre.herrn@netresearch.de>
 * @copyright  Copyright (c) 2013 Netresearch GmbH & Co. KG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Netresearch_OPS_Model_Observer
 *
 * @author     André Herrn <andre.herrn@netresearch.de>
 * @copyright  Copyright (c) 2013 Netresearch GmbH & Co. KG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Netresearch_OPS_Model_Observer
{
    
    /**
     * Get one page checkout model
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    public function getAdminSession()
    {
        return Mage::getSingleton('admin/session');
    }

    public function isAdminSession()
    {

        if ($this->getAdminSession()->getUser()) {
            return 0 < $this->getAdminSession()->getUser()->getUserId();
        }
        return false;
    }

    public function getHelper($name=null)
    {
        if (is_null($name)) {
            return Mage::helper('ops');
        }
        return Mage::helper('ops/' . $name);
    }

    /**
     * @return Netresearch_OPS_Model_Config
     */
    public function getConfig()
    {
        return Mage::getModel('ops/config');
    }

    /**
     * trigger ops payment
     */
    public function checkoutTypeOnepageSaveOrderBefore($observer)
    {
        $quote = $observer->getQuote();
        $order = $observer->getOrder();
        $code = $quote->getPayment()->getMethodInstance()->getCode();

        try {
            if ('ops_directDebit' == $code && Mage::helper('ops/payment')->isInlinePaymentWithQuoteId($quote->getPayment())) {
                $this->confirmDdPayment($order, $quote, $observer);
            } elseif ($quote->getPayment()->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_Abstract) {
                $requestParams = $quote->getPayment()->getMethodInstance()->getFormFields($order, array(), false);
                $this->invokeRequestParamValidation($requestParams);
            }
        } catch (Exception $e) {
            $quote->setIsActive(true);
            $this->getOnepage()->getCheckout()->setGotoSection('payment');
            throw new Mage_Core_Exception($e->getMessage());
        }
    }

    public function salesModelServiceQuoteSubmitFailure($observer)
    {
        $quote = $observer->getQuote();
        if (Mage::helper('ops/payment')->isInlinePaymentWithQuoteId($quote->getPayment())) {
            $this->handleFailedCheckout(
                $observer->getQuote(),
                $observer->getOrder()
            );
        }
    }

    protected function getQuoteCurrency($quote)
    {
        if ($quote->hasForcedCurrency()) {
            return $quote->getForcedCurrency()->getCode();
        } else {
            return Mage::app()->getStore($quote->getStoreId())->getBaseCurrencyCode();
        }
    }

    public function confirmAliasPayment($order, $quote)
    {
        $requestParams = Mage::helper('ops/creditcard')->getDirectLinkRequestParams($quote, $order);
        $this->invokeRequestParamValidation($requestParams);
        Mage::helper('ops/alias')->cleanUpAdditionalInformation($quote->getPayment(), true);

        return $this->performDirectLinkRequest($quote, $requestParams, $quote->getStoreId());
        
    }

    public function confirmDdPayment($order, $quote)
    {
        /** @var Netresearch_OPS_Helper_DirectDebit $directDebitHelper */
        $directDebitHelper = Mage::helper('ops/directDebit');
        $requestParams = Mage::app()->getRequest()->getParam('ops_directDebit');
        $directDebitHelper->handleAdminPayment($quote, $requestParams);
        $requestParams = $directDebitHelper->getDirectLinkRequestParams($quote, $order, $requestParams);
        $this->invokeRequestParamValidation($requestParams);

        return $this->performDirectLinkRequest($quote, $requestParams, $quote->getStoreId());
    }

    public function performDirectLinkRequest($quote, $params, $storeId = null)
    {
        $url = Mage::getModel('ops/config')->getDirectLinkGatewayOrderPath($storeId);
        $response = Mage::getSingleton('ops/api_directlink')->performRequest($params, $url, $storeId);
        /**
         * allow null as valid state for creating the order with status 'pending'
         */
        if (!is_null($response['STATUS']) 
            && Mage::helper('ops/payment')->isPaymentFailed($response['STATUS'])
           ) {
            throw new Mage_Core_Exception($this->getHelper()->__('Ingenico Payment Services Payment failed'));
        }
        $quote->getPayment()->setAdditionalInformation('ops_response', $response)->save();
        
    }

    
    /**
     * Check if checkout was made with OPS CreditCart or DirectDebit
     *
     * @return boolean
     */
    protected function isCheckoutWithExistingTxId($code)
    {
        if ('ops_opsid' == $code)
            return true;
        else
            return false;
    }

    /**
     * Replace order cancel comfirm message of Magento by a custom message from Ingenico Payment Services
     *
     * @event adminhtml_block_html_before
     * @param Varien_Event_Observer $observer
     * @return Netresearch_OPS_Model_Observer
     */
    public function updateOrderCancelButton(Varien_Event_Observer $observer)
    {
        /* @var $block Mage_Adminhtml_Block_Template */
        $block = $observer->getEvent()->getBlock();
        
        //Stop if block is not sales order view
        if ($block->getType() != 'adminhtml/sales_order_view') {
            return $this;
        }
        
        //If payment method is one of the Ingenico Payment Services-ones and order can be cancelled manually
        if ($block->getOrder()->getPayment()->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_Abstract
            && true === $block->getOrder()->getPayment()->getMethodInstance()->canCancelManually($block->getOrder())) {
            //Build message and update cancel button
            $message = Mage::helper('ops')->__(
                "Are you sure you want to cancel this order? Warning: Please check the payment status in the back-office of Ingenico Payment Services before. By cancelling this order you won\\'t be able to update the status in Magento anymore."
            );
            $block->updateButton(
                'order_cancel',
                'onclick',
                'deleteConfirm(\''.$message.'\', \'' . $block->getCancelUrl() . '\')'
            );
        }
        return $this;
    }

    /**
     *
     * appends a checkbox for closing the transaction if it's a Ingenico Payment Services payment
     *
     * @event core_block_abstract_to_html_after
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function appendCheckBoxToRefundForm($observer)
    {
        $html = '';
        /*
         * show the checkbox only if the credit memo create page is displayed and
         * the refund can be done online and the payment is done via Ingenico Payment Services
         */
        if ($observer->getBlock() instanceof Mage_Adminhtml_Block_Sales_Order_Creditmemo_Totals
            && $observer->getBlock()->getParentBlock() 
                instanceof Mage_Adminhtml_Block_Sales_Order_Creditmemo_Create_Items
            && $observer->getBlock()->getParentBlock()->getCreditmemo()->getOrder()->getPayment()
            && $observer->getBlock()->getParentBlock()->getCreditmemo()->getOrder()->getPayment()->getMethodInstance()
                instanceof Netresearch_OPS_Model_Payment_Abstract
            && $observer->getBlock()->getParentBlock()->getCreditmemo()->canRefund()
            && $observer->getBlock()->getParentBlock()->getCreditmemo()->getInvoice()
            && $observer->getBlock()->getParentBlock()->getCreditmemo()->getInvoice()->getTransactionId()
        ) {
            $transport = $observer->getTransport();
            $block     = $observer->getBlock();
            $layout    = $block->getLayout();
            $html      = $transport->getHtml();
            $checkBoxHtml = $layout->createBlock(
                'ops/adminhtml_sales_order_creditmemo_totals_checkbox', 
                'ops_refund_checkbox'
            )
                ->setTemplate('ops/sales/order/creditmemo/totals/checkbox.phtml')
                ->renderView();
            $html = $html . $checkBoxHtml;
            $transport->setHtml($html);
        }
        return $html;
    }

    /**
     *
     * fetch the creation of credit memo event and display warning message when
     * - credit memo could be done online
     * - payment is a Ingenico Payment Services payment
     * - Ingenico Payment Services transaction is closed
     *
     * @event core_block_abstract_to_html_after
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function showWarningForClosedTransactions($observer)
    {
        $html = '';
        /**
         * - credit memo could be done online
         * - payment is a Ingenico Payment Services payment
         * - Ingenico Payment Services transaction is closed
         */
        if ($observer->getBlock() instanceof Mage_Adminhtml_Block_Sales_Order_Creditmemo_Create
            && $observer->getBlock()->getCreditmemo()->getOrder()->getPayment()
            && $observer->getBlock()->getCreditmemo()->getOrder()->getPayment()->getMethodInstance()
                instanceof Netresearch_OPS_Model_Payment_Abstract
            && $observer->getBlock()->getCreditmemo()->getInvoice()
            && $observer->getBlock()->getCreditmemo()->getInvoice()->getTransactionId()
            && false === $observer->getBlock()->getCreditmemo()->canRefund()
        ) {
            $transport = $observer->getTransport();
            $block     = $observer->getBlock();
            $layout    = $block->getLayout();
            $html      = $transport->getHtml();
            $warningHtml = $layout->createBlock(
                'ops/adminhtml_sales_order_creditmemo_closedTransaction_warning', 
                'ops_closed-transaction-warning'
            )
                ->renderView();
            $html      = $warningHtml . $html;
            $transport->setHtml($html);
        }
        return $html;
    }

    
    /**
     * triggered by cron for deleting old payment data from the additional payment information
     * @param $observer
     */
    public function cleanUpOldPaymentData($observer)
    {
        Mage::helper('ops/quote')->cleanUpOldPaymentInformation();
    }

    /**
     * in some cases the payment method is not set properly by Magento so we need to reset the
     * payment method in the quote's payment before importing the data
     *
     * @event sales_quote_payment_import_data_before
     * @param $observer
     * @return $this
     */
    public function clearPaymentMethodFromQuote(Varien_Event_Observer $observer)
    {
        if ($observer->getEventName() == 'sales_quote_payment_import_data_before'
            && $observer->getEvent()->getPayment() instanceof Mage_Sales_Model_Quote_Payment
        ) {
            $observer->getEvent()->getPayment()->setMethod(null);
        }

        return $this;
    }

    /**
     * appends the status update button to the order's button in case it's an Ingenico Payment Services payment
     *
     * @event core_block_abstract_prepare_layout_before
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function addStatusUpdateButtonToOrderView(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();
        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_View) {
            $paymentMethod = $block->getOrder()->getPayment()->getMethodInstance();
            if ($paymentMethod instanceof Netresearch_OPS_Model_Payment_Abstract
                && Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/invoice')) {
                $block->addButton('ops_refresh', array(
                    'label'     => Mage::helper('ops/data')->__('Refresh payment status'),
                    'onclick'   => 'setLocation(\'' . $block->getUrl('adminhtml/opsstatus/update') . '\')'));
            }
        }

        return $this;
    }

    /**
     * @event core_block_abstract_prepare_layout_before
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function addCcPaymentMethod(Varien_Event_Observer $observer)
    {
        /** @var  $block Mage_Payment_Block_Form_Container */
        $block = $observer->getEvent()->getBlock();
        if ($block instanceof Mage_Payment_Block_Form_Container
            && !Mage::helper('ops/version')->canUseApplicableForQuote(Mage::getEdition())
        ) {
            Mage::helper('ops/payment')->addCCForZeroAmountCheckout($block);
        }

        return $this;
    }

    /**
     * @event core_block_abstract_prepare_layout_before
     *
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function disableCaptureForZeroAmountInvoice(Varien_Event_Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();
        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_Invoice_Create_Items) {
            $invoice = $block->getInvoice();
            if ($invoice->getBaseGrandTotal() <= 0.01
                && $invoice->getOrder()->getPayment()->getMethodInstance() instanceof
                Netresearch_OPS_Model_Payment_Abstract
            ) {
                $invoice->getOrder()->getPayment()->getMethodInstance()->setCanCapture(false);
            }
        }

        return $this;
    }


    /**
     * @param $requestParams
     *
     * @throws Mage_Core_Exception
     * @return Netresearch_OPS_Model_Observer
     */
    protected function invokeRequestParamValidation($requestParams)
    {
        $validator = Mage::getModel('ops/validator_parameter_factory')->getValidatorFor(
            Netresearch_OPS_Model_Validator_Parameter_Factory::TYPE_REQUEST_PARAMS_VALIDATION
        );
        if (false == $validator->isValid($requestParams)) {
            $this->getOnepage()->getCheckout()->setGotoSection('payment');
            Mage::throwException(
                $this->getHelper()->__('The data you have provided can not be processed by Ingenico Payment Services')
            );
        }

        return $this;
    }


    /**
     * @event sales_order_save_before
     * @param Varien_Event_Observer $observer
     * @return Netresearch_OPS_Model_Observer
     */
    public function checkForOpsStatus(Varien_Event_Observer $observer)
    {
        if ($this->getConfig()->getStateRestriction() == 1) {
            $order = $observer->getOrder();
            $origData = $order->getOrigData();
            if (is_array($origData)
                && array_key_exists('status', $origData)
                && $order->getStatus() != $origData['status']
                && $order->getPayment()->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_Abstract
                && Mage::helper('ops/data')->isAdminSession()
            ) {
                Mage::helper('ops/order')
                    ->checkForOpsStateOnStatusUpdate($order);
            }
        }
        return $this;
    }

    /**
     * validates the input fields after the payment step in OPC
     *
     * @event controller_action_postdispatch_checkout_onepage_savePayment
     * @param Varien_Event_Observer $event
     *
     * @return $this
     */
    public function controllerActionCheckoutOnepagePostdispatch(Varien_Event_Observer $event)
    {
        $controller = $event->getControllerAction();
        $quote = $this->getOnepage()->getQuote();
        if ($quote->getPayment()->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_Abstract) {
            $paramHelper = Mage::helper('ops/payment_request');
            $shippingParams = array();
            $billingParams = $paramHelper->getOwnerParams($quote, $quote->getBillingAddress());
            if ($quote->getShippingAddress()) {
                $shippingParams = $paramHelper->extractShipToParameters($quote->getShippingAddress(), $quote);
            }
            $params = array_merge($billingParams, $shippingParams);
            $validator = Mage::getModel('ops/validator_parameter_factory')->getValidatorFor(
                Netresearch_OPS_Model_Validator_Parameter_Factory::TYPE_REQUEST_PARAMS_VALIDATION
            );
            if (false == $validator->isValid($params)) {
                $result = Mage::helper('ops/validation_result')->getValidationFailedResult(
                    $validator->getMessages(),
                    $quote
                );
                $controller->getResponse()->setBody(Mage::helper('core/data')->jsonEncode($result));
            }
        }

        return $this;
    }


    public function salesOrderPaymentCapture(Varien_Event_Observer $event)
    {
        /** @var $payment Mage_Sales_Model_Order_Payment */
        $payment = $event->getPayment();
        $invoice = $event->getInvoice();
        if ($payment->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_Abstract) {
            $payment->setInvoice($invoice);
        }


        return $this;
    }

    /**
     * @event core_block_abstract_to_html_after
     * @param Varien_Event_Observer $event
     * @return Netresearch_OPS_Model_Observer
     */
    public function appendPartialCaptureWarningForOpenInvoice(Varien_Event_Observer $event)
    {
        $block = $event->getBlock();
        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_Invoice_Totals
            && $block->getInvoice()->getOrder()->getPayment()->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_OpenInvoice_Abstract
            && $block->getInvoice()->getOrder()->getPayment()->getMethodInstance()->canCapturePartial() === true
        ) {
            $transport = $event->getTransport();
            $layout    = $block->getLayout();
            $html      = $transport->getHtml();
            $warningHtml = $layout->createBlock(
                'ops/adminhtml_sales_order_invoice_warning_openInvoice',
                'ops_invoice-openInvoice-warning'
            )
                ->renderView();
            $html      = $html . $warningHtml ;
            $transport->setHtml($html);
        }

        return $this;
    }
    
    /**
     * resets the order status back to pending payment in case of direct debits nl with order id as merchant ref
     * @event sales_order_payment_place_end
     * @param Varien_Event_Observer $event
     */
    public function setOrderStateForDirectDebitsNl(Varien_Event_Observer $event)
    {
        $payment = $event->getPayment();
        if ($payment->getMethodInstance() instanceof Netresearch_OPS_Model_Payment_DirectDebit
            && Mage::helper('ops/payment')->isInlinePaymentWithOrderId($payment)
            && $payment->getAdditionalInformation('PM') == 'Direct Debits NL'
            && $payment->getAdditionalInformation('STATUS') == Netresearch_OPS_Model_Payment_Abstract::OPS_AUTHORIZED_WAITING
        ) {
            $payment->getOrder()->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
            $payment->getOrder()->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        }
    }

    /**
     *
     * @event core_block_abstract_to_html_after
     * @param Varien_Event_Observer $event
     * @return string
     */
    public function  appendWarningToRefundFormForOpenInvoiceNl(Varien_Event_Observer $event)
    {
        $html = '';
        if ($event->getBlock() instanceof Mage_Adminhtml_Block_Sales_Order_Creditmemo_Totals
            && $event->getBlock()->getParentBlock()
            instanceof Mage_Adminhtml_Block_Sales_Order_Creditmemo_Create_Items
            && $event->getBlock()->getParentBlock()->getCreditmemo()->getOrder()->getPayment()
            && $event->getBlock()->getParentBlock()->getCreditmemo()->getOrder()->getPayment()->getMethodInstance()
            instanceof Netresearch_OPS_Model_Payment_OpenInvoiceNl
            && $event->getBlock()->getParentBlock()->getCreditmemo()->canRefund()
            && $event->getBlock()->getParentBlock()->getCreditmemo()->getInvoice()
            && $event->getBlock()->getParentBlock()->getCreditmemo()->getInvoice()->getTransactionId()
        ) {
            $transport = $event->getTransport();
            $block     = $event->getBlock();
            $layout    = $block->getLayout();
            $html      = $transport->getHtml();
            $warningHtml = $layout->createBlock(
                'ops/adminhtml_sales_order_creditmemo_warning_openInvoiceNl',
                'ops_openinvoice-warning'
            )
                                  ->renderView();
            $html      = $warningHtml . $html;
            $transport->setHtml($html);
        }
        return $html;
    }

    /**
     * appends the resend payment info button to the order's button in case it's an Ingenico Payment Services payment
     * and the payment status is an authorize status
     *
     * @event core_block_abstract_prepare_layout_before
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function addResendPaymentInfoButtonToOrderView(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();
        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_View) {
            $payment = $block->getOrder()->getPayment();
            $paymentMethod = $payment->getMethodInstance();
            if ($paymentMethod instanceof Netresearch_OPS_Model_Payment_Abstract
                && Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/invoice')
                && is_array($payment->getAdditionalInformation())
                && array_key_exists('status', $payment->getAdditionalInformation())
                && $this->getHelper('payment')->isPaymentFailed($payment->getAdditionalInformation('status'))) {
                $block->addButton('ops_resend_info', array(
                    'label'     => Mage::helper('ops/data')->__('Resend payment information'),
                    'onclick'   => 'setLocation(\'' . $block->getUrl('adminhtml/admin/resendInfo') . '\')'));
            }
        }

        return $this;
    }
}
