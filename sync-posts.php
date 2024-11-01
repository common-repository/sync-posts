<?php
/*
	* Plugin Name: 		Sync Posts
	* Plugin URI: 		https://softclever.com/
	* Description: 		Easily copy posts from another WordPress website to your WordPress site.
	
	* Author: 			Md Maruf Adnan Sami
	* Author URI: 		https://www.mdmarufadnansami.com/
	* Version: 			1.0
	
	* Text Domain: 		sync-posts
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'inc/admin-menu.php';

// Posts sync functions //
require_once plugin_dir_path(__FILE__) . 'inc/posts-options-page.php';
require_once plugin_dir_path(__FILE__) . 'inc/posts-ajax-handler.php';
require_once plugin_dir_path(__FILE__) . 'inc/posts-sync-func.php';
?>