<?php

namespace FluxBridge;
use DB;

class Bridge {
	private $fluxbb_root = null;
	
	public $db = null; // F3 database instance
	public $cfg = null; // FluxBB configs array
	public $auth = null; // auth class
	
	function __construct($fluxbb_root)
	{
		$this->fluxbb_root = $fluxbb_root; // TODO check validl
		
		// read config.php
		$this->cfg = new Cfg($fluxbb_root.'config.php');
		
		// establish database connection.
		$this->db = new DB\SQL(
			'mysql:host='.$this->cfg->db['host'].';dbname='.$this->cfg->db['name'],
			$this->cfg->db['username'],
			$this->cfg->db['password']
		);
		
		// read forum config from database
		$this->cfg->readBoardConfig($this->db);
		
		// initialize Auth methods
		$this->auth = new Auth($this->db, $this->cfg);
		
		// 
	}
}
?>
