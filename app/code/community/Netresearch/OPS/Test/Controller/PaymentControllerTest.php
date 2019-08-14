<?php

class Netresearch_OPS_Test_Controller_PaymentControllerTest
    extends EcomDev_PHPUnit_Test_Case_Controller
{

    public function setUp()
    {
        parent::setUp();
        $helperMock = $this->getHelperMock('ops/payment', array(
            'shaCryptValidation',
            'cancelOrder',
            'declineOrder',
            'handleException',
            'getSHAInSet',
            'refillCart'
        ));
        $helperMock->expects($this->any())
            ->method('shaCryptValidation')
            ->will($this->returnValue(true));

        $this->replaceByMock('helper', 'ops/payment', $helperMock);
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testAcceptAction()
    {
        $params = array();
        $this->dispatch('ops/payment/accept', $params);
        $this->assertRedirect('checkout/cart');


        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch('ops/payment/accept', $params);
        $this->assertRedirect('checkout/onepage/success');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch('ops/payment/accept', $params);
        $this->assertRedirect('checkout/onepage/success');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testExceptionAction()
    {
        $params = array();
        $this->dispatch('ops/payment/exception', $params);
        $this->assertRedirect('checkout/cart');

        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch('ops/payment/exception', $params);
        $this->assertRedirect('checkout/onepage/success');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch('ops/payment/exception', $params);
        $this->assertRedirect('checkout/onepage/success');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testDeclineAction()
    {
        $routeToDispatch = 'ops/payment/decline';
        $params = array();
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');


        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testCancelAction()
    {
        $routeToDispatch = 'ops/payment/cancel';
        $params = array();
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/onepage');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testContinueAction()
    {
        $routeToDispatch = 'ops/payment/continue';
        $params = array();
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/cart');


        $params = array(
            'orderID' => '#100000011'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/cart');

        $params = array(
            'orderID' => '23'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('checkout/cart');

        $params = array(
            'orderID' => '#100000011',
            'redirect' => 'catalog'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('/');

        $params = array(
            'orderID' => '23',
            'redirect' => 'catalog'
        );
        $this->dispatch($routeToDispatch, $params);
        $this->assertRedirect('/');


    }


    public function testAcceptAliasAction()
    {
        $quote = Mage::getModel('sales/quote');
        $payment = Mage::getModel('sales/quote_payment');
        $quote->setPayment($payment);
        $checkoutMock = $this->getModelMock('checkout/session', array('getQuote'));
        $checkoutMock->expects($this->any())
            ->method('getQuote')
            ->will($this->returnValue($quote));
        $this->replaceByMock('singleton', 'checkout/session', $checkoutMock);
        $aliasHelperMock = $this->getHelperMock('ops/alias', array('saveAlias', 'setAliasToPayment'));
        $this->replaceByMock('helper', 'ops/alias', $aliasHelperMock);
        $routeToDispatch = 'ops/payment/acceptAlias';
        $params = array('Alias' => '4711');
        $this->dispatch($routeToDispatch, $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertEquals('4711', $result['alias']);
        $this->assertEquals('success', $result['result']);
        $this->assertNull($result['CVC']);

        $params = array('Alias' => '4711', 'CVC' => '123');
        $this->dispatch($routeToDispatch, $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertEquals('4711', $result['alias']);
        $this->assertEquals('success', $result['result']);
        $this->assertEquals('123', $result['CVC']);
//        $this->markTestIncomplete();
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     *
     * due to difficulties with loading the quote from the fixtures
     * this test is marked as incomplete
     *
     */
    public function testSaveAliasAction()
    {
//        $paymentMock = $this->getModelMock('sales/quote_payment', array('save'));
//        $quote = $this->getModelMock('sales/quote', array('getPayment','_beforeSave', 'save'));
//        $quote->expects($this->any())
//            ->method('getPayment')
//            ->will($this->returnValue($paymentMock));
//        $quote->setPayment($paymentMock);
//        Mage::getSingleton('checkout/session')->setQuote($quote);
//        $routeToDispatch = 'ops/payment/saveAlias';
//        $params = array('alias' => '4711');
//
//        $aliasHelperMock = $this->getHelperMock('ops/alias', array('setAliasToPayment'));
//        $aliasHelperMock->expects($this->once())
//            ->method('setAliasToPayment')
//            ->with($paymentMock, $params, false);
//        $this->replaceByMock('helper', 'ops/alias', $aliasHelperMock);
//
//        $this->dispatch($routeToDispatch, $params);
        $this->markTestIncomplete();
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testGenerateHashAction()
    {

        $fakeQuote = Mage::getModel('sales/order')->load(11);
        $quoteMock = $this->getModelMock('sales/quote', array('load', 'save'));
        $quoteMock->expects($this->any())
            ->method('load')
            ->will($this->returnValue($fakeQuote));
        $this->replaceByMock('model', 'sales/quote', $quoteMock);
        $params = array(
            'alias' => 4711,
            'storeId' => 1
        );

        $configHelperMock = $this->getModelMock('ops/config', array('getAliasAcceptUrl', 'getAliasExceptionUrl'));
        $configHelperMock->expects($this->any())
            ->method('getAliasAcceptUrl')
            ->with(1)
            ->will($this->returnValue(1));
        $configHelperMock->expects($this->any())
            ->method('getAliasExceptionUrl')
            ->with(1)
            ->will($this->returnValue(1));
        $this->replaceByMock('model', 'ops/config', $configHelperMock);

        $this->dispatch('ops/payment/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('alias', $result);
        $this->assertEquals('4711', $result['alias']);

        $params = array(
            'alias' => 4712,
            'storeId' => 0
        );

        $configHelperMock = $this->getModelMock('ops/config', array('getAliasAcceptUrl', 'getAliasExceptionUrl'));
        $configHelperMock->expects($this->any())
            ->method('getAliasAcceptUrl')
            ->with(0)
            ->will($this->returnValue(1));
        $configHelperMock->expects($this->any())
            ->method('getAliasExceptionUrl')
            ->with(0)
            ->will($this->returnValue(1));
        $this->replaceByMock('model', 'ops/config', $configHelperMock);

        $this->dispatch('ops/payment/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('alias', $result);
        $this->assertEquals('4712', $result['alias']);

        $params = array(
            'alias' => 4713,
            'storeId' => 1,
            'isAdmin' => 1,
            'brand' => 'visa'
        );

        $configHelperMock = $this->getModelMock('ops/config', array('getAliasAcceptUrl', 'getAliasExecptionUrl'));
        $configHelperMock->expects($this->any())
            ->method('getAliasAcceptUrl')
            ->with(0)
            ->will($this->returnValue(1));
        $configHelperMock->expects($this->any())
            ->method('getAliasExceptionUrl')
            ->with(0)
            ->will($this->returnValue(1));
        $this->replaceByMock('model', 'ops/config', $configHelperMock);

        $this->dispatch('ops/payment/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('alias', $result);
        $this->assertEquals('4713', $result['alias']);
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testGenerateHashWithStoredAliasButNewAliasIsUsed()
    {
        $fakeQuote = Mage::getModel('sales/order')->load(11);
        $quoteMock = $this->getModelMock('sales/quote', array('load', 'save'));
        $quoteMock->expects($this->any())
            ->method('load')
            ->will($this->returnValue($fakeQuote));
        $this->replaceByMock('model', 'sales/quote', $quoteMock);
        $params = array(
            'alias' => 4711,
            'storeId' => 1,
            'storedAlias' => '0815',
        );

        $helperMock = $this->getHelperMock('ops/alias', array('isAliasValidForAddresses'));
        $helperMock->expects($this->any())
            ->method('isAliasValidForAddresses')
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'ops/alias', $helperMock);
        $this->dispatch('ops/payment/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('alias', $result);
        $this->assertEquals('4711', $result['alias']);

        $helperMock = $this->getHelperMock('ops/alias', array('isAliasValidForAddresses'));
        $helperMock->expects($this->any())
            ->method('isAliasValidForAddresses')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/alias', $helperMock);
        $this->dispatch('ops/payment/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('alias', $result);
        $this->assertEquals('4711', $result['alias']);
    }

    public function testGenerateHashWithStoredAliasOldAliasIsUsed()
    {
        $fakeQuote = $this->getModelMock('sales/quote', array('save', 'getPayment'));
        $fakeQuote->setId(1);
        $fakeQuote->setStoreId(1);
        $customer = $this->getModelMock('customer/customer', array('save'));
        $customer->setId(1);
        $billingAddress = new Varien_Object();
        $shippingAddress = new Varien_Object();
        $fakeQuote->setCustomer($customer);

        $fakePayment = $this->getModelMock('sales/quote_payment', array('save'));
        $fakePayment->expects($this->any())
            ->method('settAdditionalInformation')
            ->with('saveOpsAlias', 1);
        $fakeQuote->expects($this->any())
            ->method('getPayment')
            ->will($this->returnValue($fakePayment));

        $quoteMock = $this->getModelMock('sales/quote', array('load', 'save'));
        $quoteMock->expects($this->any())
            ->method('load')
            ->will($this->returnValue($fakeQuote));
        $this->replaceByMock('model', 'sales/quote', $quoteMock);
        $params = array(
            'alias' => 4711,
            'storeId' => 1,
            'storedAlias' => '0815',
            'saveAlias' => 1
        );

        $helperMock = $this->getHelperMock('ops/alias', array('isAliasValidForAddresses'));
        $helperMock->expects($this->any())
            ->method('isAliasValidForAddresses')
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'ops/alias', $helperMock);
        $this->dispatch('ops/payment/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('alias', $result);
        $this->assertEquals('0815', $result['alias']);

        $helperMock = $this->getHelperMock('ops/alias', array('isAliasValidForAddresses'));
        $helperMock->expects($this->any())
            ->method('isAliasValidForAddresses')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/alias', $helperMock);
        $this->dispatch('ops/payment/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('alias', $result);
        $this->assertEquals('4711', $result['alias']);
    }

    public function testGenerateHashActionWithSaveAlias()
    {
        $fakeQuote = $this->getModelMock('sales/quote', array('save', 'getPayment'));
        $fakeQuote->setId(1);
        $fakeQuote->setStoreId(1);
        $customer = $this->getModelMock('customer/customer', array('save'));
        $customer->setId(1);
        $billingAddress = new Varien_Object();
        $shippingAddress = new Varien_Object();
        $fakeQuote->setCustomer($customer);
        $fakePayment = $this->getModelMock('sales/quote_payment', array('save'));
        $fakePayment->expects($this->any())
            ->method('settAdditionalInformation')
            ->with('saveOpsAlias', 1);
        $fakeQuote->expects($this->any())
            ->method('getPayment')
            ->will($this->returnValue($fakePayment));

        $quoteMock = $this->getModelMock('sales/quote', array('load', 'save'));
        $quoteMock->expects($this->any())
            ->method('load')
            ->will($this->returnValue($fakeQuote));
        $this->replaceByMock('model', 'sales/quote', $quoteMock);
        $params = array(
            'alias' => 4711,
            'storeId' => 1,
            'saveAlias' => 1
        );

        $this->dispatch('ops/payment/generateHash', $params);
        $result = Mage::helper('core')->jsonDecode($this->getResponse()->getOutputBody());
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('alias', $result);
        $this->assertEquals('4711', $result['alias']);
    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testRepayActionWithInvalidHash()
    {
        // test 1: hash not valid
        $order = Mage::getModel('sales/order')->load(11);
        $opsOrderId = Mage::helper('ops/order')->getOpsOrderId($order);

        $paymentHelperMock = $this->getHelperMock('ops/payment', array('shaCryptValidation'));
        $paymentHelperMock->expects($this->any())
            ->method('shaCryptValidation')
            ->will($this->returnValue(false));
        $this->replaceByMock('helper', 'ops/payment', $paymentHelperMock);


        $params = array('orderID' => $opsOrderId, 'SHASIGN' => 'foo');
        $this->dispatch('ops/payment/retry', $params);
        $this->assertRedirectTo('/');
        $message = Mage::getSingleton('core/session')->getMessages()->getLastAddedMessage();
        $this->assertNotNull($message);
        $this->assertEquals($message->getText(), 'Hash not valid');

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testRepayActionWithInvalidOrder()
    {

        // test 1: hash valid, order can not be retried
        // orderID 100000011
        $order = Mage::getModel('sales/order')->load(11);
        $opsOrderId = Mage::helper('ops/order')->getOpsOrderId($order);

        $paymentHelperMock = $this->getHelperMock('ops/payment', array('shaCryptValidation'));
        $paymentHelperMock->expects($this->any())
            ->method('shaCryptValidation')
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'ops/payment', $paymentHelperMock);

        $params = array(
            'orderID' => $opsOrderId,
            'SHASIGN' => 'foo'
        );
        $this->dispatch('ops/payment/retry', $params);
        $this->assertRedirectTo('/');
        $message = Mage::getSingleton('core/session')->getMessages()->getLastAddedMessage();
        $this->assertNotNull($message);
        $this->assertEquals($message->getText(), 'Not possible to reenter the payment details for order ' . $order->getIncrementId());

    }

    /**
     * @loadFixture ../../../var/fixtures/orders.yaml
     */
    public function testRepayActionWithSuccess()
    {
        // test 3: order is fine
        // orderID 100000013

        $order = Mage::getModel('sales/order')->load(13);
        $opsOrderId = Mage::helper('ops/order')->getOpsOrderId($order);

        $paymentHelperMock = $this->getHelperMock('ops/payment', array('shaCryptValidation'));
        $paymentHelperMock->expects($this->any())
            ->method('shaCryptValidation')
            ->will($this->returnValue(true));
        $this->replaceByMock('helper', 'ops/payment', $paymentHelperMock);

        $params = array(
            'orderID' => $opsOrderId,
            'SHASIGN' => 'foo'
        );

        $this->dispatch('ops/payment/retry', $params);
        $this->assertLayoutLoaded();
        $this->assertLayoutHandleLoaded('ops_payment_retry');

    }
}