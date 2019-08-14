<?php


class Netresearch_OPS_Test_Model_Payment_ChinaUnionPayTest extends EcomDev_PHPUnit_Test_Case
{

    protected $model = null;

    public function setUp()
    {
        parent::setUp();
        $this->model = Mage::getModel('ops/payment_chinaUnionPay');
    }

    /**
     * assure that openInvoiceNL can not capture partial, because invoice is always created on feedback in this case
     */
    public function testCanCapturePartial()
    {
        $this->assertFalse($this->model->canCapturePartial());
    }

    public function testGetOpsCode()
    {
        $this->assertEquals(Netresearch_OPS_Model_Payment_ChinaUnionPay::PM, $this->model->getOpsCode());
    }

    public function testGetOpsBrand()
    {
        $this->assertEquals(Netresearch_OPS_Model_Payment_ChinaUnionPay::BRAND, $this->model->getOpsBrand());
    }


    public function testCanRefundPartialPerInvoice()
    {
        $this->assertFalse($this->model->canRefundPartialPerInvoice());
    }

    public function testGetPaymentAction()
    {
        $this->assertEquals(
            Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE,
            $this->model->getPaymentAction());
    }
}
