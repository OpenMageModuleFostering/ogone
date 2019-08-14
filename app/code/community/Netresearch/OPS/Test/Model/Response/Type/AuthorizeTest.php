<?php
/**
 * Created by PhpStorm.
 * User: sebastian
 * Date: 24.11.15
 * Time: 12:51
 */

class Netresearch_OPS_Test_Model_Response_Type_AuthorizeTest extends EcomDev_PHPUnit_Test_Case
{

    /**
     * @test
     * @loadFixture orders.yaml
     * @expectedException Mage_Core_Exception
     */
    public function testExceptionThrown()
    {
        /** @var Netresearch_OPS_Model_Payment_IDeal $instance */

        $order = Mage::getModel('sales/order')->load(25);
        $response = array(
            'status'   => 2,
            'payid'    => 1234567,
            'payidsub' => 3,
            'amount'   => 33.33
        );

        /** @var Netresearch_OPS_Model_Response_Type_Capture $handler */
        $handler = Mage::getModel('ops/response_type_authorize');
        $handler->handleResponse($response, $order->getPayment()->getMethodInstance(), false);
    }

    /**
     * @test
     * @loadFixture orders.yaml
     * @expectedException Mage_Core_Exception
     * @expectedExceptionMessage 500 is not a authorize status!
     */
    public function testExceptionThrownForNoAuthorizeStatus()
    {
        /** @var Netresearch_OPS_Model_Payment_IDeal $instance */

        $order = Mage::getModel('sales/order')->load(25);
        $response = array(
            'status'   => 500,
            'payid'    => 1234567,
            'payidsub' => 3,
            'amount'   => 33.33
        );

        /** @var Netresearch_OPS_Model_Response_Type_Capture $handler */
        $handler = Mage::getModel('ops/response_type_authorize');
        $handler->handleResponse($response, $order->getPayment()->getMethodInstance(), false);
    }


    /**
     * @test
     * @loadFixture orders.yaml
     */
    public function testHandleResponseWithPendingPaymentAndIntermediate()
    {
        $this->mockOrderConfig();

        /** @var Netresearch_OPS_Model_Payment_IDeal $instance */
        $order = Mage::getModel('sales/order')->load(28);

        $response = array(
            'status'   => 51,
            'payid'    => 1234567,
            'payidsub' => 3,
            'amount'   => 33.33
        );


        /** @var Netresearch_OPS_Model_Response_Handler $handler */
        $handler = Mage::getModel('ops/response_handler');
        $handler->processResponse($response, $order->getPayment()->getMethodInstance());

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertNotEmpty($order->getAllStatusHistory());
        $this->assertEquals($response['status'], $order->getPayment()->getAdditionalInformation('status'));
    }

    /**
     * @test
     * @loadFixture orders.yaml
     */
    public function testHandleResponseWithPaymentReviewAndFinal()
    {
        $this->mockOrderConfig();
        /** @var Netresearch_OPS_Model_Payment_IDeal $instance */
        $order = Mage::getModel('sales/order')->load(26);

        $response = array(
            'status'   => 5,
            'payid'    => 1234567,
            'payidsub' => 3,
            'amount'   => 33.33
        );

        /** @var Netresearch_OPS_Model_Response_Handler $handler */
        $handler = Mage::getModel('ops/response_handler');
        $payment = $order->getPayment()->getMethodInstance();
        $handler->processResponse($response, $payment);

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $order->getState());
        $this->assertNotEmpty($order->getAllStatusHistory());
        $this->assertEquals($response['status'], $order->getPayment()->getAdditionalInformation('status'));
    }

    /**
     * @test
     * @loadFixture orders.yaml
     */
    public function testHandleResponseWithPaymentReviewAndIntermediate()
    {

        $this->mockOrderConfig();

        $order = Mage::getModel('sales/order')->load(26);

        $response = array(
            'status'   => 51,
            'payid'    => 1234567,
            'payidsub' => 3,
            'amount'   => 33.33
        );

        /** @var Netresearch_OPS_Model_Response_Handler $handler */
        $handler = Mage::getModel('ops/response_handler');
        $handler->processResponse($response, $order->getPayment()->getMethodInstance());

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertNotEmpty($order->getAllStatusHistory());
        $this->assertEquals($response['status'], $order->getPayment()->getAdditionalInformation('status'));
    }

    /**
     * @test
     * @loadFixture orders.yaml
     */
    public function testHandleResponseWithPaymentReviewAndFinalDeclined()
    {
        $this->mockOrderConfig();

        $order = Mage::getModel('sales/order')->load(26);
        $this->mockOrderConfig();

        $response = array(
            'status'   => 2,
            'payid'    => 1234567,
            'payidsub' => 3,
            'amount'   => 33.33
        );

        $order->getPayment()->setAdditionalInformation('status', 46);

        /** @var Netresearch_OPS_Model_Response_Handler $handler */
        $handler = Mage::getModel('ops/response_handler');
        $handler->processResponse($response, $order->getPayment()->getMethodInstance());

        $this->assertEquals(Mage_Sales_Model_Order::STATE_CANCELED, $order->getState());
        $this->assertNotEmpty($order->getAllStatusHistory());
        $this->assertEquals($response['status'], $order->getPayment()->getAdditionalInformation('status'));
    }


    /**
     * @test
     * @loadFixture orders.yaml
     */
    public function testHandleResponseWithPendingPaymentAndFinal()
    {
        $this->mockOrderConfig();
        $order = Mage::getModel('sales/order')->load(29);
        $response = array(
            'status'   => 5,
            'payid'    => 1234567,
            'payidsub' => 3,
            'amount'   => 33.33
        );

        /** @var Netresearch_OPS_Model_Response_Handler $handler */
        $handler = Mage::getModel('ops/response_handler');
        $handler->processResponse($response, $order->getPayment()->getMethodInstance());

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $order->getState());
        $this->assertNotEmpty($order->getAllStatusHistory());
        $this->assertEquals($response['status'],  $order->getPayment()->getAdditionalInformation('status'));
    }


    /**
     * @test
     * @loadFixture orders.yaml
     */
    public function testHandleResponseWithPendingPaymentAndSuspectedFraudStatus()
    {
        $order = Mage::getModel('sales/order')->load(30);
        $response = array(
            'status'   => 55,
            'payid'    => 12345678,
            'payidsub' => 3,
            'amount'   => 33.33
        );

        /** @var Netresearch_OPS_Model_Response_Handler $handler */
        $handler = Mage::getModel('ops/response_handler');
        $handler->processResponse($response, $order->getPayment()->getMethodInstance());

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATUS_FRAUD, $order->getStatus());
        $this->assertNotEmpty($order->getAllStatusHistory());
        $this->assertEquals($response['status'],  $order->getPayment()->getAdditionalInformation('status'));
    }

    /**
     * @test
     * @loadFixture orders.yaml
     */
    public function testHandleResponseWithPaymentReviewAndSuspectedFraudStatus()
    {
        $order = Mage::getModel('sales/order')->load(31);
        $response = array(
            'status'   => 55,
            'payid'    => 1234567897,
            'payidsub' => 3,
            'amount'   => 33.33
        );

        /** @var Netresearch_OPS_Model_Response_Handler $handler */
        $handler = Mage::getModel('ops/response_handler');
        $handler->processResponse($response, $order->getPayment()->getMethodInstance());

        $this->assertEquals(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW, $order->getState());
        $this->assertEquals(Mage_Sales_Model_Order::STATUS_FRAUD, $order->getStatus());
        $this->assertNotEmpty($order->getAllStatusHistory());
        $this->assertEquals($response['status'],  $order->getPayment()->getAdditionalInformation('status'));
    }

    protected function mockOrderConfig(){
        $configMock = $this->getModelMock('sales/order_config',array('getDefaultStatus'));
        $configMock->expects($this->any())
            ->method('getDefaultStatus')
            ->will($this->returnArgument(0));
        $this->replaceByMock('singleton', 'sales/order_config', $configMock);
    }

    public function testStatusAuthorizationUnclear(){
        $order = Mage::getModel('sales/order')->setState(Mage_Sales_Model_Order::STATE_NEW);
        $payment = Mage::getModel('sales/order_payment')->setMethod('ops_cc');
        $order->setPayment($payment);
        $response = array(
            'status'   => 52,
            'payid'    => 12345678,
            'payidsub' => 3,
            'amount'   => 33.33
        );
        $handler = Mage::getModel('ops/response_handler');
        $handler->processResponse($response, $order->getPayment()->getMethodInstance(), false);
        $this->assertEquals(true, $payment->getIsTransactionPending());

    }
}