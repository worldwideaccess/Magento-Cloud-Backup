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
 * Various Helper functions for the FBAShipping module
 *
 */

class Aschroder_CloudBackup_Helper_Data extends Mage_Core_Helper_Abstract {


	const XML_PATH_EMAIL_ADMIN_BACKUP_SUCCESS_NOTIFICATION = 'system_cloudbackup_success_email';
	const XML_PATH_EMAIL_ADMIN_BACKUP_FAILED_NOTIFICATION = 'system_cloudbackup_failed_email';
	const PRODUCT_TOKEN = '{ProductToken}AAQGQXBwVGtuDP+Xc4PAp8koqHPS5Sng7GSOn+i3mE2aF9GPwDBGPvIEzxqzxrTq65yivSW1Tz1wTZvwQbbx3Q9VrEp3a1O+nja/XwvYfhCDA0TAuF3isRJDgvHa5BEBE1imVVWEVThv37z0WOznrd2ypTiGi1F/gTxfOA1xu14GqBAH1X7NOAz2by6nAIls0jgFlQbhMK1DA4Yhw9SErD2SRoKbvKcCK22noCxHEyk8miljPrNBK4PLeGzbSqhoEekC/OPVw5mxDksJwZ7BS+sxCw8yA4/Q+ngzDJMXKvwpzo4QFKidtIM=';

	public function getS3Client() {
		
		set_include_path(get_include_path() . 
    					PATH_SEPARATOR . Mage::getBaseDir('app') . 
						'/code/community/Aschroder/CloudBackup/lib/');
    	require_once('S3.php');
    	
    	return new S3(Mage::helper('cloudbackup')->getAccess(), Mage::helper('cloudbackup')->getSecret(), 
    			true, self::PRODUCT_TOKEN.",".Mage::helper('cloudbackup')->getUserToken());
	}

	// Returns the current bucket - if none is defined or the defined one does not exist, 
	// it will create one. When creating if creation fails it will return false;
	
	public function getBucket() {
		
		$s3 = $this->getS3Client();
		$bucket =  Mage::getStoreConfig('system/cloudbackup/bucket_name');
		
		//If no bucket is defined, generate one
		if(!$bucket) {
			// generate a bucket name that is hopefully unique	
			$url = Mage::getStoreConfig('web/secure/base_url');
			$urlObject = Mage::getModel('core/url')->parseUrl($url);
			$bucket = $urlObject->getHost() .".". date("Y-m-d-h-i-s") .".cloudbackup";
		}
		
		//Check the bucket exists, if not make it
		if (@$s3->getBucket($bucket) === false) {
			// Try to create the bucket
		    if($s3->putBucket($bucket)) {
		    	Mage::helper('cloudbackup')->setBucket($bucket); // save the bucket name for next time
		    } else {
		    	
		    	// We couldn't make this bucket - maybe the name was not unique?
		    	Mage::log("Unable to create bucket named: " . $bucket . 
		    		". Please confirm this bucket does not already exist.");
		    	return false;
		    }
		}
		
		return $bucket;
		
	}
	
	/*
	 * This function will activate the product with Amazon if it has not 
	 * already been activated.
	 * 
	 * It requires the activation key to have been input in the
	 * admin screen and will log an error if it has not.
	 * 
	 * Activation retrieves the access and secret key required for 
	 * the application to access S3 and upload to S3.
	 * 
	 */
	public function activateIfRequired() {
		
		// check activation key exists and if we have been activated
		$key =  Mage::getStoreConfig('system/cloudbackup/activation');
		
		if(!$key){
			$msg = 
				"Error: Cannot activate product. 
				Reason: No Activation Key configured. 
				Solution: Please input an activation key in order to use the offsite backup functionality.";
			Mage::log($msg);
			throw new Exception($msg);
		}
		
		$activated = Mage::getStoreConfig('system/cloudbackup/access') 
			&& Mage::getStoreConfig('system/cloudbackup/secret');
			
		if ($activated) {
			
			//Nothing more we need to do.
			return;
			
		} else {
			
			// If we haven't then go ahead and activate
			return $this->activate();
			
		}
	}
	
	// Try to activate, and set the AWS keys if we can and return true
	// Otherwise throw an error
	public function activate() {
		
		set_include_path(get_include_path() . 
    					PATH_SEPARATOR . getcwd() . 
						'/app/code/community/Aschroder/CloudBackup/lib/');
	    	require_once('DevPay.php');
	    	
	    	$devPay = new DevPay();
	    	$result = $devPay->license(
	    			Mage::getStoreConfig('system/cloudbackup/activation'), 
	    			self::PRODUCT_TOKEN);
	    		
	    	if ($result) {
	    		
	    		$access = $result["access"];
	    		$secret = $result["secret"];
	    		$usertoken = $result["usertoken"];
	    		
				// send it to amazon and get back the access key and secret key (and user token?)
				Mage::helper('cloudbackup')->setAccess($access);
				Mage::helper('cloudbackup')->setSecret($secret);
				Mage::helper('cloudbackup')->setUserToken($usertoken);

				// Clear the cache after activating, so we get the right credentials
				Mage::app()->getCacheInstance()->flush();
				
				
				return true;
	    		
	    	} else {
	    		
				// If we cannot activate - throw exception
				$msg = 
					"Error: Cannot activate product. 
					Reason: Activation failed. 
					Solution: Please try again shortly and if problems persist contact support.";
				Mage::log($msg);
				throw new Exception($msg);
	    	}	
	}
	
	
	// Any time we use this it requires activation - 
	// will throw an exception if it cannot activate
	public function getAccess() {
		$this->activateIfRequired();
		return $this->unenc(Mage::getStoreConfig('system/cloudbackup/access'));
	}
	
	// Only used with the access key, so assume we are activated
	public function getSecret() {
		return $this->unenc(Mage::getStoreConfig('system/cloudbackup/secret'));
	}
	
	public function getUserToken() {
		return $this->unenc(Mage::getStoreConfig('system/cloudbackup/usertoken'));
	}
	
	public function setAccess($access) {
		Mage::getModel('core/config')->saveConfig('system/cloudbackup/access', $this->enc($access));
	}
	public function setSecret($secret) {
		Mage::getModel('core/config')->saveConfig('system/cloudbackup/secret', $this->enc($secret));
	}
	public function setUserToken($userToken) {
		Mage::getModel('core/config')->saveConfig('system/cloudbackup/usertoken', $this->enc($userToken));
	}
	
	public function getAuto() {
		return Mage::getStoreConfig('system/cloudbackup/auto');
	}
	public function getAutoCleanup() {
		return Mage::getStoreConfig('system/cloudbackup/autocleanup');
	}
	public function getNativeBackup() {
		return Mage::getStoreConfig('system/cloudbackup/nativedb');
	}
	public function getBackupLocation() {
		
		if (Mage::getStoreConfig('system/cloudbackup/location')) {
			return Mage::getStoreConfig('system/cloudbackup/location');
		} else {
				return Mage::getBaseDir() . DS . "var" . DS . "backups";
		}
	}
	public function getExclude() {
		return Mage::getStoreConfig('system/cloudbackup/exclude');
	}

	public function setBucket($bucket) {
		Mage::getModel('core/config')->saveConfig('system/cloudbackup/bucket_name', $bucket);
	}
	
	public function enc($string) {
		return Mage::helper('core')->encrypt($string);
	}
	
	public function unenc($string) {
		return Mage::helper('core')->decrypt($string);
	}

	public function sendSuccessEmail($bucket, $key) {
		
		$vars = array('bucket'=>$bucket, 'key'=>$key);
		return $this->_sendBackupNotificationEmail($vars, self::XML_PATH_EMAIL_ADMIN_BACKUP_SUCCESS_NOTIFICATION);

	}

	public function sendFailEmail($bucket, $key, $error) {
		
		$vars = array('bucket'=>$bucket, 'key'=>$key, 'error'=>$error);
		return $this->_sendBackupNotificationEmail($vars, self::XML_PATH_EMAIL_ADMIN_BACKUP_FAILED_NOTIFICATION);
	}

	/**
	 * Send transactional email
	 *
	 * @param Mage_Customer_Model_Customer|string $to
	 * @param string $templateConfigPath
	 * return boolean
	 */
	protected function _sendBackupNotificationEmail($vars, $template) {
		
		$to = Mage::getStoreConfig('system/cloudbackup/email');

		$translate = Mage::getSingleton('core/translate');
		/* @var $translate Mage_Core_Model_Translate */
		$translate->setTranslateInline(false);

		$mailTemplate = Mage::getModel('core/email_template');
		/* @var $mailTemplate Mage_Core_Model_Email_Template */

		$mailTemplate->setDesignConfig(array('area'=>'frontend'))
			->sendTransactional(
				$template,
				Mage::getStoreConfig(Mage_Customer_Model_Customer::XML_PATH_REGISTER_EMAIL_IDENTITY),
				$to,
				null,
				$vars
				);

		$translate->setTranslateInline(true);

		return true;
	}

}

