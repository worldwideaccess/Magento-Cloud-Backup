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

class Aschroder_CloudBackup_Model_Collection extends Varien_Data_Collection {
	
	// This is just a little hack to get the collection working properly.
	public function addItem(Varien_Object $item) {
		parent::addItem($item);
		$this->_totalRecords = $this->_totalRecords + 1; 
	}
	
	
}
