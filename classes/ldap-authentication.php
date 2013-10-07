<?php

class LDAP_Authentication
{
	/**
	 * Privileged LDAP username
	 *
	 * @var string
	 */
	protected $_privileged_dn	= NULL;
	
	/**
	 * Privileged LDAP user's password
	 *
	 * @var string
	 */
	protected $_privileged_password = NULL;
	
	/**
	 * LDAP server hosts
	 *
	 * @var array
	 */
	protected $_ldap_hosts = array();
	
	/**
	 * LDAP connection port
	 *
	 * @var string
	 */
	protected $_ldap_port = NULL;
	
	/**
	 * LDAP base dn
	 *
	 * @var string
	 */
	protected $_base_dn = NULL;
	
	/**
	 * LDAP connection resource
	 *
	 * @var resource
	 */
	protected $_connection = NULL;
	
	/**
	 * WP Error Exceptioin Handler
	 * 
	 * @var string
	 */
	protected $_error = NULL;
	
	/**
	 * Ldap Constructor
	 *
	 * The constructor runs the ldap routines automatically
	 * whenever the class is instantiated.
	 */
	public function __construct($params = array())
	{
		$wordpress_ldap = new LDAP();
		
		$vars = array('_privileged_dn', '_privileged_password', '_ldap_hosts', '_ldap_port', '_base_dn');
		
		// Set all the ldap preferences, which can either be set
		// manually via the $params array above or via the config file
		foreach ($vars as $key)
		{
			$meta_key = substr($key, 1);
			
			if($key === '_ldap_hosts' && !is_array($key))
			{ 
				$this->$key = (isset($params[$key])) ? $params[$key] : explode(',', $wordpress_ldap->get_option($meta_key));
			}
			else
			{
				$this->$key = (isset($params[$key])) ? $params[$key] : $wordpress_ldap->get_option($meta_key);
			}
		}
		
		$this->_error = new WP_Error();
		
		// Authenticate secure credentials against ldap
		$this->_authenticate();
	}

	/**
	 * Ldap Destructor
	 * 
	 * Closes the connection to the LDAP server.
	 */
	public function __destruct()
	{
		if(ldap_unbind($this->_connection))
		{
			$this->_log_message('debug', 'Unboud from ldap server.');
		}
	}
		
	/**
	 * Connect
	 * 
	 * Opens a new connection to the LDAP server.
	 * 
	 * @throws Exception
	 * @throws ErrorException
	 */
	protected function _connect() 
	{
		if(!function_exists('ldap_connect'))
		{
			$this->_log_message('error', 'Function "ldap_connect" does not exist.');
			return FALSE;
		}
		
		if(!$this->_connection = @ldap_connect($this->_ldap_hosts[0], $this->_ldap_port))
		{
			unset($this->_ldap_hosts[0]);
		}
		else
		{
			foreach($this->_ldap_hosts as $host)
			{
				if($this->_connection = @ldap_connect($host, $this->_ldap_port))
				{
					$this->_ldap_hosts[0] = $host;
					break;
				}
			}
		}
		
		if(!empty($this->_connection))
		{
			$this->_log_message('debug', 'Connected to: '. $this->_ldap_hosts[0]);
			return TRUE;
		}
		else
		{
			$this->_log_message('debug', 'Could not establish a connection to the LDAP server');
			return FALSE;
		}
	}

	/**
	 * Authenticate
	 * 
	 * Connects to the LDAP server as a privileged user.
	 */
	protected function _authenticate()
	{
		if($this->_connect())
		{
			if(!function_exists('ldap_bind'))
			{
				$this->_log_message('error', 'Function "ldap_bind" does not exist.');
				return FALSE;
			}
			
			if(@ldap_bind($this->_connection, $this->_privileged_dn, $this->_privileged_password))
			{
				$this->_log_message('debug', 'Authenticated connection to ldap using secure credentials.');
				return TRUE;
			}
			else
			{
				$this->_log_message('error', 'Could not establish authentication with ldap');
				return FALSE;
			}
		}
	}
	
	/**
	 * Search
	 * 
	 * Searches the LDAP server for the given username.
	 * 
	 * @param string $username
	 */
	protected function _search($username = NULL)
	{
		$this->_log_message('debug', 'Initialized '. __FUNCTION__);
		
		if(empty($username))
		{
			$this->_log_message('error', 'Invalid username.');
			return FALSE;
		}
		
		if($this->_connection)
		{
			if(!function_exists('ldap_search'))
			{
				$this->_log_message('error', 'Function "ldap_search" does not exist.');
				return FALSE;
			}
			
			if($search = ldap_search($this->_connection, $this->_base_dn, "uid=$username"))
			{
				$num_entries = ldap_count_entries($this->_connection, $search);
	
				if ($num_entries === 1)
				{
					$this->_log_message('debug', 'LDAP found username: '. $username);
					return $search;
				}
				else
				{
					$this->_log_message('debug', 'LDAP could not retrieve a single entry.');
					return FALSE;
				}
			}
			else
			{
				$this->_log_message('error', ldap_errno($this->_connection) .' '. ldap_error($this->_connection));
				return FALSE;
			}
		}
		else
		{
			return FALSE;
		}
	}

	/**
	 * Log Message
	 * 
	 * Logs error messages to "./wp-content/debug.log"
	 * 
	 * @param string $level
	 * @param string $message
	 */
	protected function _log_message($level, $message) 
	{
	    if(WP_DEBUG === true)
	    {
	    	if(is_array($message) || is_object($message))
	      	{
	      		error_log(strtoupper($level) .' - '. date('Y-m-d g:i:s') .' -->'. print_r($message, true));
	      	} 
	      	else 
	      	{
	        	error_log(strtoupper($level) .' - '. date('Y-m-d g:i:s') .' -->'. $message);
	      	}
	    }
	}

	/**
	 * Check Credentials
	 * 
	 * Attempts to bind the provided credentials to the LDAP
	 * server. If the bind is successful, the username and 
	 * password combination is correct.
	 * 
	 * @param string $username
	 * @param string $password
	 */
	public function check_credentials($username = NULL, $password = NULL)
	{
		if(empty($username) || empty($password))
		{
			$this->_log_message('error', 'Invalid username and/or password.');
			return FALSE;
		}
		
		$search_dn = 'uid='. $username .','. $this->_base_dn;
		
		if(@ldap_bind($this->_connection, $search_dn, $password))
		{
			$this->_log_message('debug', 'Authenticated username and password.');
			return TRUE;
		}
		else
		{
			$this->_log_message('error', ldap_errno($this->_connection) .' '. ldap_error($this->_connection));
			return FALSE;
		}
	}
	
	/**
	 * Get Profile
	 * 
	 * Returns all the available information on the LDAP server
	 * for the given username.
	 * 
	 * @param string $username
	 */
	public function get_profile($username = NULL)
	{	
		if(empty($username))
		{
			$this->_log_message('error', 'Invalid username.');
			return FALSE;
		}
		
		if($search = $this->_search($username))
		{
			if($entry = ldap_get_entries($this->_connection, $search))
			{
				return $entry[0];
			}
			else 
			{
				$this->_log_message('error', 'Could not retrieve ldap entry for user: '. $username);
				return FALSE;	
			}
		}
		else 
		{
			return FALSE;
		} 
	}
}