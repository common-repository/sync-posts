<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add a menu item for the plugin
add_action('admin_menu', 'scwp_sync_posts_menu');

// Enqueue the CSS file
function scwp_enqueue_styles() {
    wp_enqueue_style( 'sync-posts-style', plugins_url( '../css/style.css', __FILE__ ) );
}
add_action( 'admin_enqueue_scripts', 'scwp_enqueue_styles' );

// Admin menu functions
function scwp_sync_posts_menu() {
    add_menu_page(
        'Sync Posts',
        'Sync Posts',
        'manage_options',
        'sync-posts',
        'scwp_sync_posts_options',
        'dashicons-database-import',
        99
    );
}
?>