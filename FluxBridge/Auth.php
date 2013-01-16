<?php
namespace FluxBridge;
use DB;

class Auth {
	private $db = null;
	private $cfg = null;
	private $loggedIn = false;
	public $pun_user = null;
	
	function __construct(&$db, &$cfg)
	{
		$this->db = $db;
		$this->cfg = $cfg;
	}
	
	// sets fluxbb cookie
	// returns true on success, false otherwise
	public function login($username, $password, $save_pass = '0')
	{
		if($this->isLoggedIn())
			return true;
		
		// retrieve user infos
		$result = $this->db->exec('SELECT * FROM '.$this->cfg->db['prefix'].'users WHERE username=?', $username);
		if($this->db->count() == 0) return false; // TODO an error message would be better
		$this->pun_user = $result[0];
		
		$hasher = new \PasswordHash(8, false);
		$check = $hasher->CheckPassword($password, $this->pun_user['password']);
		if($check)
		{
			// Send a new, updated cookie with a new expiration timestamp
			$expire = ($save_pass == '1') ? time() + 1209600 : time() + $this->cfg->pun_config['o_timeout_visit'];
			$this->pun_setcookie($this->pun_user['id'], $this->pun_user['password'], $expire);
			$this->loggedIn = true;
			return true;
		}
		else
		{
			$this->pun_user = null;
			$this->loggedIn = false;
			return false;
		}
	}
	
	// clears fluxbb cookie
	public function logout()
	{
		if($this->isLoggedIn())
		{
			// remove cookie
			$this->pun_setcookie($this->pun_user['id'], $this->pun_user['password'], time()-10);
			$this->loggedIn = false;
			$this->pun_user = null;
		}
	}
	
	// checks fluxbb cookie, returns true if logged in or false otherwise
	// code taken from fluxbb/include/functions.php, check_cookie()
	public function isLoggedIn()
	{
		// if that's set to true, we've already passed the check during this request.
		if($this->loggedIn)
			return true;
		
		$now = time();

		// If the cookie is set and it matches the correct pattern, then read the values from it
		if (isset($_COOKIE[$this->cfg->cookie['name']]) && preg_match('%^(\d+)\|([0-9a-fA-F]+)\|(\d+)\|([0-9a-fA-F]+)$%', $_COOKIE[$this->cfg->cookie['name']], $matches))
		{
			$cookie = array(
				'user_id'			=> intval($matches[1]),
				'password_hash' 	=> $matches[2],
				'expiration_time'	=> intval($matches[3]),
				'cookie_hash'		=> $matches[4],
			);
		}
		else
			return false;

		// If it has a non-guest user, and hasn't expired
		if (isset($cookie) && $cookie['user_id'] > 1 && $cookie['expiration_time'] > $now)
		{
			// If the cookie has been tampered with
			if ($this->forum_hmac($cookie['user_id'].'|'.$cookie['expiration_time'], $this->cfg->cookie['seed'].'_cookie_hash') != $cookie['cookie_hash'])
			{
				// I don't like this piece of code.
				$expire = $now + 31536000; // The cookie expires after a year
				$this->pun_setcookie(1, $this->pun_hash(uniqid(rand(), true)), $expire);
				//set_default_user();
				return false;
			}

			// Check if there's a user with the user ID and password hash from the cookie
			//$result = $db->query('SELECT u.*, g.*, o.logged, o.idle FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'groups AS g ON u.group_id=g.g_id LEFT JOIN '.$db->prefix.'online AS o ON o.user_id=u.id WHERE u.id='.intval($cookie['user_id'])) or error('Unable to fetch user information', __FILE__, __LINE__, $db->error());
			//$pun_user = $db->fetch_assoc($result);
			// [REPLACEMENT]
			// retrieve and save user infos
			$result = $this->db->exec('SELECT * FROM '.$this->cfg->db['prefix'].'users WHERE id='.$cookie['user_id']);
			if($this->db->count() == 0) return false; // TODO an error message would be better
			$this->pun_user = $result[0];
			// [/REPLACEMENT]
			
			
			// If user authorisation failed
			if (!isset($this->pun_user['id']) || $this->forum_hmac($this->pun_user['password'], $this->cfg->cookie['seed'].'_password_hash') !== $cookie['password_hash'])
			{
				// I don't like this piece of code.
				$expire = $now + 31536000; // The cookie expires after a year
				$this->pun_setcookie(1, $this->pun_hash(uniqid(rand(), true)), $expire);
				//set_default_user();
				return false;
			}

			// Send a new, updated cookie with a new expiration timestamp
			$expire = ($cookie['expiration_time'] > $now + $this->cfg->pun_config['o_timeout_visit']) ? $now + 1209600 : $now + $this->cfg->pun_config['o_timeout_visit'];
			$this->pun_setcookie($this->pun_user['id'], $this->pun_user['password'], $expire);
			
			//[INSERTION]
			return true;
			//[/INSERTION]
		}
		else
			//set_default_user();
			return false;
	}
	
	// imported as-is (FluxBB 1.5.2)
	private function forum_hmac($data, $key, $raw_output = false)
	{
		if (function_exists('hash_hmac'))
			return hash_hmac('sha1', $data, $key, $raw_output);

		// If key size more than blocksize then we hash it once
		if (strlen($key) > 64)
			$key = pack('H*', sha1($key)); // we have to use raw output here to match the standard

		// Ensure we're padded to exactly one block boundary
		$key = str_pad($key, 64, chr(0x00));

		$hmac_opad = str_repeat(chr(0x5C), 64);
		$hmac_ipad = str_repeat(chr(0x36), 64);

		// Do inner and outer padding
		for ($i = 0;$i < 64;$i++) {
			$hmac_opad[$i] = $hmac_opad[$i] ^ $key[$i];
			$hmac_ipad[$i] = $hmac_ipad[$i] ^ $key[$i];
		}

		// Finally, calculate the HMAC
		$hash = sha1($hmac_opad.pack('H*', sha1($hmac_ipad.$data)));

		// If we want raw output then we need to pack the final result
		if ($raw_output)
			$hash = pack('H*', $hash);

		return $hash;
	}
	
	// imported as-is (FluxBB 1.5.2)
	private function pun_hash($str)
	{
		return sha1($str);
	}
	
	// imported as-is (FluxBB 1.5.2)
	private function pun_setcookie($user_id, $password_hash, $expire)
	{
		//global $cookie_name, $cookie_seed;

		$this->forum_setcookie($this->cfg->cookie['name'], $user_id.'|'.$this->forum_hmac($password_hash, $this->cfg->cookie['seed'].'_password_hash').'|'.$expire.'|'.$this->forum_hmac($user_id.'|'.$expire, $this->cfg->cookie['seed'].'_cookie_hash'), $expire);
	}
	
	// imported as-is (FluxBB 1.5.2)
	private function forum_setcookie($name, $value, $expire)
	{
		//global $cookie_path, $cookie_domain, $cookie_secure;

		// Enable sending of a P3P header
		header('P3P: CP="CUR ADM"');

		if (version_compare(PHP_VERSION, '5.2.0', '>='))
			setcookie($name, $value, $expire, $this->cfg->cookie['path'], $this->cfg->cookie['domain'], $this->cfg->cookie['secure'], true);
		else
			setcookie($name, $value, $expire, $this->cfg->cookie['path'].'; HttpOnly', $this->cfg->cookie['domain'], $this->cfg->cookie['secure']);
	}
}
?>
