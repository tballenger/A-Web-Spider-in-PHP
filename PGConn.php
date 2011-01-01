<?php

class DBClass{

	private static $connString = CONN_STRING;

	public function __construct(){
		$this->conn = pg_pconnect(self::$connString,
			PGSQL_CONNECT_FORCE_NEW);
	}

	public function _connect($new=false){
		// Pass TRUE to force a new connection.
		if ($new){
			return pg_connect(self::$connString,PGSQL_CONNECT_FORCE_NEW);
		}else{
			if (!is_resource($this->conn))
				$this->conn = pg_pconnect(self::$connString);
		}
	}

	public function _close(){
		pg_close($this->conn);
	}

	public function _reset(){

		if (isset($this->conn))
			$this->_close($this->conn);

		$this->conn = $this->_connect(true);
	}

	public function getResource($query){

		$rescon = $this->_connect(true);

		$this->sql		= $query;
		$this->res 		= pg_query($rescon,$query);

		while(!$this->res){
			$rescon = $this->_connect(true);
			$this->res = pg_query($rescon,$query);
		}

		return $this->res;
	}

	public function query($SQL){

		$this->_connect();

		while(pg_connection_busy($this->conn)){
			; // Do nothing while the connection is busy...
		}

		if (pg_query($this->conn,$SQL)){
			return true;
		} else {
			//echo "\n".pg_last_error($this->conn)."\n";
			$this->_reset();
			return false;
		}

	}

	public function getNextLine($query){
		if (isset($this->sql) && $this->sql == $query && is_resource($this->res)){
			return pg_fetch_array($this->res, NULL, PGSQL_ASSOC);
		}else{
			return pg_fetch_array($this->getResource($query),NULL, PGSQL_ASSOC);
		}
	}

	public function sanitize($data,$type){
		$this->_connect();
		if($type==A_STRING){
			return trim(pg_escape_string($this->conn, $data)); 
		}else if ($type==EDGE){
			foreach($data as $key=>$dat){
				$data[$key] = trim(pg_escape_string($this->conn,$dat));
			}
		}else if ($type==PARSED_PAGE){
			if (!is_array($data))
				return false;
			foreach($data as $elem_name=>$num_array){
				if ($elem_name == 'url' || $elem_name == 'base_url' || $elem_name == 'cms')
					continue;
				foreach($num_array as $key=>$string){
					$data[$elem_name][$key] = trim(pg_escape_string($this->conn,$string));
				}
				$data[$elem_name] = array_unique($data[$elem_name]);
			}
		}else if ($type==VECTOR){
			foreach($data as $key=>$value){
				$mykey = trim(pg_escape_string($this->conn, $key));
				$myval = trim(pg_escape_string($this->conn, $value));
				unset($data[$key]);
				$data[$mykey] = $myval;
			}	
		}else if ($type==MATRIX){
			if (!is_array($data)){
				return;
			}
			foreach($data as $key=>$dat){
				if (!is_array($dat)){
					return;
				}
				foreach($dat as $ke=>$val){
					$data[$key][$ke] = trim(pg_escape_string($this->conn,$val));
				}
			}
		}else if ($type==CONTEXT_ARRAY){
			foreach($data as $key=>$d){
				$mytype = "";
				if ($key=="matrix"){
					$mytype = MATRIX;
				}
				$data[$key] = $this->sanitize($d,$mytype);
			}
		}
		return $data;
	}

	public function deposit($data,$type){
		$this->_connect();
		$data = $this->sanitize($data,$type);
		if($type==EDGE){
			$SQL = "INSERT INTO edges (l_to,l_to_full,l_from,l_from_full,time) VALUES ('".
				$data['to'].
				"','".$data['to_full'].
				"','".$data['from'].
				"','".$data['from_full'].
				"','".time()."');";
			return $this->query($SQL);	
		}else if($type==CONTEXT_ARRAY){
			$SQL = "INSERT INTO urls (url,vector,matrix,time,cms) VALUES ".
				"('".$data['url'].
				"','".$data['vector'].
				"','".serialize($data['matrix']).
				"','".time().
				"','".$data['cms']."');";
			//echo $SQL;
			return $this->query($SQL);
		}else if($type==PARSED_PAGE){ 
			$SQL = "INSERT INTO data (base_url,url,ps,spans,articles,comments,titles,time,cms) ".
				"VALUES ('".$data['base_url'].
				"','".$data['url'].
				"','".serialize($data['ps']).
				"','".serialize($data['spans']).
				"','" .serialize($data['articles']).
				"','".serialize($data['comments']).
				"','".serialize($data['titles']).
				"','".time().
				"','".$data['cms']."');"; 
			return $this->query($SQL);
		}else{
			return false;
		}
		return false;
	}

	public function __destruct(){
		if (is_resource($this->conn))
			pg_close($this->conn);
	}	
}
?>
