<?php
/**
 * Shop System Plugins:
 * - Terms of Use can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/_TERMS_OF_USE
 * - License can be found under:
 * https://github.com/wirecard/magento-ee/blob/master/LICENSE
 */

use WirecardEE\PaymentGateway\Service\Logger;

class WirecardEE_PaymentGateway_Model_Observer
{
    public function controllerFrontInitBefore()
    {
        $this->registerComposerAutoloader();
        $this->registerServices();
    }

    /**
     * Registers the composer autoloader.
     */
    private function registerComposerAutoloader()
    {
        $vendorPath = Mage::getBaseDir('lib') . '/WirecardEE/vendor/';

        if (! file_exists($vendorPath . 'autoload.php')) {
            throw new RuntimeException('Unable to include the Composer autoloader.');
        }

        /** @noinspection PhpIncludeInspection */
        require_once($vendorPath . 'autoload.php');
    }

    /**
     * @throws Mage_Core_Exception
     */
    private function registerServices()
    {
        Mage::register('logger', new Logger());
    }
}
