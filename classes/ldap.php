<?php

class LDAP
{
	/**
	 * LDAP Plugin Version Number
	 *
	 * @var string
	 */
	public $version;

	/**
	 * LDAP Plugin Table Name
	 *
	 * @var string
	 */
	public $table_name;

	/**
	 * LDAP Plugin Table Name
	 *
	 * @var object
	 */
	protected static $instance;

	/**
	 * Constructor
	 * 
	 * Defines the class properties and class the add_actions method.
	 */
	public function __construct()
	{
		global $wpdb;
		
		$this->version = '1.1.0';
 
 		if (function_exists('is_multisite') && is_multisite()) 
 		{
			$this->table_name = $wpdb->get_blog_prefix(BLOG_ID_CURRENT_SITE) . 'wordpress_ldap';
		} 
		else
		{
			$this->table_name = $wpdb->prefix . 'wordpress_ldap_meta';
		}

		$this->add_actions();
	}

    /**
     * Instance
     */
    public static function instance()
    {
        if (!isset(self::$instance)) 
        {
            $className = __CLASS__;
            self::$instance = new $className;
        }
        
        return self::$instance;
    }

	/**
	 * Add WordPress Actions
	 * 
	 * Gathers all "add_action" calls into one method
	 */
	public function add_actions()
	{
		// Create plugin table when activated
		register_activation_hook(WP_PLUGIN_DIR .'/wordpress-ldap-plugin/wordpress-ldap-plugin.php', array($this, 'activation_hook'));

		// Remove plugin options from wordpress when deleted
		register_deactivation_hook(WP_PLUGIN_DIR .'/wordpress-ldap-plugin/wordpress-ldap-plugin.php', array($this, 'deactivation_hook'));
		
		// Add the necessary css and js files to the WordPress Admin
		add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));

		// Add the WordPress LDAP Plugin to admin menu
		add_action('admin_menu', array($this, 'add_menu_page'));
		
		// Add the WordPress LDAP Plugin options to the options page
		add_action('admin_init', array($this, 'add_plugin_options'));

		// Check for updates to plugin
		add_action('plugins_loaded', array($this, 'update_hook'));

		// Add WordPress LDAP authentication to WP 'authenticate' hook.
		add_filter('authenticate', array($this, 'authenticate'), 1, 3);
	}

	/**
	 * Load Admin Scripts
	 * 
	 * Loads the required admin styles and scripts for this plugin.
	 */
	public function add_admin_scripts()
	{
		wp_enqueue_style('wordpressldap-styles', WP_PLUGIN_URL .'/wordpressldap-plugin/css/admin.css');

		wp_enqueue_script('jquery-ui-datepicker');
	}

	/**
	 * WordPress LDAP Admin Actions
	 * 
	 * WP hook that adds the configuration screen to WP Admin Menu.
	 */
	public function add_menu_page()
	{
		add_menu_page(
			'WordPress LDAP Configuration Settings',
			'WordPress LDAP',
			'activate_plugins',
			'wordpress_ldap',
			array($this, 'display_options_page')
		);
	}

	/**
	 * Add Plugin Options
	 * 
	 * Defines and registers the LDAP Plugin WordPress options.
	 */	
	public function add_plugin_options()
	{
		add_settings_section( 
	        'ldap_plugin_options',                  // ID used to identify this section and with which to register options 
	        'LDAP Settings',                        // Title to be displayed on the administration page 
	        array($this, 'display_plugin_desc'),    // Callback used to render the description of the section 
	        'ldap_plugin_options'                   // Page on which to add this section of options 
	    );

		add_settings_field(
			'privileged_dn',                        // ID used to identify the field throughout the theme
			'Privileged DN',                        // The label to the left of the option interface element
			array($this, 'display_option_field'),   // The name of the function responsible for rendering the option interface 
			'ldap_plugin_options',                  // The page on which this option will be displayed  
	        'ldap_plugin_options',                  // The name of the section to which this field belongs
	        array('field_name' => 'privileged_dn')  // Additional arguments that are passed to the callback function
		);  
	    
	    add_settings_field(  
	        'privileged_password',                       // ID used to identify the field throughout the theme  
	        'Password',                                  // The label to the left of the option interface element  
	        array($this, 'display_option_field'),        // The name of the function responsible for rendering the option interface
	        'ldap_plugin_options',                       // The page on which this option will be displayed  
	        'ldap_plugin_options',                       // The name of the section to which this field belongs  
	    	array('field_name' => 'privileged_password') // Additional arguments that are passed to the callback function
		);

	    add_settings_field(  
	        'ldap_hosts',                           // ID used to identify the field throughout the theme  
	        'LDAP Hosts',                           // The label to the left of the option interface element  
	        array($this, 'display_option_field'),   // The name of the function responsible for rendering the option interface
	        'ldap_plugin_options',                  // The page on which this option will be displayed  
	        'ldap_plugin_options',                  // The name of the section to which this field belongs  
	 		array('field_name' => 'ldap_hosts')     // Additional arguments that are passed to the callback function
	    );  
	    
	    add_settings_field(  
	        'ldap_port',                            // ID used to identify the field throughout the theme  
	        'Password',                             // The label to the left of the option interface element  
	        array($this, 'display_option_field'),   // The name of the function responsible for rendering the option interface
	        'ldap_plugin_options',                  // The page on which this option will be displayed  
	        'ldap_plugin_options',                  // The name of the section to which this field belongs
	 		array('field_name' => 'ldap_port')      // Additional arguments that are passed to the callback function
	    );

	    add_settings_field(  
	        'base_dn',                              // ID used to identify the field throughout the theme  
	        'Base DN',                              // The label to the left of the option interface element  
	        array($this, 'display_option_field'),   // The name of the function responsible for rendering the option interface
	        'ldap_plugin_options',                  // The page on which this option will be displayed  
	        'ldap_plugin_options',                  // The name of the section to which this field belongs
	 		array('field_name' => 'base_dn')        // Additional arguments that are passed to the callback function
	    );    
	    
		register_setting(  
	        'ldap_plugin_options',                  // The name of the settings group  
	        'ldap_plugin_options',                  // The name of an option to sanitize and save
	        array($this, 'save_ldap_options')       // The name of the function that handles the sanitization and saving
		);
	}

	/**
	 * Display Plugin Description
	 * 
	 * Displays the description for the defined options section.
	 */
	public function display_plugin_desc()
	{
	}

	/**
	 * Display Option Field
	 * 
	 * Display the specified plugin option field.
	 */	
	public function display_option_field($args)
	{
		if($args['field_name'] !== 'privileged_password')
			$type = 'text';
		else
			$type = 'password';

		echo sprintf(
				'<input type="%s" name="ldap_plugin_options[%s]" id="%s" value="%s">', 
				$type,
				$args['field_name'], 
				$args['field_name'], 
				$this->get_option($args['field_name']) 
			);
	}

	/**
	 * WordPress LDAP Menu
	 * 
	 * Displays the LDAP configuration screen in WP Admin.
	 */
	public function display_options_page()
	{
		require( WP_PLUGIN_DIR .'/wordpressldap-plugin/views/plugin-options.php' );
	}

	/**
	 * Save LDAP Options
	 * 
	 * Sanitized and saves the provided LDAP options.
	 * 
	 * @param array $input
	 * 
	 * @return array $input Retuns the options array provided by WordPress.
	 */
	public function save_ldap_options($input)
	{
		global $wpdb;
		
		foreach($input as $field => $value)
		{
			if(FALSE === $wpdb->query("UPDATE {$this->table_name} SET meta_value = '{$value}' WHERE meta_key = '{$field}'"))
				error_log(__FUNCTION__ .': could not save value: {$value} for option: {$field}');
		}
	}

	/**
	 * Get Setting
	 * 
	 * Returns the appropriate value for the given input field.
	 * 
	 * @param string $key
	 */
	public function get_option($key)
	{
		global $wpdb;

		if(isset($_POST[$key]))
		{
			 $value = $_POST[$key];
		}
		else
		{
			$value = $wpdb->get_var("SELECT meta_value FROM {$this->table_name} WHERE meta_key = '{$key}'");
		}		

		return $value;
	}

	/**
	 * WordPress LDAP Authenticate
	 * 
	 * Wrapper function for WordPress LDAP Authentication class.
	 * 
	 * @param WP_User $user
	 * @param string $username
	 * @param string $password
	 */
	public function authenticate($user, $username, $password)
	{
		// check if user is already logged in.
		if (is_a($user, 'WP_User')) 
			return $user;
		
		// disallow wordpress authentication -- go straight to LDAP
		remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
		
		$ldap = new LDAP_Authentication();
		
		if($ldap->check_credentials($username, $password))
		{
			// attempt to retrieve wordpress user profile
			if($wp_profile = get_user_by('login', $username))
			{
				// found user, return user id
				return new WP_User($wp_profile->ID);
			}
			else
			{
				// could not find user, return login error
				do_action( 'wp_login_failed', $username );				
				return new WP_Error('invalid_username', __('<strong>WordPres LDAP Plugin Error</strong>: You do not have permission to access this website.'));
			}
		}
		else
		{
			// could not find user in LDAP, attempt wordpress login
			add_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
		}
	}

	/**
	 * WordPres LDAP Activation Hook
	 * 
	 * WP hook that adds plugin configuration to Wordpress.
	 */
	public function activation_hook()
	{
		global $wpdb;
		
		if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $this->table_name)
		{
			$this->initialize_table();
		}
	}

	/**
	 * WordPres LDAP Deletion Hook
	 * 
	 * WP hook that removes plugin configuration from WP options.
	 */
	public function deactivation_hook()
	{
		$this->delete_table();
		
		delete_option("wordpress_ldap_version");
	}

	/**
	 * WordPres LDAP Update Hook
	 * 
	 * WP hook that checks for any updates to the plugin.
	 */
	public function update_hook()
	{
	    if (get_site_option('wordpress_ldap_version') != $this->version) 
	    {
	        $this->update_table();
	    }
	}

	/**
	 * Initialize Table
	 * 
	 * Creates and populates the LDAP plugin table and sets the plugin version.
	 */
	public function initialize_table()
	{
		global $wpdb;
		
		if($this->create_table())
		{
			$ldap_values = 
				array(
					'privileged_dn' => '',
					'privileged_password' => '',
					'ldap_hosts' => '',
					'ldap_port' => '',
					'base_dn' => ''
					
				);
				
			foreach($ldap_values as $key => $value)
			{
				$wpdb->insert($this->table_name, array('meta_key' => $key, 'meta_value' => $value));
			}

			add_option("wordpress_ldap_version", $this->version);
		}
	}

	/**
	 * Create Table
	 * 
	 * Creates the LDAP plugin table which holds the configuration settings.
	 */
	public function create_table()
	{
		global $wpdb;
				
		if (!empty ($wpdb->charset))
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		
		if (!empty ($wpdb->collate))
			$charset_collate .= " COLLATE {$wpdb->collate}";
	 
		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
		  		meta_id int(11) NOT NULL AUTO_INCREMENT,
				meta_key varchar(255) DEFAULT NULL,
				meta_value longtext DEFAULT NULL,
 
		  		UNIQUE KEY meta_id (meta_id)
		) {$charset_collate};";
	 
		require_once( ABSPATH .'wp-admin/includes/upgrade.php' );
		dbDelta($sql);
		
		if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") == $this->table_name)
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Update Table
	 * 
	 * Updates the LDAP plugin table structure after a schema change.
	 */
	public function update_table()
	{
		global $wpdb;
		
		$installed_ver = get_option("wordpress_ldap_version");
		
	   	if ($installed_ver !== $this->version) 
	   	{
			$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
		  		meta_id int() NOT NULL AUTO_INCREMENT,
				meta_key varchar(255) DEFAULT NULL,
				meta_value longtext DEFAULT NULL,
 
		  		UNIQUE KEY meta_id (meta_id)
			) {$charset_collate};";
	
	      	require_once( ABSPATH .'wp-admin/includes/upgrade.php' );
	      	dbDelta($sql);
	
	    	update_option("wordpress_ldap_version", $this->version);
	  	}
	}
	
	/**
	 * Delete Table
	 * 
	 * Deletes the LDAP plugin table from the database.
	 */
	public function delete_table()
	{
		global $wpdb;
		
		$sql = "DROP TABLE IF EXISTS {$this->table_name};";
      	$wpdb->query($sql);
	}
}