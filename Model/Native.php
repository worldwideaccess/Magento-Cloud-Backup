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
 * 
 * Our Native DB dump function. 
 * 
 */
 
class Aschroder_CloudBackup_Model_Native {
	
	private $PATH_TO_MYSQLDUMP = ""; // if mysqldump binary is not on the webserver user's path
	
	// For example on a MAMP based OSX install you might use: 
	// private $PATH_TO_MYSQLDUMP = "/Applications/MAMP/Library/bin/";
	
	/**
	* Create backup using native mysqldump
	* 
	* Same signature as the core version.
	*
	* @param Mage_Backup_Model_Backup $backup
	* @return Mage_Backup_Model_Db
	*/
	public function createBackup(Mage_Backup_Model_Backup $backup) {
		
		$db = Mage::getResourceModel('sales/order')->getReadConnection()->getConfig();
		$host = $db['host'];
		$user = $db['username'];
		$pass = $db['password'];
		$db = $db['dbname'];
		
		// Based on http://php.net/manual/en/function.system.php
		$cmd = sprintf(
		    '%smysqldump --opt -h %s -u %s -p%s %s | gzip > %s',                                                 
		    $this->PATH_TO_MYSQLDUMP,
			$host,
			$user,
			$pass,
			$db,
			$backup->getPath() . DS . $backup->getFileName()
			);
		
		Mage::log("Running native backup command: $cmd");
		
		system($cmd);
		
		Mage::log("Created backup natively");
		
	}
		
}