<?php

class Parser extends DOMDocument{

	public function _parse($url,$data){
		global $db;
		$this->url = $url;
 
		// Tidy up that HTML before parsing!
		$tidy_config = array( 
					 'clean' => true, 
					 'output-xhtml' => true, 
					 'show-body-only' => true, 
					 'wrap' => 0 
					 ); 

		$tidy = tidy_parse_string($data, $tidy_config, 'UTF8'); 
		$tidy->cleanRepair(); 
		$data = $tidy; 
		
		// Nice and tidy, now remove commonly problematic tags:
		$replace = array(
			"/<script.*?<\/\s?script>/s",	// JavaScript
			"/<style.*?<\/\s?style>/s",		// CSS
			"/<!--.*?-->/s"					// Multi-line Comments
		);

		$data = preg_replace($replace,"",$data); 

		// Now parse it!
		if (@$this->loadXML($data)){
			fwrite(STDERR,"1"); // Successfully parsed.
		}else if (@$this->loadHTML($data)){
			fwrite(STDERR,"1");
		}else{
			fwrite(STDERR,"2"); // Try parsing with other methods...
			return false;
		}

		$from_full 	= $this->url;
		$from 		= $this->baseURL($this->url);
		$this->base = $from;

		if (!$from){
			return false;
		}else{
			// Identify the CMS
			if ($this->is_wordpress()){
				$cms = "wp";
			}else if ($this->is_blogger()){
				$cms = "bs";
			}else if ($this->is_joomla()){
				$cms = "jm";
			}else if ($this->is_drupal()){
				$cms = "dp";
			}else{
				$cms = "un";
			}

			$this->cms = $cms;

			$data = array(
				'base_url'	=>$this->baseURL($this->url),
				'url'		=>$this->url,
				'ps'		=>array(),
				'spans'		=>array(),
				'titles'	=>array(),
				'comments'	=>array(),
				'articles'	=>array(),
				'cms' 		=>$cms
			);

			// Grab the bulk of the good stuff here
			$ps 	= $this->getElementsByTagName('p');
			foreach($ps as $p){
				$data['ps'][] = $p->nodeValue;
			}
			$spans  = $this->getElementsByTagName('span');
			foreach($spans as $span){
				$data['spans'][] = $span->nodeValue;
			}

			// Grab Titles
			$h1s	= $this->getElementsByTagName('h1');
			foreach($h1s as $h1){
				$data['titles'][] = $h1->nodeValue;
			}
			$h2s	= $this->getElementsByTagName('h2');
			foreach($h2s as $h2){
				$data['titles'][] = $h2->nodeValue;
			}
			$h3s	= $this->getElementsByTagName('h3');
			foreach($h3s as $h3){
				$data['titles'][] = $h3->nodeValue;
			}
		
			// Support for HTML5	
			$comms	= $this->getElementsByTagName('comment');
			foreach($comms as $comment){
				$data['comments'][] = $comment->nodeValue;
			}
			$arts	= $this->getElementsByTagName('article');
			foreach($arts as $art){
				$data['articles'][] = $art->nodeValue;
			}

			// Parse through links
			$as		= $this->getElementsByTagName('a');
			foreach($as as $a){
				if (stristr($a->getAttribute('href'),str_replace("www.","",$this->url)) === FALSE
						&& stristr($a->getAttribute('href'),"javascript:") === FALSE
						&& stristr($a->getAttribute('href'),"http://") !== FALSE){

					// The site is linking to someone else.  Save the edge.
					$to 	= $this->baseURL($a->getAttribute('href'));
					$to_full= $a->getAttribute('href');
					if (!$from || !$to){
						continue;
					}else{
						$edge 	= array(
							'from' 		=> $from,
							'from_full' => $from_full,
							'to' 		=> $to,
							'to_full'	=> $to_full
						);
						$db->deposit($edge,EDGE);
					}
				}else{
					// This site is linking to itself.  Ignore the link.
				}
			}
			$this->contextize($data);	
			return $data;
		}
	}

	public function contextize($data){
		global $db;
		$doc = "";
		foreach($data as $PorSpan){
			if (!is_array($PorSpan)){
				continue;
			}else{
				foreach($PorSpan as $content){
					$doc .= " ".$content;
				}
			}
		}

		$descriptors = array(
			0 => array("pipe","r"),
			1 => array("pipe","w"),
			2 => array("file","error.dat","a")
		);

		$process = proc_open("../PostProcessing/vectorize.out",$descriptors, $pipes);

		if (is_resource($process)){
			fwrite($pipes[0], $doc);
			fclose($pipes[0]);

			eval(stream_get_contents($pipes[1]));
			fclose($pipes[1]);

			$rc = proc_close($process);
		}

		$new = array();
		foreach($vector as $key=>$val){
			$new[] = $key;
			$new[] = $val;
		}

		$d = array(
			'vector' 	=> str_replace("\n","",implode(",",$new)),
			'matrix' 	=> array(),
			'url'		=> $this->url,
			'cms'		=> $this->cms
		);
		$db->deposit($d,CONTEXT_ARRAY);
	}

	public function is_wordpress(){
		// Check the base url.  Gives false positives on wp static content.
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
		// Check the base url.  Gives false positives on static content.
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
		$header	= explode("\r\n",$this->curl_sess(FALSE,$this->url));
		foreach($header as $head){
			if(preg_match("/Joomla/",$head)){
				return true;
			}
		}

		if ($this->long_test){
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
		if ($this->long_test){  // Takes really long (~4 seconds)
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
	

	public function baseURL($url){
		preg_match("/^http:\/\/([^\/]*)/",$url,$matches);	
		if (!isset($matches[1])){
			return false;
		}
		return $matches[1];
	}

}

?>
