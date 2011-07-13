<?php

/**
 * world-wide-access.com Cloud Backup
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
 *
 * @author   Ashley Schroder
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @copyright Copyright 2010 World Wide Access
 */
/**
 * Offiste Backup admin controller
 *
 */
class Aschroder_CloudBackup_BackupController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Backup list action
     */
    public function indexAction() {
    	
        if($this->getRequest()->getParam('ajax')) {
            $this->_forward('grid');
            return;
        }
        
        // check and warn about cron
        $schedules_pending = Mage::getModel('cron/schedule')->getCollection()
        			->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_PENDING)
        			->load();
        $schedules_complete = Mage::getModel('cron/schedule')->getCollection()
        			->addFieldToFilter('status', Mage_Cron_Model_Schedule::STATUS_SUCCESS)
        			->load();
        			
        if (sizeof($schedules_pending) == 0 || sizeof($schedules_complete) == 0) {
	       	$this->_getSession()->addError(Mage::helper('adminhtml')->__(
			       	'Error: It looks like your Magento cron job is not currently running. 
			       	In order to create backups automatically you need cron configured. 
			       	There is a guide to configuring cron <a href="http://www.magentocommerce.com/wiki/1_-_installation_and_configuration/how_to_setup_a_cron_job">here</a>. 
			       	Please <a href="mailto:contact@world-wide-access.com">contact us</a> for more information or if this message is wrong.'));
        }
        
        $backupLocation = Mage::helper('cloudbackup')->getBackupLocation();
        if (!is_writable($backupLocation)) { 
        		$this->_getSession()->addError(Mage::helper('adminhtml')->__(
        		"Error: Cannot write to $backupLocation. In order to create a backup the webserver must be able to write to $backupLocation. 
        		You can fix the permissions or specify a different backup location in the settings."));
        }
		
        $this->loadLayout();
        $this->_setActiveMenu('system');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('System'), Mage::helper('adminhtml')->__('System'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Tools'), Mage::helper('adminhtml')->__('Tools'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Offsite Backups'), Mage::helper('adminhtml')->__('Offsite Backup'));

        $this->_addContent($this->getLayout()->createBlock('cloudbackup/backup', 'backup'));

        $this->renderLayout();
    }

    /**
     * Backup list action
     */
    public function gridAction() {
        $this->getResponse()->setBody($this->getLayout()->createBlock('cloudbackup/backup_grid')->toHtml());
    }

    
	 /**
     * Create backup action - we use the underlying system backup - 
     * our dB override will pick up the system backup, and send it to 
     * S3, then depending on config, will clean up the system backup.
     * 
     */
    public function createAction() {
    	
        try {
        	Mage::getModel('cloudbackup/backup')->createBackup();
            $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('Backup successfully created and sent to S3'));
        }
        catch (Exception  $e) {
            $this->_getSession()->addException($e, Mage::helper('adminhtml')->__('Error while creating backup. If you have only just activated, please try again shortly. otherwise please see the Magento exception log for details'));
        }
        $this->_redirect('*/*');
    }

    /**
     * Download backup action
     */
    public function bucketAction() {

    	$this->loadLayout();
        $this->_setActiveMenu('system');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('System'), Mage::helper('adminhtml')->__('System'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Tools'), Mage::helper('adminhtml')->__('Tools'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Offsite Backups'), Mage::helper('adminhtml')->__('Offsite Backup'));

        $this->_addContent($this->getLayout()->createBlock('cloudbackup/backup_bucket', 'backup'));

        $this->renderLayout();
    	
    }

    /**
     * Delete bucket action
     */
    public function deleteAction() {
    	
        try {

        	$s3 =  Mage::helper('cloudbackup')->getS3Client();
        	$bucket_name = $this->getRequest()->getParam('name');
        	$bucket = $s3->getBucket($bucket_name);

 			if( count($bucket)) {
 				 $this->_getSession()->addError(Mage::helper('adminhtml')->__('Sorry, but you cannot delete a bucket full of backups...'));
 				 $this->_redirectReferer();
 				 return;
 			}
        	
        	$s3->deleteBucket($bucket_name);
            $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('Bucket was deleted'));
        }
        catch (Exception $e) {
        	 $this->_getSession()->addError(Mage::helper('adminhtml')->__('Could not delete bucket: '. $e->getMessage()));
        }

		 $this->_redirectReferer();
        
    }
    
	/**
     * Delete backup action
     */
    public function deletebackupAction() {
    	
        try {

        	$s3 =  Mage::helper('cloudbackup')->getS3Client();
        	$bucket_name = $this->getRequest()->getParam('bucket');
        	$key_name = $this->getRequest()->getParam('keyname');
        	$s3->deleteObject($bucket_name, $key_name);
        	
            $this->_getSession()->addSuccess(Mage::helper('adminhtml')->__('Backup was deleted'));
        }
        catch (Exception $e) {
        	 $this->_getSession()->addError(Mage::helper('adminhtml')->__('Could not delete bucket: '. $e->getMessage()));
        }

		 $this->_redirectReferer();
        
    }
    
	/**
     * Download backup action
     */
    public function downloadAction() {
    	 
    	try {
    		
    		$s3 =  Mage::helper('cloudbackup')->getS3Client();
    		$bucket_name = $this->getRequest()->getParam('bucket');
    		$key_name = $this->getRequest()->getParam('keyname');
    		
    		//fetch the file
    		$s3->getObject($bucket_name, $key_name, $key_name);
    		
    		// return the file
    		header('Content-type: application/x-compressed');
			header('Content-Disposition: attachment; filename="'.$key_name.'"');
			header('Content-length: '. filesize($key_name));
			readfile($key_name);
			
			//cleanup
			unlink($key_name);
			
    		return;
    	}
    	catch (Exception $e) {
    		$this->_getSession()->addError(Mage::helper('adminhtml')->__('Could not download backup: '. $e->getMessage()));
    		$this->_redirectReferer();
    	}

    }

    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')->isAllowed('system/tools/backup');
    }

    protected function _getSession()  {
        return Mage::getSingleton('adminhtml/session');
    }
}
