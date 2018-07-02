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

final class MySQLBkp extends \stdClass {
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
	/**
	 * Backup directory where backup files are stored 
	 */
	private $backupDir;
	/**
	 * Output backup file
	 */
	private $backupFile;
	/**
	 * Use gzip compression on backup file
	 */
	private $gzipBackupFile;
	/**
	 * Content of standard output
	 */
	private $output;

	private $climate;
	/**
	 * Constructor initializes database
	 */
	public function __construct($host, $username, $passwd, $dbName, $dir, $zip = true) {
		$this->host            = $host;
		$this->username        = $username;
		$this->passwd          = $passwd;
		$this->dbName          = $dbName;
		$this->conn            = $this->initializeDatabase();
		$this->backupDir       = $dir;
		$this->backupFile      = $this->dbName.'-'.date("Ymd_His", time()).'.sql';
		$this->gzipBackupFile  = $zip;
		$this->output          = '';
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

	public function backupTables($tables = '*') {
		try {
			if($tables == '*') {
				$tables = array();
				$query = $this->conn->prepare('SHOW TABLES;');
				$query->execute();
				foreach ($query->fetchAll() as $row) {
					$tables[] = $row[0];
				}
			} else {
				$tables = is_array($tables) ? $tables : explode(',', str_replace(' ', '', $tables));
			}
			
			$this->climate->green('Backup in ' . $this->backupDir.'/'.$this->backupFile);
			$sql = 'CREATE DATABASE IF NOT EXISTS `'.$this->dbName."`;\n\n";
			$sql .= 'USE `'.$this->dbName."`;\n\n";
			$this->saveFile($sql);

			/**
			* Iterate tables
			*/
			foreach($tables as $table) {
				$this->climate->lightGreen("Table `".$table."` ...".str_repeat('.', 50-strlen($table)));
				/**
				 * CREATE TABLE
				 */
				$sql = "/**\n * " . $table . " \n */\n". 'DROP TABLE IF EXISTS `'.$table.'`;';
				$query = $this->conn->prepare('SHOW CREATE TABLE `'.$table.'`;');
				$query->execute();
				$row = $query->fetchAll();
				$row = array_pop($row);
				$sql .= "\n\n".$row[1].";\n\n";
				/**
				 * INSERT INTO
				 */
				$query = $this->conn->prepare('SELECT COUNT(*) FROM `'.$table.'`;');
				$query->execute();
				$row = $query->fetchAll();
				$row = array_pop($row);
				$numRows = $row[0];
				// Split table in batches in order to not exhaust system memory 
				$batchSize = 1000; // Number of rows per batch
				$numBatches = intval($numRows / $batchSize) + 1; // Number of while-loop calls to perform
				for ($b = 1; $b <= $numBatches; $b++) {
					$query = $this->conn->prepare('SELECT * FROM `'.$table.'` LIMIT '.($b*$batchSize-$batchSize).','.$batchSize);
					$query->execute();
					$result = $query->fetchAll(\PDO::FETCH_ASSOC);
					$registers = [];
					foreach ($result as $row) {
						$fields = [];
						foreach ($row as $field => $value) {
							if (is_null($value)) {
								$fields[] = 'NULL';
							} else {
								$value = addslashes($value);
								$value = str_replace("\n","\\n",$value);
								$fields[] = '"'.$value.'"' ;
							}
						}
						$registers[] = '(' . implode(',', $fields) . ')';
					}
					if (count($registers) > 0) {
						$sql .= 'INSERT INTO `'.$table.'` VALUES ' . implode(',', $registers) . ";\n\n";
					}
					// var_dump($sql); die();
					$this->saveFile($sql);
					$sql = '';
				}
			}
			if ($this->gzipBackupFile) {
				$this->gzipBackupFile();
			}
			$this->climate->green(" OK");
		} catch (Exception $e) {
			print_r($e->getMessage());
			return false;
		}
		return true;
	}
	/**
	 * Save SQL to file
	 * @param string $sql
	 */
	protected function saveFile(&$sql) {
		if (!$sql) return false;
		try {
			if (!file_exists($this->backupDir)) {
				mkdir($this->backupDir, 0777, true);
			}
			file_put_contents($this->backupDir.'/'.$this->backupFile, $sql, FILE_APPEND | LOCK_EX);
		} catch (Exception $e) {
			print_r($e->getMessage());
			return false;
		}
		return true;
	}
	/*
	 * Gzip backup file
	 *
	 * @param integer $level GZIP compression level (default: 9)
	 * @return string New filename (with .gz appended) if success, or false if operation fails
	 */
	protected function gzipBackupFile($level = 9) {
		if (!$this->gzipBackupFile) {
			return true;
		}
		$source = $this->backupDir . '/' . $this->backupFile;
		$dest =  $source . '.gz';
		$this->climate->green('Gzipping ' . $dest . '... ');
		$mode = 'wb' . $level;
		if ($fpOut = gzopen($dest, $mode)) {
			if ($fpIn = fopen($source,'rb')) {
				while (!feof($fpIn)) {
					gzwrite($fpOut, fread($fpIn, 1024 * 256));
				}
				fclose($fpIn);
			} else {
				return false;
			}
			gzclose($fpOut);
			if(!unlink($source)) {
				return false;
			}
		} else {
			return false;
		}
		
		$this->climate->lightGreen('OK');
		return $dest;
	}
}