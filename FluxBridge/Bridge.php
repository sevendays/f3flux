<?php

/*
 * Bridge.php
 * 
 * Copyright 2013 Luca Zamboni <zamboluca@gmail.com>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 * 
 */

namespace FluxBridge;

class Bridge {
	
	// FluxBB root dir
	protected $fluxbb_root;
	// is it vanilla FluxBB or the GameZoo one?
	protected $vanilla_fluxbb;
	// Holds connection to FluxBB database
	public $db;
	// FluxBB's $pun_config loaded from db
	public $pun_config = null;
	// FluxBB's user data loaded from db
	public $pun_user = null;
	// FluxBB's cookie config
	private $_cookie = null;
	// FluxBB's db config
	private $_db = null;
	
	private $loggedIn = false;
	
	/* functions:
	1- auth user
	2- retrieve user from id
	3- retrieve user from username
	*/
	
	// authenticates the current user via the cookie
	// returns true or false
	// populates pun_user
	public function isLoggedIn()
	{
		// if that's set to true, we've already passed the check during this request.
		if($this->loggedIn)
			return true;
		
		$now = time();

		// If the cookie is set and it matches the correct pattern, then read the values from it
		if (isset($_COOKIE[$this->_cookie['name']]) && preg_match('%^(\d+)\|([0-9a-fA-F]+)\|(\d+)\|([0-9a-fA-F]+)$%', $_COOKIE[$this->_cookie['name']], $matches))
		{
			$cookie = array(
				'user_id'			=> intval($matches[1]),
				'password_hash' 	=> $matches[2],
				'expiration_time'	=> intval($matches[3]),
				'cookie_hash'		=> $matches[4],
			);
		}
		else
		{
			$this->pun_user = null;
			$this->loggedIn = false;
			return false;
		}

		// If it has a non-guest user, and hasn't expired
		if (isset($cookie) && $cookie['user_id'] > 1 && $cookie['expiration_time'] > $now)
		{
			// If the cookie has been tampered with
			if ($this->forum_hmac($cookie['user_id'].'|'.$cookie['expiration_time'], $this->_cookie['seed'].'_cookie_hash') != $cookie['cookie_hash'])
			{
				$expire = $now - 3600; // delete the cookie
				$this->pun_setcookie(1, $this->pun_hash(uniqid(rand(), true)), $expire);
				$this->pun_user = null;
				$this->loggedIn = false;
				return false;
			}

			// Check if there's a user with the user ID and password hash from the cookie
			// retrieve and save user infos
			$result = $this->db->exec('SELECT * FROM '.$this->_db['prefix'].'users WHERE id='.$cookie['user_id']);
			if($this->db->count() == 0) // no such name
			{
				$expire = $now - 3600; // delete the cookie
				$this->pun_setcookie(1, $this->pun_hash(uniqid(rand(), true)), $expire);
				$this->pun_user = null;
				$this->loggedIn = false;
				return false;
			}
			$this->pun_user = $result[0];
			
			// If user authorisation failed
			if (!isset($this->pun_user['id']) || $this->forum_hmac($this->pun_user['password'], $this->_cookie['seed'].'_password_hash') !== $cookie['password_hash'])
			{
				$expire = $now - 3600; // delete the cookie
				$this->pun_setcookie(1, $this->pun_hash(uniqid(rand(), true)), $expire);
				$this->pun_user = null;
				$this->loggedIn = false;
				return false;
			}

			// Send a new, updated cookie with a new expiration timestamp
			$expire = ($cookie['expiration_time'] > $now + $this->pun_config['o_timeout_visit']) ? $now + 1209600 : $now + $this->pun_config['o_timeout_visit'];
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
	
	// sets fluxbb cookie
	// returns true on success, false otherwise
	// populates pun_user
	public function login($username, $password, $save_pass = false)
	{
		if(empty($username) or empty ($password))
			return false;
		
		if($this->loggedIn)
			return true;
		
		// retrieve user infos
		$result = $this->db->exec('SELECT * FROM '.$this->_db['prefix'].'users WHERE username=?', $username);
		if($this->db->count() == 0)
		{
			$this->pun_user = null;
			$this->loggedIn = false;
			return false;
		}
		$this->pun_user = $result[0];
		
		$authorized = false;
		
		// GameZoo login method using PHPass
		if(!$this->vanilla_fluxbb)
		{
			$hasher = new \PasswordHash(8, false);
			$authorized = $hasher->CheckPassword($password, $this->pun_user['password']);
		}
		else // vanilla FluxBB login method
		{
			// copying our variables to FLuxBB's code (login.php)
			$form_password = $password;
			$cur_user = $this->pun_user;
			
			// BEGIN c&p from login.php
			$form_password_hash = pun_hash($form_password); // Will result in a SHA-1 hash
			
			// If there is a salt in the database we have upgraded from 1.3-legacy though haven't yet logged in
			if (!empty($cur_user['salt']))
			{
				if (sha1($cur_user['salt'].sha1($form_password)) == $cur_user['password']) // 1.3 used sha1(salt.sha1(pass))
				{
					$authorized = true;

					//$db->query('UPDATE '.$db->prefix.'users SET password=\''.$form_password_hash.'\', salt=NULL WHERE id='.$cur_user['id']) or error('Unable to update user password', __FILE__, __LINE__, $db->error());
					$this->db->exec('UPDATE'.$this->_db['prefix'].'users SET password=\''.$form_password_hash.'\', salt=NULL WHERE id='.$cur_user['id']) or trigger_error('Unable to update user password');
				}
			}
			// If the length isn't 40 then the password isn't using sha1, so it must be md5 from 1.2
			else if (strlen($cur_user['password']) != 40)
			{
				if (md5($form_password) == $cur_user['password'])
				{
					$authorized = true;

					//$db->query('UPDATE '.$db->prefix.'users SET password=\''.$form_password_hash.'\' WHERE id='.$cur_user['id']) or error('Unable to update user password', __FILE__, __LINE__, $db->error());
					$this->db->exec('UPDATE '.$this->_db['prefix'].'users SET password=\''.$form_password_hash.'\' WHERE id='.$cur_user['id']) or trigger_error('Unable to update user password');
				}
			}
			// Otherwise we should have a normal sha1 password
			else
				$authorized = ($cur_user['password'] == $form_password_hash);
			// END c&p from login.php
		}
		
		// set cookie or say bye
		if($authorized)
		{
			// Send a new, updated cookie with a new expiration timestamp
			$expire = ($save_pass) ? time() + 1209600 : time() + $this->pun_config['o_timeout_visit'];
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
	// no return
	public function logout()
	{
		if($this->loggedIn)
		{
			// remove cookie
			$this->pun_setcookie($this->pun_user['id'], $this->pun_user['password'], time()-10);
			$this->loggedIn = false;
			$this->pun_user = null;
		}
	}
	
	// imported as-is (FluxBB 1.5.3)
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
	
	// imported as-is (FluxBB 1.5.3)
	private function pun_hash($str)
	{
		return sha1($str);
	}
	
	// imported as-is (FluxBB 1.5.3) + slightly modified
	private function pun_setcookie($user_id, $password_hash, $expire)
	{
		//global $cookie_name, $cookie_seed;
		$this->forum_setcookie($this->_cookie['name'], $user_id.'|'.$this->forum_hmac($password_hash, $this->_cookie['seed'].'_password_hash').'|'.$expire.'|'.$this->forum_hmac($user_id.'|'.$expire, $this->_cookie['seed'].'_cookie_hash'), $expire);
	}
	
	// imported as-is (FluxBB 1.5.3)
	private function forum_setcookie($name, $value, $expire)
	{
		//global $cookie_path, $cookie_domain, $cookie_secure;
		// Enable sending of a P3P header
		header('P3P: CP="CUR ADM"');
		if (version_compare(PHP_VERSION, '5.2.0', '>='))
			setcookie($name, $value, $expire, $this->_cookie['path'], $this->_cookie['domain'], $this->_cookie['secure'], true);
		else
			setcookie($name, $value, $expire, $this->_cookie['path'].'; HttpOnly', $this->_cookie['domain'], $this->_cookie['secure']);
	}
	
	function __construct($fluxbb_root, $is_vanilla)
	{
		if($is_vanilla === true) $this->vanilla_fluxbb = true;
		
		require_once($fluxbb_root."config.php");
		$this->_db['host'] = $db_host;
		$this->_db['name'] = $db_name;
		$this->_db['username'] = $db_username;
		$this->_db['password'] = $db_password;
		$this->_db['prefix'] = $db_prefix;
		$this->_cookie['name'] = $cookie_name;
		$this->_cookie['domain'] = $cookie_domain;
		$this->_cookie['path'] = $cookie_path;
		$this->_cookie['secure'] = $cookie_secure;
		$this->_cookie['seed'] = $cookie_seed;
		
		// open connection to FluxBB DB
		$this->db = new \DB\SQL('mysql:host='.$db_host.';dbname='.$db_name, $db_username, $db_password);
		
		// retrieve pun_config from database
		$result = $this->db->exec('SELECT * FROM '.$db_prefix.'config');
		
		foreach($result as $row)
		{
			$this->pun_config[$row['conf_name']] = $row['conf_value'];
		}
		
		// set initial variables values
		$this->loggedIn = false;
	}
}

