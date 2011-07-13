<?php
/**
 * Eek this is not very elegant, how else can we force certain code to execute when an admin page loads?
 * 
 * @author Ashley Schroder
 */
class Aschroder_CloudBackup_Model_Form_Activation extends Varien_Data_Form_Element_Abstract {
	
    /**
     * We return nothing, the front end type is a textfield....
     *
     * @return array
     */
    public function toOptionArray() {

    	// check activation key exists and if we have been activated
		$key =  Mage::getStoreConfig('system/cloudbackup/activation');
    	
		
    	if ($key) {
    		
			if (Mage::getStoreConfig('system/cloudbackup/access') && 
					Mage::getStoreConfig('system/cloudbackup/secret')) {
						
				Mage::log("Your cloudbackup is activated");
				
			} else {
				
				try {
					
					$result = Mage::helper('cloudbackup')->activate();
					Mage::log("Activated: " . $result);
					Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__("Activation complete, please allow several minutes for the servers to update, before making your first backup."));
					
				} catch (Exception $e) {
					
					Mage::getSingleton('adminhtml/session')->addException($e, Mage::helper('adminhtml')->__('Could not Activate Cloud Backup.') . $e->getMessage());
				}
				
				
			}
    	}
    	
    	return array();
    }
	
}
