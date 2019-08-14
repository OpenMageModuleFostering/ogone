<?php
/**
 * Other.php
 * @author  paul.siedler@netresearch.de
 * @copyright Copyright (c) 2015 Netresearch GmbH & Co. KG
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License
 */

class Netresearch_OPS_Model_Payment_Other extends Netresearch_OPS_Model_Payment_Abstract {

    const CODE = 'ops_other';

    /** Check if we can capture directly from the backend */
    protected $_canBackendDirectCapture = true;

    /** info source path */
    protected $_infoBlockType = 'ops/info_redirect';

    protected $_formBlockType = 'ops/form_other';

    /** payment code */
    protected $_code = self::CODE;

    protected $_needsCartDataForRequest = true;

    public function getOpsCode($payment = null)
    {
        return '';
    }


}