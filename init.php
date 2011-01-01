<?php
require_once 'PGConn.php';
require_once 'Parse.php';
require_once 'Downloader.php';
require_once 'constants.php';

// Globalize database object.
global $db;

// Set up error logging...
ini_set("log_errors","On");
ini_set("log_errors_max_len","0");
ini_set("error_log","spider.errors.log");
ini_set("memory_limit","-1"); // You should probably change this if you're running it on a server.

// Disable libxml errors that would stop parsing:
libxml_use_internal_errors(true);

// Instantiate database object and curl routine object.
$db 		= new DBClass();
$get		= new Downloader();
$frontier  	= 0;
$batchno	= 0;

$firstLine = $db->getNextLine("SELECT l_to FROM edges LIMIT 1");
$this_time = time();

$backup_query = false;

while(true){

	if ($frontier==0){
		// Get seed set of URLS.
		$SQL = "SELECT url FROM urls LIMIT ".MAX_FRONTIER.";";
	}else if ($backup_query){
		fwrite(STDERR,"Uh oh, executing backup query...  " .
				"Fine if it only happens at the beginning.\n");
		$SQL = "SELECT DISTINCT l_to_full AS url FROM edges LIMIT ";
		$SQL.= MAX_FRONTIER.";";
		$backup_query = false;
	}else{
		$SQL = "SELECT DISTINCT a.l_to_full AS url FROM edges AS a LEFT OUTER";
		$SQL.= " JOIN (SELECT DISTINCT l_to_full FROM edges WHERE time < ";
		$SQL.= "$last_time) AS b ON a.l_to_full = b.l_to_full WHERE a.time >=";
		$SQL.= " $last_time AND b.l_to_full IS NULL LIMIT ".MAX_FRONTIER.";";
	}	

	$i=0;
	$urls = array();
	$batchno++;
	while($i<BATCH_SIZE && $u=$db->getNextLine($SQL)){
		$urls[] = $u['url'];
		$i++;
	}

	if(count($urls) == 0){	
		$last_time = $this_time;
		$this_time = time();
		if ($this_time - $last_time < 1 && $frontier != 0 && $frontier != 1){
			fwrite(STDERR, "Error with batch in Frontier #$frontier\n");
			$backup_query = true;
		}
		$batchno = 0;
		$frontier++; 
	}else{
		$raw_batch 	= $get->fetchData($urls);
		if ($batchno==0){
		}else{
			pcntl_wait($status); // Clear defunct child process
		}
		$pid = pcntl_fork();
		if ($pid == -1) {
			fwrite(STDERR, "Could not fork\n");
		} else if ($pid) {
			// we are the parent
			continue;
		} else {
			// we are the child
			foreach($raw_batch as $url=>$rawdata){
				$parser 	= new Parser();
				$child_db	= new DBClass();
				$data 		= $parser->_parse($url,$rawdata);
				if (!$data){
					continue;
				}else{
					$child_db->deposit($data, PARSED_PAGE);
				}
			}
			exit(0); // Return successfully.
		}
	}
}

?>
