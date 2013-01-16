<?php
namespace FluxBridge;
class Cfg {
	
	private $file = null;
	
	public $db = array();
	public $cookie = array();
	public $pun_config = array();
	
	function __construct($filename)
	{
		if(!isset($filename) || empty($filename)) return;
		if(!is_null($this->file) && $this->file == $filename) return;
		$this->file = $filename;
		require_once($filename);
		$this->db['host'] = $db_host;
		$this->db['name'] = $db_name;
		$this->db['username'] = $db_username;
		$this->db['password'] = $db_password;
		$this->db['prefix'] = $db_prefix;
		
		$this->cookie['name'] = $cookie_name;
		$this->cookie['domain'] = $cookie_domain;
		$this->cookie['path'] = $cookie_path;
		$this->cookie['secure'] = $cookie_secure;
		$this->cookie['seed'] = $cookie_seed;
	}
	
	public function readBoardConfig(&$db)
	{
		$this->pun_config = array();
		
		$result = $db->exec('SELECT * FROM '.$this->db['prefix'].'config');
		if($db->count() == 0) return; // TODO an error message would be better
		
		foreach($result as $row)
		{
			$this->pun_config[$row['conf_name']] = $row['conf_value'];
		}
	}
}
?>
