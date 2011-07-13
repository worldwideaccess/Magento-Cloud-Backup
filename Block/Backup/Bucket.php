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

class Aschroder_CloudBackup_Block_Backup_Bucket extends Mage_Adminhtml_Block_Template {
    
	public function __construct() {
        parent::__construct();
        $this->setTemplate('backup/list.phtml');
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->setChild('backButton',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
                    'label' => Mage::helper('backup')->__('Back to Buckets'),
                    'onclick' => "window.location.href='" . $this->getUrl('*/*/') . "'",
                    'class'  => 'task'
                ))
        );
        $this->setChild('offsiteBackupsBucketGrid',
            $this->getLayout()->createBlock('cloudbackup/backup_bucket_grid')
        );
    }

    public function getCreateButtonHtml()
    {
        return $this->getChildHtml('backButton');
    }

    public function getGridHtml()
    {
        return $this->getChildHtml('offsiteBackupsBucketGrid');
    }
}
