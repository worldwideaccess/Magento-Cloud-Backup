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
 *This little script will basically 'smoke test' the extension by:
 *
 * making a backup
 * sending it to amazon
 * cleaning up
 * downloading it
 * untarring it in /tmp
 * diffing the downloaded extract with the store
 * ensuring they are the same
 * 
 * Notes:
 * If you run this on an actively being used site - then expect differences as new sessions are created etc
 * 
 * On a Mac with MAMP you'd run this like from the root of the magento install:
 * /Applications/MAMP/bin/php5/bin/php -f app/code/community/Aschroder/CloudBackup/ShakeDown.php
 *
 * @author   Ashley Schroder
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @copyright Copyright 2010 World Wide Access
 */
set_include_path(get_include_path() . PATH_SEPARATOR . getcwd());
require_once 'app/Mage.php';

Varien_Profiler::enable();
Mage::setIsDeveloperMode(true);
ini_set('display_errors', 1);

umask(0);
Mage::app('default');

// We try to turn off the logging
$before = Mage::getStoreConfig('dev/log/active');
Mage::getModel('core/config')->saveConfig('dev/log/active', false);

$obj = Mage::getModel('cloudbackup/backup');
$obj->shakeDown();

Mage::getModel('core/config')->saveConfig('dev/log/active', $before);


?>
