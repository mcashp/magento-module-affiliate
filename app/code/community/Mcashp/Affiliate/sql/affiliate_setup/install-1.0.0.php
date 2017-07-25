<?php

$installer = $this;
/* @var $installer Mcashp_Affiliate_Model_Resource_Setup */

$installer->startSetup();

$attributeTracking = Mcashp_Affiliate_Helper_DataInterface::ATTRIBUTE_TRACKING;
$attributeCommission = Mcashp_Affiliate_Helper_DataInterface::ATTRIBUTE_COMMISSION;

$installer->addAttribute('quote', $attributeTracking, array('type' => 'varchar'));

$installer->addAttribute('order', $attributeTracking, array('type' => 'varchar'));
$installer->addAttribute('order', $attributeCommission, array('type' => 'decimal'));

$installer->addAttribute('invoice', $attributeTracking, array('type' => 'varchar'));
$installer->addAttribute('invoice', $attributeCommission, array('type' => 'decimal'));

/* @var $setup Mage_Customer_Model_Resource_Setup */
$setup = Mage::getModel('customer/entity_setup', 'core_setup');
$setup->addAttribute('customer', 'mcashp_tracking', array(
    'type' => 'varchar',
    'input' => 'text',
    'label' => 'MCASHP tracking',
    'global' => 1,
    'visible' => 1,
    'required' => 0,
    'user_defined' => 1,
    'default' => '',
    'visible_on_front' => 1,
));
if (version_compare(Mage::getVersion(), '1.6.0', '<=')) {
    $customer = Mage::getModel('customer/customer');
    $attrSetId = $customer->getResource()->getEntityType()->getDefaultAttributeSetId();
    $setup->addAttributeToSet('customer', $attrSetId, 'General', $attributeTracking);
} elseif (version_compare(Mage::getVersion(), '1.4.2', '>=')) {
    Mage::getSingleton('eav/config')
        ->getAttribute('customer', $attributeTracking)
        ->setData('used_in_forms', array('adminhtml_customer'))
        ->save()
    ;
}

$installer->endSetup();
