<?php
class Downloader{

public function __construct(){

}

public function baseURL($url){
	preg_match("/^http:\/\/([^\/]*)/",$url,$matches);
	if (!isset($matches[1]))
		return false;

	return $matches[1];
}

public function FileExtension($url){
	$split = explode(".",$url);
	return $split[count($split) - 1];
}

public function topLevelDomain($url){
	$url = $this->baseURL($url);
	$split = explode('.',$url);
	return $split[count($split)-1];
}
 
public function fetchData($urls){

	/* MAKE IT LOOK LIKE A BROWSER (Firefox on a Mac) */
	$header = array();
	$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
	$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
	$header[] = "Cache-Control: max-age=0";
	$header[] = "Connection: keep-alive";
	$header[] = "Keep-Alive: 300";
	$header[] = "Accept-Charset: utf-8;q=0.7,*;q=0.7";
	$header[] = "Accept-Language: en-us,en;q=0.5";
	$header[] = "Pragma: "; // browsers keep this blank. 

	$chs = array();
	$datas = array();
	$cmh = curl_multi_init();
	foreach($urls as $url){

		$toplevel = $this->topLevelDomain($url);
		$extension = trim($this->FileExtension($url));

		// Only download the following toplevel domains:
		$toplevels = array(
			"com","org","edu","net","uk",
			"us","tv","gov","info"
		);
		$keepit = false;

		foreach($toplevels as $top){
			if (stristr($toplevel,$top) !== FALSE){
				$keepit = true;
			}
		}
		if (!$keepit)
			continue;

		// Don't download the following extensions.
		$extensions = array(
			"jpg","png","gif","jpeg","bmp",
			"swf","js","css","pdf","wmv","ppt"
		);

		foreach($extensions as $ext){
			if (stristr($extension,$ext) !== FALSE){
				$ditchit = true;
				break;
			}else{
				$ditchit = false;
			}
		}

		if ($ditchit)
			continue;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_REFERER, 'http://www.facebook.com/');
		curl_setopt($ch, CURLOPT_USERAGENT, 
			'Mozilla/5.0 (Macintosh; U; Intel Mac OS ' . 
			'X 10.5; en-US; rv:1.9.1.7) Gecko/20091221 Firefox/3.5.7'
		);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

		// if this is a new session: 
		curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);

		/* set URL */
		curl_setopt($ch, CURLOPT_URL, $url);

		/* TO VERIFY THE PEER's CERTIFICATE: */
		//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		//curl_setopt($ch, CURLOPT_CAINFO, "./server.crt");

		/* Don't Verify peer certificate: */
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	
		/* MUST set CURLOPT_COOKIEJAR to a file for CURL to use cookies */
		curl_setopt($ch, CURLOPT_COOKIESESSION, 1);
		curl_setopt($ch, CURLOPT_COOKIEJAR, "./cookies.txt");
		curl_setopt($ch, CURLOPT_COOKIEFILE, "./cookies.txt");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$chs[$url] = $ch;
		curl_multi_add_handle($cmh,$ch);
	} // END LOOP THROUGH URL BATCH

	/* Get all that stuff we just downloaded */
	do{
		$rc = curl_multi_exec($cmh, $threads);
	} while ($threads > 0);

	foreach($chs as $key=>$ch){
		$html = curl_multi_getcontent($ch);
		curl_multi_remove_handle($cmh, $ch);
		curl_close($ch);
		$datas[$key] = $html;
	}

	return $datas;
} // END fetchData 

} // END CLASS
?>
