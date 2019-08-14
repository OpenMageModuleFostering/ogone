<?php

/**
 * PaymentEmailTest.php
 *
 * @author    paul.siedler@netresearch.de
 * @copyright Copyright (c) 2015 Netresearch GmbH & Co. KG
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License
 */
class Netresearch_OPS_Test_Model_Payment_Features_PaymentEmailTest extends EcomDev_PHPUnit_Test_Case
{

    /** @var Netresearch_OPS_Model_Payment_Features_PaymentEmail $testSubject */
    protected $testSubject;

    public function setUp()
    {
        parent::setUp();
        $this->testSubject = Mage::getModel('ops/payment_features_paymentEmail');
    }

    public function testIsAvailableForOrder()
    {

        // given object is no order model -> returns false
        $order = new Varien_Object();
        $this->assertFalse($this->testSubject->isAvailableForOrder($order));

        // given payment has not fitting status -> returns false
        $payment = Mage::getModel('sales/order_payment');
        $payment->setAdditionalInformation(array('status' => 9));
        $order = $this->getModelMock('sales/order', array('getPayment'));
        $order->expects($this->once())
              ->method('getPayment')
              ->will($this->returnValue($payment));
        $this->assertFalse($this->testSubject->isAvailableForOrder($order));

        // payment has relevant status -> returns true
        $payment = Mage::getModel('sales/order_payment');
        $payment->setAdditionalInformation(array('status' => 1));
        $order = $this->getModelMock('sales/order', array('getPayment'));
        $order->expects($this->once())
              ->method('getPayment')
              ->will($this->returnValue($payment));
        $this->assertTrue($this->testSubject->isAvailableForOrder($order));

    }

    public function testResendPaymentInfo()
    {
        $genericMethod = Mage::getModel('ops/payment_flex');


        $payment = $this->getModelMock('sales/order_payment', array('save'));
        $payment->expects($this->once())
                ->method('save')
                ->will($this->returnValue(null));

        $order = Mage::getModel('sales/order');
        $order->setData('customer_email', 'a@bc.de')
              ->setData('customer_firstname', 'Hans')
              ->setData('customer_lastname', 'Wurst')
              ->setStoreId(0)
              ->setPayment($payment);

        $this->assertTrue($this->testSubject->resendPaymentInfo($order));
        $this->assertNotEquals($order->getPayment()->getMethod(), 'foobar');
        $this->assertEquals($order->getPayment()->getMethod(), Netresearch_OPS_Model_Payment_Flex::CODE);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Could not send mail due to internal error!
     */
    public function testSendSuspendSubscriptionMailWithException()
    {
        $this->testSubject->sendSuspendSubscriptionMail(null, null);
    }

    public function testSendSuspendSubscriptionMail()
    {
        $profile = Mage::getModel('sales/recurring_profile');
        $profile->setReferenceId('SUB-123')
                ->setStoreId(0);

        $customer = Mage::getModel('customer/customer');
        $customer->setEmail('a@bc.de')
                 ->setFirstName('Hans')
                 ->setLastName('Wurst');

        $this->assertTrue($this->testSubject->sendSuspendSubscriptionMail($profile, $customer));

    }
}
