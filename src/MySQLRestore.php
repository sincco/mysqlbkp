<?php
# NOTICE OF LICENSE
#
# This source file is subject to the Open Software License (OSL 3.0)
# that is available through the world-wide-web at this URL:
# http://opensource.org/licenses/osl-3.0.php
#
# -----------------------
# @author: Iván Miranda
# @version: 1.0.0
# -----------------------
# Create & validate a token string for user's data
# -----------------------

namespace Sincco\Tools;

use League\CLImate\CLImate;

final class MySQLRestore extends \stdClass {
	/**
	 * Host where the database is located
	 */
	private $host;
	/**
	 * Username used to connect to database
	 */
	private $username;
	/**
	 * Password used to connect to database
	 */
	private $passwd;
	/**
	 * Database to backup
	 */
	private $dbName;
	/**
	 * Database charset
	 */
	private $charset;
	/**
	 * Database connection
	 */
	private $conn;

	private $climate;
	/**
	 * Constructor initializes database
	 */
	function __construct($host, $username, $passwd, $dbName, $dir, $file, $zip = true) {
		$this->host       = $host;
		$this->username   = $username;
		$this->passwd     = $passwd;
		$this->dbName     = $dbName;
		$this->conn       = $this->initializeDatabase();
		$this->backupDir  = $dir;
		$this->backupFile = $file;
		$this->climate = new CLImate;
	}
	protected function initializeDatabase() {
		try {
			$hostname = "mysql:host=".$this->host.";dbname=".$this->dbName;
			$conn = New \PDO($hostname, $this->username, trim($this->passwd), [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);
		} catch (Exception $e) {
			$errorInfo = sprintf('%s: %s in %s on line %s.',
				'Database Error',
				$err,
				$err->getFile(),
				$err->getLine()
			);
			echo $errorInfo;
		}
		return $conn;
	}

	public function restoreDb() {
		try {
			$sql = '';
			$multiLineComment = true;
			$backupDir = $this->backupDir;
			$backupFile = $this->backupFile;
			$this->climate->green('Restore ' . $backupDir . '/' . $backupFile);
			/**
			 * Gunzip file if gzipped
			 */
			$backupFileIsGzipped = substr($backupFile, -3, 3) == '.gz' ? true : false;
			if ($backupFileIsGzipped) {
				if (!$backupFile = $this->gunzipBackupFile()) {
					throw new Exception("ERROR: couldn't gunzip backup file " . $backupDir . '/' . $backupFile);
				}
			}
			/**
			* Read backup file line by line
			*/
			$handle = fopen($backupDir . '/' . $backupFile, "r");
			if ($handle) {
				while (($line = fgets($handle)) !== false) {
					$line = ltrim(rtrim($line));
					if (strlen($line) > 1) { // avoid blank lines
						$lineIsComment = false;
						if (preg_match('/^\/\*/', $line)) {
							$multiLineComment = true;
							$lineIsComment = true;
						}
						if ($multiLineComment or preg_match('/^\/\//', $line)) {
							$lineIsComment = true;
						}
						if (!$lineIsComment) {
							$sql .= $line;
							if (preg_match('/;$/', $line)) {
								// execute query
								$query = $this->conn->prepare($sql);
								if($query->execute()) {
									if (preg_match('/^CREATE TABLE `([^`]+)`/i', $sql, $tableName)) {
										$this->climate->lightGreen("Table: `" . $tableName[1] . "`");
									}
									$sql = '';
								} else {
									throw new Exception("ERROR: SQL execution error: ");
								}
							}
						} else if (preg_match('/\*\/$/', $line)) {
							$multiLineComment = false;
						}
					}
				}
				fclose($handle);
			} else {
				throw new Exception("ERROR: couldn't open backup file " . $backupDir . '/' . $backupFile);
			} 
		} catch (Exception $e) {
			print_r($e->getMessage());
			return false;
		}
		if ($backupFileIsGzipped) {
			unlink($backupDir . '/' . $backupFile);
		}
		$this->climate->green('OK');
		return true;
	}
	/*
	 * Gunzip backup file
	 *
	 * @return string New filename (without .gz appended and without backup directory) if success, or false if operation fails
	 */
	protected function gunzipBackupFile() {
		// Raising this value may increase performance
		$bufferSize = 4096; // read 4kb at a time
		$error = false;
		$source = $this->backupDir . '/' . $this->backupFile;
		$dest = $this->backupDir . '/' . date("Ymd_His", time()) . '_' . substr($this->backupFile, 0, -3);
		$this->climate->green('Gunzipping ' . $source . '... ');
		// Remove $dest file if exists
		if (file_exists($dest)) {
			if (!unlink($dest)) {
				return false;
			}
		}
		
		// Open gzipped and destination files in binary mode
		if (!$srcFile = gzopen($this->backupDir . '/' . $this->backupFile, 'rb')) {
			return false;
		}
		if (!$dstFile = fopen($dest, 'wb')) {
			return false;
		}
		while (!gzeof($srcFile)) {
			// Read buffer-size bytes
			// Both fwrite and gzread are binary-safe
			if(!fwrite($dstFile, gzread($srcFile, $bufferSize))) {
				return false;
			}
		}
		fclose($dstFile);
		gzclose($srcFile);
		$this->climate->green('OK');
		// Return backup filename excluding backup directory
		return str_replace($this->backupDir . '/', '', $dest);
	}
	/**
	 * Prints message forcing output buffer flush
	 *
	 */
	public function obfPrint ($msg = '', $lineBreaksBefore = 0, $lineBreaksAfter = 1) {
		if (!$msg) {
			return false;
		}
		$output = '';
		if (php_sapi_name() != "cli") {
			$lineBreak = "<br />";
		} else {
			$lineBreak = "\n";
		}
		if ($lineBreaksBefore > 0) {
			for ($i = 1; $i <= $lineBreaksBefore; $i++) {
				$output .= $lineBreak;
			}                
		}
		$output .= $msg;
		if ($lineBreaksAfter > 0) {
			for ($i = 1; $i <= $lineBreaksAfter; $i++) {
				$output .= $lineBreak;
			}                
		}
		if (php_sapi_name() == "cli") {
			$output .= "\n";
		}
		echo $output;
		if (php_sapi_name() != "cli") {
			ob_flush();
		}
		flush();
	}
}