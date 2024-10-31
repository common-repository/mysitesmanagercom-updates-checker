<?php
/*
  Plugin Name: MySitesManager Updates Checker
  Plugin URI: http://www.mysitesmanager.com
  Description: Creates an XML file that lists updates for your site. Only for use with www.mysitesmanager.com - a webapp that gives you an easy way to monitor your sites for updates. 
  Author: MySitesManager.com
  Version: 1.0.1
  Author URI: http://www.mysitesmanager.com
  License: GPLv2
  Text Domain: mysitesmanager-updates-checker
  Domain Path: /languages
 */

/*  Copyright 2013  MySitesManager.com (email: info@MySitesManager.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if (!class_exists('freshideas_WPUpdatesInfo'))
{

    class freshideas_WPUpdatesInfo
    {

	protected static $options_field = "freshideas_wpui_settings";
	protected static $options_field_ver = "freshideas_wpui_settings_ver";
	protected static $options_field_current_ver = "1.0";
	protected static $cron_name = "freshideas_wpui_update_check";

	function __construct()
	{
	    // Check settings are up to date
	    self::settings_check();
	    // Create Activation and Deactivation Hooks
	    register_activation_hook(__FILE__, array(__CLASS__, 'activate_plugin'));
	    register_deactivation_hook(__FILE__, array(__CLASS__, 'deactivate_plugin'));
	    // Internationalization
	    load_plugin_textdomain('mysitesmanager-updates-checker', false, dirname(plugin_basename(__FILE__)) . '/languages');
	    // Add Filters
	    add_filter('plugin_action_links', array(__CLASS__, 'plugin_action_links'), 10, 2); // Add settings link to plugin in plugin list
	    // Add Actions
	    add_action('admin_menu', array(__CLASS__, 'admin_settings_menu')); // Add menu to options
	    add_action('admin_init', array(__CLASS__, 'admin_settings_init')); // Add admin init functions
	    add_action('freshideas_wpui_enable_cron', array(__CLASS__, 'enable_cron')); // action to enable cron
	    add_action('freshideas_wpui_disable_cron', array(__CLASS__, 'disable_cron')); // action to disable cron
	    add_action(self::$cron_name, array(__CLASS__, 'do_update_check')); // action to link cron task to actual task
	    add_action('admin_init', array(__CLASS__, 'check_xml_infofile_for_wp_updates'));
	}
	
	public static function check_xml_infofile_for_wp_updates()
	{
	    $options = get_option(self::$options_field); // get settings  
	    if ( strlen($options['xml_filename']) == 0 ) return false;
            $xmlFullFileName = ABSPATH . $options['xml_filename'];
	    if ( !file_exists($xmlFullFileName))
	    {
                if ( !self::generate_wp_xml_update_info() )
                {
                    self::showMessage("Invalid WP updates xml info file name: " . $options['xml_filename'] . ", please fix file name. ", true);
                    return false;
                }
                else
                {
                    self::xml_file_success();
                    return true;
                }
	    }
	    
	    $xmlDoc = simplexml_load_file($xmlFullFileName);
            if ( isset($xmlDoc) && isset($xmlDoc->wp) && isset($xmlDoc->wp['installed_version']) && isset($xmlDoc->plugins) ) 
                return true;
            else
            {
                if ( !self::generate_wp_xml_update_info() )
                {
                    self::showMessage("Invalid WP updates xml info file name: " . $options['xml_filename'] . ", please fix file name. ", true);
                    return false;
                }  
                else
                {
                    self::xml_file_success();
                    return true;
                }                
            }
            
	}       
	
        /**
         * Generates XML file with WP update info
         * @return boolean true if file was generated OK
         */
	public static function generate_wp_xml_update_info()
	{  
	    $options = get_option(self::$options_field); // get settings  
	    if ( empty($options['xml_filename']) ) return false;
            
            $xmlFullFileName = ABSPATH . $options['xml_filename'];
                 
	    $xmlDoc = new SimpleXMLElement('<?xml version="1.0" standalone="yes"?><wpupdateinfo><wp>Blog name</wp><plugins></plugins><themes></themes></wpupdateinfo>');	   	    

            $new_wp_ver = '';
	    self::core_update_check($new_wp_ver); // check the WP core for updates

	    $xmlDoc->wp = get_bloginfo('name');
	    $xmlDoc->wp['installed_version'] = get_bloginfo('version');
	    $xmlDoc->wp['latest_version'] = $new_wp_ver;
	    $xmlDoc->wp['url'] = get_bloginfo('url');
	            	   
            $plugsToUpdate = self::get_plugins_to_update(); // check for plugin updates
            foreach($plugsToUpdate as $key => $val )
            {
                $plugNode = $xmlDoc->plugins->addChild('plugin', $val['name']);
                $plugNode['slug'] = $key;
                $plugNode['installed_version'] = $val['installed_version'];
                $plugNode['latest_version'] = $val['latest_version'];		    
                $plugNode['url'] = $val['url'];
                $plugNode['active'] = $val['active'];
            }
	    
            $themesToUpdate = self::get_themes_to_update(); // check for theme updates
            foreach($themesToUpdate as $key => $val )
            {
                $themeNode = $xmlDoc->themes->addChild('theme', $val['name']);
                $themeNode['slug'] = $key;
                $themeNode['installed_version'] = $val['installed_version'];
                $themeNode['latest_version'] = $val['latest_version'];
                $themeNode['active'] = $val['active'];
            }		
	    
	    if ( $xmlDoc->saveXML($xmlFullFileName) !== false )
                return true;
            else
                return false;                
	}
	
	protected function xml_file_success()
	{
	    self::showMessage(__("XML update file was successfully recreated.", "mysitesmanager-updates-checker"), false);
	}
        
	protected function xml_file_error()
	{
	    self::showMessage(__("XML update file was not generated due to an error.", "mysitesmanager-updates-checker"), true);
	}        

	protected function showMessage($message, $errormsg = false)
	{
	    if ($errormsg)
		echo '<div class="error">';
	    else
		echo '<div class="updated fade">';
	    echo "<p>$message</p></div>";
	}

	/**
	 * Check if this plugin settings are up to date. Firstly check the version in
	 * the DB. If they don't match then load in defaults but don't override values
	 * already set. Also this will remove obsolete settings that are not needed.
	 *
	 * @return void
	 */
	protected function settings_check()
	{
	    $current_ver = get_option(self::$options_field_ver); // Get current plugin version
	    if (self::$options_field_current_ver != $current_ver)
	    { // is the version the same as this plugin?
		$options = (array) get_option(self::$options_field); // get current settings from DB
		$defaults = array(// Here are our default values for this plugin
		    'xml_filename' => self::get_secure_xml_filename(),
		);
		// Intersect current options with defaults. Basically removing settings that are obsolete
		$options = array_intersect_key($options, $defaults);
		// Merge current settings with defaults. Basically adding any new settings with defaults that we dont have.
		$options = array_merge($defaults, $options);
		update_option(self::$options_field, $options); // update settings
		update_option(self::$options_field_ver, self::$options_field_current_ver); // update settings version
	    }
	}

	public function activate_plugin()
	{
	    do_action("freshideas_wpui_enable_cron"); // Enable cron
	}

	public function deactivate_plugin()
	{
	    do_action("freshideas_wpui_disable_cron"); // Disable cron
	}

	/**
	 * Enable cron for this plugin. Check if a cron should be scheduled.
	 *
	 * @param bool|string $manual_interval For setting a manual cron interval.
	 * @return void
	 */
	public function enable_cron()
	{
            do_action("freshideas_wpui_disable_cron"); // remove any crons for this plugin first so we don't end up with multiple crons doing the same thing.
            wp_schedule_event((time() + 3600), 'daily', self::$cron_name); // schedule cron for this plugin.
	    
	}

	public function disable_cron()
	{
	    wp_clear_scheduled_hook(self::$cron_name); // clear cron
	}

	/**
	 * Adds the settings link under the plugin on the plugin screen.
	 *
	 * @param array $links
	 * @param string $file
	 * @return array $links
	 */
	public function plugin_action_links($links, $file)
	{
	    static $this_plugin;
	    if (!$this_plugin)
	    {
		$this_plugin = plugin_basename(__FILE__);
	    }
	    if ($file == $this_plugin)
	    {
		$settings_link = '<a href="' . site_url() . '/wp-admin/options-general.php?page=mysitesmanager-updates-checker">' . __("Settings", "mysitesmanager-updates-checker") . '</a>';
		array_unshift($links, $settings_link);
	    }
	    return $links;
	}

	/**
	 * This is run by the cron. The update check checks the core, the
	 * plugins and themes and generates XML file
	 *
	 * @return void
	 */
	public static function do_update_check()
	{
	    self::generate_wp_xml_update_info();
	}
	
	/**
	 * Checks to see if any WP core updates
	 *
	 * @param string $message holds message to be sent via notification
	 * @return bool
	 */
	protected function core_update_check(&$new_wp_ver)
	{
	    do_action("wp_version_check"); // force WP to check its core for updates
	    $update_core = get_site_transient("update_core"); // get information of updates
	    
	    require_once( ABSPATH . WPINC . '/version.php' ); // Including this because some plugins can mess with the real version stored in the DB.
	    $new_wp_ver = $update_core->updates[0]->current; // The new WP core version
	    
	    if ('upgrade' == $update_core->updates[0]->response) return true; // we have updates so return true
	    return false; // no updates return false
	}
	
	/**
         * Returns array of plugins to update
         * @return array of plugins to update
         */
	protected function get_plugins_to_update()
	{
	    $plugsToUpdate = array();
	    do_action("wp_update_plugins"); // force WP to check plugins for updates
	    $update_plugins = get_site_transient('update_plugins'); // get information of updates
	    
	    if (!empty($update_plugins->response))
	    { 
		$plugins_need_update = $update_plugins->response;
		// get active plugins
                $active_plugins = array_flip(get_option('active_plugins')); 
		if (count($plugins_need_update) >= 1)
		{ 
		    require_once(ABSPATH . 'wp-admin/includes/plugin-install.php'); // Required for plugin API
		    require_once(ABSPATH . WPINC . '/version.php' ); // Required for WP core version
		    foreach ($plugins_need_update as $key => $data)
		    { // loop through the plugins that need updating
			$plugin_info = get_plugin_data(WP_PLUGIN_DIR . "/" . $key); // get local plugin info
			$info = plugins_api('plugin_information', array('slug' => $data->slug)); // get repository plugin info
			$message = "\n" . sprintf(__("Plugin: %s is out of date. Please update from version %s to %s", "mysitesmanager-updates-checker"), $plugin_info['Name'], $plugin_info['Version'], $data->new_version) . "\n";
			$message .= "\t" . sprintf(__("Details: %s", "mysitesmanager-updates-checker"), $data->url) . "\n";
			$message .= "\t" . sprintf(__("Changelog: %s%s", "mysitesmanager-updates-checker"), $data->url, "changelog/") . "\n";
			
			if (isset($info->tested) && version_compare($info->tested, $wp_version, '>='))
			{
			    $compat = sprintf(__('Compatibility with WordPress %1$s: 100%% (according to its author)'), $cur_wp_version);
			}
			elseif (isset($info->compatibility[$wp_version][$data->new_version]))
			{
			    $compat = $info->compatibility[$wp_version][$data->new_version];
			    $compat = sprintf(__('Compatibility with WordPress %1$s: %2$d%% (%3$d "works" votes out of %4$d total)'), $wp_version, $compat[0], $compat[2], $compat[1]);
			}
			else
			{
			    $compat = sprintf(__('Compatibility with WordPress %1$s: Unknown'), $wp_version);
			}
			
			$message .= "\t" . sprintf(__("Compatibility: %s", "mysitesmanager-updates-checker"), $compat) . "\n";
			
			$plugsToUpdate[$data->slug] = array('name' => $plugin_info['Name'], 'message' => $message, 'compat' => $compat, 'url' => $data->url, 
                            'installed_version' => $plugin_info['Version'], 'latest_version' => $data->new_version, 'active' => (array_key_exists($key, $active_plugins) ? 'yes' : 'no'));
			
		    }
		}
	    }
	    return $plugsToUpdate;
	}	
	
	/**
	 * Get themes to update
	 *
	 * @return array of themes to update
	 */
	protected function get_themes_to_update()
	{
	    $themesToUpdate = array();	
	    do_action("wp_update_themes"); // force WP to check for theme updates
	    $update_themes = get_site_transient('update_themes'); // get information of updates
	    if (!empty($update_themes->response))
	    { // any theme updates available?
		$themes_need_update = $update_themes->response; // themes that need updating
                // get active theme
                $active_theme = get_option('template'); // find current theme that is active
		if (count($themes_need_update) >= 1)
		{ // any themes need updating after all the filtering gone on above?
		    foreach ($themes_need_update as $key => $data)
		    { // loop through the themes that need updating
			$theme_info = get_theme_data(WP_CONTENT_DIR . "/themes/" . $key . "/style.css"); // get theme info
			$message = "\n" . sprintf(__("Theme: %s is out of date. Please update from version %s to %s", "mysitesmanager-updates-checker"), $theme_info['Name'], $theme_info['Version'], $data['new_version']) . "\n";
			$themesToUpdate[$key] = array( 'name' => $theme_info['Name'], 'installed_version' => $theme_info['Version'], 'latest_version' => $data['new_version'], 
                            'message' => $message, 'active' => ( $active_theme == $theme_info['Name'] ? 'yes' : 'no' ));
		    }
		}
	    }
	    return $themesToUpdate;
	}	
	
	/*
	 * WP ADMIN SETTINGS
	 */
	public function admin_settings_menu()
	{
	    add_options_page('MySitesManager.com Updates Checker', 'MySitesManager.com Updates Checker', 'manage_options', 'mysitesmanager-updates-checker', array(__CLASS__, 'settings_page'));
	}

	public function settings_page()
	{
	    ?>
	    <div class="wrap">
	    <?php screen_icon(); ?>
	        <h2><?php _e("MySitesManager.com Updates Checker Settings", "mysitesmanager-updates-checker"); ?></h2>
	        <form action="options.php" method="post">
                    <?php
                    settings_fields("freshideas_wpui_settings");
                    do_settings_sections("mysitesmanager-updates-checker");
                    ?>
	        </form>
	    </div>
	    <?php
	}

	public function admin_settings_init()
	{
	    register_setting(self::$options_field, self::$options_field, 
                    array(__CLASS__, "freshideas_wpui_settings_validate"));
	    add_settings_section("freshideas_wpui_settings_main", __("Settings", "mysitesmanager-updates-checker"), 
                    array(__CLASS__, "freshideas_wpui_settings_main_text"), "mysitesmanager-updates-checker"); 
	    add_settings_field("freshideas_wpui_settings_main_xml_filename", __("Location of XML file", "mysitesmanager-updates-checker"), 
                    array(__CLASS__, "freshideas_wpui_settings_main_field_xml_filename"), "mysitesmanager-updates-checker", "freshideas_wpui_settings_main");
	}

	public function freshideas_wpui_settings_validate($input)
	{
	    $valid = get_option(self::$options_field);
	    $xml_filename = $input['xml_filename'];
	    if (  strlen($xml_filename) > 0 )
	    {
		if (is_file(ABSPATH . $xml_filename) )
		{
		    $valid['xml_filename'] = $xml_filename;
		}
		else
		{
		    $file = fopen(ABSPATH . $xml_filename, 'c');
		    if ( $file !== false )
		    {
			$valid['xml_filename'] = $xml_filename;
			fclose($file);
		    }
		    else
			add_settings_error("freshideas_wpui_settings_main_xml_filename", "freshideas_wpui_settings_main_xml_filename_error", 
                                __("Invalid xml file name or file can not be created, please verify file name and path and check write permissions.", "mysitesmanager-updates-checker"), "error");
		}
	    }

            if ( !self::generate_wp_xml_update_info() )
                 add_settings_error("freshideas_wpui_settings_main_xml_filename", "freshideas_wpui_settings_main_xml_filename_error", 
                            __("Invalid xml file name or file can not be created, please verify file name and path and check write permissions.", "mysitesmanager-updates-checker"), "error");
            else            
                do_action("freshideas_wpui_enable_cron");	    
	    
	    return $valid;
	}
		
	public function freshideas_wpui_settings_main_text() { }
	
	public static function get_secure_xml_filename()
	{
	    return 'wp-content/uploads/wpui_' . substr(md5((string)time() . self::generateRandomString()),0,10) . '.xml';
	}
        
        public static function generateRandomString($length = 10) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, strlen($characters) - 1)];
            }
            return $randomString;
        }
	
	public function freshideas_wpui_settings_main_field_xml_filename()
	{		
	    $options = get_option(self::$options_field);
	    ?><input id="freshideas_wpui_settings_main_xml_filename" class="regular-text" name="<?php echo self::$options_field; ?>[xml_filename]" value="<?php echo $options['xml_filename']; ?>" /> 
	    <br/>
	    <span>
		<?php 
		    _e("For security we generate a unique URL for the XML file.", "mysitesmanager-updates-checker");
                    echo '<br/>';
                    _e("If you prefer you can change the location or rename this file.", "mysitesmanager-updates-checker");
		    if (strlen($options['xml_filename']) > 0 )
		    {
                        $xmlFileUrl = get_bloginfo('wpurl') . '/' . $options['xml_filename'];
			echo '<h3>' . __("View the XML file", "mysitesmanager-updates-checker") . '</h3><a href="' . $xmlFileUrl . '" target="_blank">' . $xmlFileUrl . '</a><br/>';
                        _e("Click on the link to assure yourself that no sensitive data is stored in this file. It only lists updates.", "mysitesmanager-updates-checker");
			echo '<br/>' . __("Right click on the XML file link and copy and paste it to your dashboard at: <a href='http://www.mysitesmanager.com' target='_blank'>MySitesManager.com</a>","mysitesmanager-updates-checker");
		    }
		?>
	    </span>
            <p>
                <input class="button-primary" name="submitgenerate" type="submit" value="<?php _e("Save settings and generate XML file", "mysitesmanager-updates-checker"); ?>" />
            </p>
	    
	    <?php
	}	

   }

}

// Instance of WPUI Class
if (!isset($freshideas_wpui) && function_exists('add_action'))
{
    $freshideas_wpui = new freshideas_WPUpdatesInfo();
}
?>