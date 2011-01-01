<?php
/* These constants define the various datatypes */
/* to be processed. */

define('A_STRING', 		0x1); // Self explanatory
define('PARSED_PAGE',	0x2); // Page processed into an array of page elements Array('ps'=>[p tag contents],'spans'...'titles'...'articles'..'comments')
define('EDGE',			0x3); // Symbolizes a link from one page to another Array('to'=>1974971693716,'from'=>9187394671356)
define('VECTOR',		0x4); // Word count vector for spam filtering
define('MATRIX',		0x5); // Context matrix
define('CONTEXT_ARRAY',	0x6); // Array('vector'=>[word count vector],'matrix'=>[context matrix],'url'=>198571651795)


define("CONN_STRING","YOUR CONNECTION STRING GOES HERE");
define("BATCH_SIZE","120"); // How many urls to process at a time?  This will affect memory consumption.
define("MAX_FRONTIER",10000000); // Since URLS found grows very quickly; we'll want to limit breadth for the sake of timeliness

?>
