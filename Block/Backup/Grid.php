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
class Aschroder_CloudBackup_Block_Backup_Grid extends Mage_Adminhtml_Block_Widget_Grid {

  protected function _construct() {
        $this->setSaveParametersInSession(true);
        $this->setId('offsiteBackupsGrid');
        $this->setDefaultSort('name', 'desc');
        $this->setFilterVisibility(false);
        $this->setPagerVisibility(false);
        
    }

    /**
     * Init backups collection
     */
    protected function _prepareCollection() {
    	
    	$s3 =  Mage::helper('cloudbackup')->getS3Client();
 		$collection = new Aschroder_CloudBackup_Model_Collection();
    	
    	try {
	    	$buckets = $s3->listBuckets(true);
    	} catch (Exception $e) {
    		//Set an error an an empty collection
    		Mage::getSingleton('adminhtml/session')->addException($e, Mage::helper('adminhtml')->__('Could not list backups, if you have only just activated please try again shortly.'));
    		$this->setCollection($collection);
         	return parent::_prepareCollection();
    	}

    	$current_bucket =  Mage::getStoreConfig('system/cloudbackup/bucket_name');
    	
    	if (!$current_bucket) {
    		Mage::getSingleton('adminhtml/session')->addError("You have not configured a bucket for your backups yet.");
    	}
    	
 		foreach ($buckets['buckets'] as $bucket) {
    		$obj = new Varien_Object();
    		if(isset($bucket['time'])) {
	    		$obj->setData('name', $bucket['name']);
	    		$obj->setData('time', new Zend_Date($bucket['time'], Zend_Date::TIMESTAMP));
	    		$obj->setData('inuse', ($bucket['name'] == $current_bucket)?'Yes':'');
    		}
    		$collection->addItem($obj);
    	}
         $this->setCollection($collection);
         return parent::_prepareCollection();
    }

    /**
     * Configuration of grid
     */
    protected function _prepareColumns() {

       $this->addColumn('Bucket', array(
            'header'    => Mage::helper('backup')->__('Bucket'),
            'type'      => 'text',
            'index'      => 'name',
            'sortable'  => false,
            'filter'    => false
        ));
       $this->addColumn('Created', array(
            'header'    => Mage::helper('backup')->__('Created'),
            'type'      => 'datetime',
            'index'      => 'time',
            'width'     => '200px',
            'sortable'  => false,
            'filter'    => false
        ));
       $this->addColumn('In Use', array(
            'header'    => Mage::helper('backup')->__('In Use'),
            'type'      => 'text',
            'index'      => 'inuse',
            'width'     => '60px',
            'sortable'  => false,
            'filter'    => false
        ));
        
        $this->addColumn('view', array(
            'header'    => Mage::helper('backup')->__('View'),
            'format'    => '<a href="' . $this->getUrl('*/*/bucket', array('time' => '$time', 'name' => '$name')) .'">View</a> ',
            'index'     => 'type',
            'width'     => '100px',
            'sortable'  => false,
            'filter'    => false
        ));
         $this->addColumn('delete', array(
            'header'    => Mage::helper('backup')->__('Delete'),
            'type'      => 'action',
            'width'     => '80px',
            'filter'    => false,
            'sortable'  => false,
            'actions'   => array(array(
                'url'       => $this->getUrl('*/*/delete', array('time' => '$time', 'name' => '$name')),
                'caption'   => Mage::helper('adminhtml')->__('Delete'),
                'confirm'   => Mage::helper('adminhtml')->__('Are you sure you want to do this?')
            )),
            'index'     => 'type',
            'sortable'  => false
        ));

        return $this;
    }
}