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
 * Backup related functionality. 
 * 
 */
 
class Aschroder_CloudBackup_Model_Backup {
	
		private $logTo = "mage";
		private $NUM_MONTHS = 3; // but you can change it if you want
			
		public function daily() {
			
			// If we have enabled autobackup - then run the backup process now
			if(Mage::helper('cloudbackup')->getAuto()) {
				$this->createBackup();
			}
			
			// If we have enabled autocleanup
			if(Mage::helper('cloudbackup')->getAutoCleanup()) {
				$this->pruneOldBackups();
			}
		}
		
		/*
		* Prune old backups
		*/
		public function pruneOldBackups() {
			
			$current_bucket =  Mage::getStoreConfig('system/cloudbackup/bucket_name');
			
			if (!$current_bucket) {
				$this->log("No bucket is set, we cannot prune old backups.");
				return;
			}	
			
			$s3 =  Mage::helper('cloudbackup')->getS3Client();
			
			try {
				
				$bucket_contents = $s3->getBucket($current_bucket);
				
				$cutoff_date = new Zend_Date();
				$cutoff_date->sub($this->NUM_MONTHS, Zend_Date::MONTH);
				
				foreach ($bucket_contents as $key) {
					
					$bucket_date = new Zend_Date($key['time'], Zend_Date::TIMESTAMP);
					
					if ($bucket_date->compare($cutoff_date) == -1 ) {
						
						$this->log($key['name'] . " was created on $bucket_date and is older than " . 
							$this->NUM_MONTHS . " months, deleting.");
						$s3->deleteObject($current_bucket, $key['name']);
						
					}
					
				}
				
			} catch (Exception $e) {
				Mage::logException($e);
				$this->log("Failed to while pruning old backups - please see exception.log for details.");
				return;
			}
			
		}
		
		/*
		 * Smoke Test function see: ShakeDown.php
		 */
		public function shakeDown() {
			
			$this->logTo = "echo";
			$s3 =  Mage::helper('cloudbackup')->getS3Client();

			// Create the backup and send it to Amazon - don't clean up, otherwise they WILL be different...
			list($bucket_name, $key_name) = $this->createBackup(true, false);
			
			// Download it again - save it in /tmp
    		$s3->getObject($bucket_name, $key_name, "/tmp" . DS. $key_name);
    		
    		// Extract it in /tmp
    		$tar = new Archive_Tar("/tmp" . DS. $key_name);
			$tar->extract("/tmp/test") or die ("Could not extract files!");

			// Diff it to check all the files are there - ignore errors about symlinks etc
			// TODO: why do we need to remove var/*  
			// because cache and log entries can change during the running of this test...
			
    		$output = exec("diff -rq . /tmp/test 2> /dev/null | grep -v var/");  
    		$this->log($output);
    		
    		// We expect only the uploaded backup to be different - because we do cleanup=false
			if ($output == "Only in .: " . $key_name) {
				echo "test passed\n";
				//clean up tmp we're all good
				exec("rm -f /tmp" . DS. $key_name);
				exec("rm -rf /tmp/test");
			} else {
				echo "test failed\n";
				// We don't clean up after a failure - so we can investigate...
			}
			
		}

		// Backup the whole store
		
		public function createBackup ($sendOffsite= true, $cleanup = true) {
			
			// the directory to backup
			$backupdir = Mage::getBaseDir();
			
			// User may override the default $magebase/var/backups directory e.g. /tmp
			$backupLocation = Mage::helper('cloudbackup')->getBackupLocation();
			
			$filename = "cloudbackup-" . date("Y-m-d-h-i-s") . ".tar.gz";
			$manifest_file = "cloudbackup-manifest.xml";
			$bucket = Mage::helper('cloudbackup')->getBucket();
			$s3 =  Mage::helper('cloudbackup')->getS3Client();
			
			// Run some sanity checks
			// 1 - can we write to $backupLocation
			if (!is_writable($backupLocation)) {
				throw new Exception ("Cannot write to: $backupLocation. Please make this directory writable, 
				or specify a different backup location in the Cloud Backup settings.");
			}
			
			$this->log("Creating backup file of $backupdir in $backupLocation named $filename. Backup will be sent to S3 bucket $bucket.");
			
			try {
				$db_backup = $this->createDatabaseBackup($backupdir);
				$this->writeManifest($backupLocation, $manifest_file, $db_backup->getFilename());
				$this->createTar($backupdir, $backupLocation, $filename);
				
				if($sendOffsite)
					$this->sendToCloudBackup($s3, $bucket, $backupLocation, $filename);
				
				if($cleanup)
			    	$this->cleanUp($backupLocation, $manifest_file, $filename, $db_backup);
			    	
				$this->log("Created backup file and sent to S3. Sending success email to admin");
				Mage::helper('cloudbackup')->sendSuccessEmail($bucket, $filename);
				
				return array($bucket,$filename);
				
			} catch (Exception $e) {
				$this->log("Unable to create backup file. Sending failure email to admin: ". $e->getMessage());
		    	Mage::helper('cloudbackup')->sendFailEmail($bucket, $filename, $e->getMessage());
		    	throw $e; // bubble this back up
			}
			
		}
		
		private function createDatabaseBackup($backupdir) {
			
			$db = Mage::getModel('backup/db');
        	$backup   = Mage::getModel('backup/backup')
                ->setTime(time())
                ->setType('db')
                ->setPath(Mage::getBaseDir("var") . DS . "backups");

            $db->createBackup($backup);
            
            if (!$backup->exists() || !$backup->getSize()) {
            	// missing or 0 size backup
            	throw new Exception ("Unable to create DB backup. CloudBackup Aborted");
            }
            
            return $backup;
		}
		
		private function writeManifest($backuplocation, $manifest_file, $dbname) {
			
			$file = fopen ($backuplocation . DS . $manifest_file, "w");
			
			if(!$file) {
				$this->log("Could not write Manifest File: " . $backuplocation . DS . $manifest_file);
				throw new Exception ("Could not write Manifest File: " . $backuplocation . DS . $manifest_file);
			}
			
			fwrite($file, '<?xml version="1.0" encoding="UTF-8" ?>\n');
			fwrite($file, "<cloudbackup>\n");
			fwrite($file, "<version>0.2.3</version>\n");
			fwrite($file, "<db>$dbname</db>\n");
			fwrite($file, "<tar>lib/Archive_Tar</tar>\n");
			fwrite($file, "</cloudbackup>");
			fclose ($file);  
			
		}
		
		private function createTar($backupdir, $backupLocation, $filename) {
			
			set_include_path(get_include_path() . 
    					PATH_SEPARATOR . Mage::getBaseDir('app') . 
						'/code/community/Aschroder/CloudBackup/lib/');
			require_once('Archive/Tar.php'); 
			
			$files = scandir($backupdir);
			// remove . and ..
			$files = array_diff($files, array(".", ".."));
			
			if ($exclude = Mage::helper('cloudbackup')->getExclude()) {
				$this->log("Excluding: " . $exclude);
				$files = array_diff($files, explode(",", $exclude));
			}
			
			// Sometimes the store basedir will not be the CWD, 
			// so make our urls absolute for Archive_Tar
			$abs_files = array();
			foreach ($files as $file) {
				$abs_files[] = $backupdir . DS . $file;
			}
			
			$this->log("Backing up: " . print_r($abs_files, true));
			
			$tar = new Archive_Tar($backupLocation . DS . $filename, "gz");
			
			if ($tar->create($abs_files)) {
			    $this->log("Backup complete");
			} else {
			    $this->log("Could not create backup file: " . $filename);
			    throw new Exception ("Could not write Backup Tar File: " .  $filename);
			}
		}
		
		
		private function sendToCloudBackup($s3, $bucket, $backupLocation, $filename) {
    		// Upload the file
			$upload = $s3->inputFile($backupLocation . DS . $filename);
			$this->log("Uploading: " . $backupLocation . DS . $filename . " to " . $bucket);
			
			if ($s3->putObject($upload, $bucket, $filename)) {
				$this->log("...Upload successful");
			} else {
				$this->log("...Upload failed");
				throw new Exception ("Unable to upload backup");
			}
			
		}
		
		private function cleanUp($backupLocation, $manifest_file, $filename, $db_backup) {
			
			//delete the manifest
			if (file_exists($backupLocation. DS . $manifest_file)) 
				unlink($backupLocation. DS . $manifest_file);
			//delete the tgz
			if (file_exists($backupLocation. DS . $filename)) 
				unlink($backupLocation. DS . $filename);
			//delete the db backup
			$db_backup->deleteFile();
		}
		
		//Coming soon...
		public function restore($bucket_name, $key_name) {
			
			// First thing we do when restoring is backup the whole current store locally (DB included)
			// Just incase this has been done acidentally
			$this->createBackup(false, false);
			
			// Then we have to fetch the requested bucket and key
			
			
			// Then we extract each element from the tar file
			// Begin with the manifest xml file - it _may_ contain information relevant to what we do here.
			
			
			// Can we delete and overwrite the whole directories?
			// we can't just tar out over top, because, for example, a local override might persist 
			// beyond the restore and continue to mess up a broken store.
			
			
			// How about restoring the db backup?
			// what happens if we just kick off a mysql import
			// does the store need to 'go down' while it's importing
			// can we do that with a listener on all requests that serves up a 'woops page'
			
			
			// Can we do an ajax-based progress indicator?
			
			//what happens if the actual php files running get hosed - or do they all get pulled into memory?
			
		}
		
		private function log($msg) {
			
			if ($this->logTo == "mage") {
				Mage::log($msg);
			} else if ($this->logTo == "echo") {
				echo $msg . "\n";
			} else if ($this->logTo == "none") {
				return;
			} else {
				throw new Exception("No logging defined");
			}
		}
		
}