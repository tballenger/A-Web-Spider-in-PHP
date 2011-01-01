<?php
$start = microtime(TRUE);

class Tagger Extends DOMDocument{
	public function _init($url){
		$this->url = $url;

		$html 	= file_get_contents($url);
		$tidy_config = array(
			"clean"=>true,
			"output-xhtml" => true,
			"show-body-only" => true,
			"wrap" => 0
		);
	
		$tidy = tidy_parse_string($html,$tidy_config,"UTF8");
		$tidy->cleanRepair();
		$html = $tidy;

		@$this->loadHTML($html);
		$this->baseURL();
		$this->long_test = FALSE;
	}

	public function baseURL(){
		$url = $this->url;
		preg_match("/^http:\/\/([^\/]*)/",$url,$matches);	
		if (!isset($matches[1])){
			return false;
		}
		$this->base = $matches[1];
	}

	public function curl_sess($body=TRUE,$url){
		$ch = curl_init();
		$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
        $header[] = "Accept-Charset: utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Pragma: "; // browsers keep this blank. 
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_REFERER, 'http://www.facebook.com/');
        curl_setopt($ch, CURLOPT_USERAGENT,
            'Mozilla/5.0 (Macintosh; U; Intel Mac OS ' .
            'X 10.5; en-US; rv:1.9.1.7) Gecko/20091221 Firefox/3.5.7');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        // if this is a new session: 
        curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);

        /* set URL */
        curl_setopt($ch, CURLOPT_URL, $url);

        /* Don't Verify peer certificate: */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        /* MUST set CURLOPT_COOKIEJAR to a file for CURL to use cookies */
        curl_setopt($ch, CURLOPT_COOKIESESSION, 1);
        curl_setopt($ch, CURLOPT_COOKIEJAR, "./cookies.txt");
        curl_setopt($ch, CURLOPT_COOKIEFILE, "./cookies.txt");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if (!$body)
			curl_setopt($ch, CURLOPT_NOBODY, true);		
		curl_setopt($ch, CURLOPT_HEADER, true);
		return curl_exec($ch);
	}

	public function is_wordpress(){
		// Check the base url...(duh)
		if (preg_match("/\.wordpress\./",$this->url))
			return true;

		// Look for relative links to wordpress directories.
		$as = $this->getElementsByTagName("a");
		foreach($as as $a){
			$href = $a->getAttribute("href");
			$relative = str_replace("http://".$this->base,"",$href);
			if ($relative == $href)
				continue;
			if (substr($relative,0,6) != "http://"){
				$wp_content = strstr($relative,"wp-content");
				$wp_admin	= strstr($relative,"wp-synhighlight");
				if ($wp_content !== FALSE || $wp_admin !== FALSE){
					return TRUE;
				}
			}else{
				continue;
			}
		}	
	
		// Now try to find the most common comment fingerprint.
		$ols = $this->getElementsByTagName("ol");
		foreach($ols as $ol){
			$class 	= $ol->getAttribute("class");
			$id		= $ol->getAttribute("id");
			if ($class == "commentlist" || $id == "commentlist"){
				$lis = $ol->getElementsByTagName("li");
				foreach($lis as $li){
					$id		= $li->getAttribute("id");
					if (preg_match("/comment-[0-9]*$/",$id)){
						return TRUE;
					}else{
					}	
				}
			}
		}
		return FALSE;
	}	

	public function is_blogger(){
		// Check the base url...
		if (preg_match("/\.blogspot\./",$this->url) || preg_match("/\.blogger\./",$this->url))
			return true;

		// Look for that unmistakable blogger fingerprint.
		$divs = $this->getElementsByTagName("div");
		foreach($divs as $div){
			$id = $div->getAttribute("id");
			if (preg_match("/Blog[0-9]*_comments-block-wrapper/",$id)){
				$dls = $div->getElementsByTagName("dl");
				foreach($dls as $dl){
					$id = $dl->getAttribute('id');
					if (preg_match("/comment[s]?-block/",$id)){
						return TRUE;
					}
				}
			}else{
			}
		}
		return FALSE;
	}

	public function is_joomla(){
		
		$metas = $this->getElementsByTagName("meta");
		foreach($metas as $meta){
			$name 		= $meta->getAttribute("name");
			$content 	= $meta->getAttribute("content");
			if (preg_match("/[Gg]enerator/",$name)){
				if (preg_match("/Joomla/",$content)){
					return TRUE;
				}
			}
		}

		if ($this->long_test){
			// First long test...
			$header	= explode("\r\n",$this->curl_sess(FALSE,$this->url));
			foreach($header as $head){
				if(preg_match("/Joomla/",$head)){
					return true;
				}
			}
			// Second long test...
			$test = "http://".$this->base."/?tp=1";
			$body = $this->curl_sess(TRUE,$test);	
			$searcha = "/\[none outline\]/";
			$searchb = "/mod-preview/";
			if (preg_match($searcha,$body) || preg_match($searchb,$body))
				return true;
			
		}
		return FALSE;		
	}
	
	public function is_drupal(){
		$tests = array();
		$header = explode("\r\n",$this->curl_sess(FALSE,$this->url));
		foreach($header as $head){
			if (preg_match("/Sun, 19 Nov 1978 05:00:00 GMT/",$head)){
				return true;
			}
		}	
		if ($this->long_test){
			// Change this to multi-curl for better efficiency:
			$tests[] = (@file_get_contents("http://".$this->base."/user") || 
				@file_get_contents(str_replace("www.","","http://".$this->base."/user"))) 
				? TRUE : FALSE;
			$tests[] = (@file_get_contents("http://".$this->base."/node") || 
				@file_get_contents(str_replace("www.","","http://".$this->base."/node"))) 
				? TRUE : FALSE;
			$tests[] = (@file_get_contents("http://".$this->base."/update.php") || 
				@file_get_contents(str_replace("www.","","http://".$this->base."/update.php"))) 
				? TRUE : FALSE;
			$tests[] = (@file_get_contents("http://".$this->base."/CHANGELOG.txt") || 
				@file_get_contents(str_replace("www.","","http://".$this->base."/CHANGELOG.txt"))) 
				? TRUE : FALSE;
			$tests[] = (@file_get_contents("http://".$this->base."/INSTALL.mysql.txt") || 
				@file_get_contents(str_replace("www.","","http://".$this->base."/INSTALL.mysql.txt"))) 
				? TRUE : FALSE;
			$tests[] = (@file_get_contents("http://".$this->base."/INSTALL.pgsql.txt") || 
				@file_get_contents(str_replace("www.","","http://".$this->base."/INSTALL.pgsql.txt"))) 
				? TRUE : FALSE; 
	
			$win = 0;
			foreach($tests as $test){
				if ($test)
					$win++;
			}	
			return ($win > 3) ? TRUE : FALSE; 
		} else {
			return false;
		}
		return false;
	}
}

//printf("time elapsed: %0.4f seconds.\n",(microtime(TRUE) - $start));

?>
