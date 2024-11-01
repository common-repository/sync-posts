<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Handle the AJAX request to sync posts
add_action('wp_ajax_sync_posts', 'scwp_sync_posts_func');
?>