<?php
/**
 * Ho_Import
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    Ho
 * @package     Ho_Import
 * @copyright   Copyright © 2013 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 *
 *
 */
class Ho_Import_Model_Observer
{
    public function schedule() {
        $importCollection = Mage::getResourceModel('ho_import/system_import_collection');
        foreach ($importCollection as $import) {
            /** @var $import Ho_Import_Model_System_Import */
            $import->schedule(false);
        }

        Mage::getConfig()->cleanCache();
        $importCollection->cleanupCron();
    }

    public function process(Mage_Cron_Model_Schedule $cron) {
        //initialize the translations so that we are able to translate things.
        Mage::app()->loadAreaPart(
            Mage_Core_Model_App_Area::AREA_ADMINHTML,
            Mage_Core_Model_App_Area::PART_TRANSLATE
        );

        $cronName = $cron->getJobCode();
        $profile = str_replace('ho_import_', '', $cronName);

        try {
            /** @var Ho_Import_Model_Import $import */
            $import = Mage::getModel('ho_import/import');
            $import->setProfile($profile);
            $import->process();
        } catch (Exception $e) {
            Mage::helper('ho_import/log')->log($e->getMessage(), Zend_Log::CRIT);
            Mage::helper('ho_import/log')->log($e->getTraceAsString(), Zend_Log::CRIT);
        }

        Mage::helper('ho_import/log')->done();
    }

    public function progressLog(Varien_Event_Observer $event) {
        $name = str_replace('fastsimpleimport_', '', $event->getEvent()->getName());
        $name = str_replace('before_', '', $name);
        $name = ucfirst(str_replace('_',' ',$name)).'...';

        Mage::helper('ho_import/log')->log($name);
    }

    
    /**
     * @event catalog_product_edit_action
     * @param Varien_Event_Observer $observer
     */
    public function catalogProductEditAction(Varien_Event_Observer $observer) {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getProduct();

        // Is product assigned to import profile.
        if (! ($profile = $product->getData('ho_import_profile'))){
            return;
        }

        // Is lock attributes functionality enabled.
        $lockAttributes = sprintf('global/ho_import/%s/import_options/lock_attributes', $profile);
        $fieldMapNode = Mage::getConfig()->getNode($lockAttributes);
        if (!$fieldMapNode || !$fieldMapNode->asArray()) {
            return;
        }

        // Get the mapper.
        /** @var Ho_Import_Model_Mapper $mapper */
        $mapper = Mage::getModel('ho_import/mapper');
        $mapper->setProfileName($profile);
        $storeCode = $product->getStore()->getCode();

        // Check if attributes need to be locked.
        $attributes = $product->getAttributes();
        foreach ($attributes as $attribute) {
            /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
            $mapper->setStoreCode($attribute->isScopeStore() || $attribute->isScopeWebsite() ? $storeCode :'admin');

            $fieldConfig = $mapper->getFieldConfig($attribute->getAttributeCode());
            if (isset($fieldConfig['@'])) {
                $product->lockAttribute($attribute->getAttributeCode());
                $note = $attribute->getNote() ? $attribute->getNote()."<br />\n" : '';

                //scope global, website
                $note .= Mage::helper('ho_import')->__("Automatically filled by import %s", '<code>'.$profile.'</code>');
                $attribute->setNote($note);
            }
        }
    }
}
