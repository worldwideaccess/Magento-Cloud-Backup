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
class Aschroder_CloudBackup_Block_Backup_Bucket_Grid extends Mage_Adminhtml_Block_Widget_Grid {

  protected function _construct() {
        $this->setSaveParametersInSession(true);
        $this->setId('offsiteBackupsBucketGrid');
        $this->setDefaultSort('name', 'desc');
  		$this->setFilterVisibility(false);
        $this->setPagerVisibility(false);    }

    /**
     * Init backups collection
     */
    protected function _prepareCollection() {
    	
    	//maybe we should just populate a table with the webservice results..then just load a db collection...
    	$s3 =  Mage::helper('cloudbackup')->getS3Client();
    	$bucket_name = $this->getRequest()->getParam('name');
 		$collection = new Aschroder_CloudBackup_Model_Collection();
 		
    	try {
	    	$bucket = $s3->getBucket($bucket_name);
    	} catch (Exception $e) {
    		//Set an error an an empty collection
    		Mage::getSingleton('adminhtml/session')->addException($e, Mage::helper('adminhtml')->__('Could not list backups, if you have only just activated please try again shortly.'));
    		$this->setCollection($collection);
         	return parent::_prepareCollection();
    	}
 		
 		
 		foreach ($bucket as $key) {
    		$obj = new Varien_Object();
    		$obj->setData('name', $key['name']);
    		$obj->setData('time', new Zend_Date($key['time'], Zend_Date::TIMESTAMP));
    		$obj->setData('size', $key['size']);
    		$collection->addItem($obj);
    	}
         $this->setCollection($collection);
         return parent::_prepareCollection();
    }

    /**
     * Configuration of grid
     */
    protected function _prepareColumns() {

       $this->addColumn('Backup Name', array(
            'header'    => Mage::helper('backup')->__('Backup Name'),
            'type'      => 'text',
            'index'      => 'name',
            'sortable'  => false,
            'filter'    => false
        ));
       $this->addColumn('Created', array(
            'header'    => Mage::helper('backup')->__('Created'),
            'type'      => 'datetime',
            'index'      => 'time',
            'sortable'  => false,
            'filter'    => false
        ));
       $this->addColumn('Size', array(
            'header'    => Mage::helper('backup')->__('Size'),
            'type'      => 'number',
            'index'      => 'size',
            'sortable'  => false,
            'filter'    => false
        ));
        
        
  		$this->addColumn('view', array(
            'header'    => Mage::helper('backup')->__('Download'),
            'format'    => '<a href="' . $this->getUrl('*/*/download', array('time' => '$time', 'keyname' => '$name', 'bucket' => $this->getRequest()->getParam('name'))) .'">Download</a> ',
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
                'url'       => $this->getUrl('*/*/deletebackup', array('time' => '$time', 'keyname' => '$name', 'bucket' => $this->getRequest()->getParam('name'))),
                'caption'   => Mage::helper('adminhtml')->__('Delete'),
                'confirm'   => Mage::helper('adminhtml')->__('Are you sure you want to do this?')
            )),
            'index'     => 'type',
            'sortable'  => false
             ));

        return $this;
    }
    
     protected function _prepareLayout() {
    
	     $this->setChild('backButton',
	            $this->getLayout()->createBlock('adminhtml/widget_button')
	                ->setData(array(
	                    'label'     => Mage::helper('adminhtml')->__('Back'),
	                    'onclick'   => 'window.location.href=\''.$this->getUrl('*/*/').'\'',
	                    'class' => 'back'
	                ))
	        );
	        
	         return parent::_prepareLayout();
     }
        
}