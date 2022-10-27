<?php defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('rss_to_post_settings');

if ($timestamp = wp_next_scheduled('rss_to_post_check_feeds'))
{
	wp_unschedule_event($timestamp, 'rss_to_post_check_feeds');
}

global $wpdb;
$wpdb->delete("{$wpdb->prefix}postmeta", array('meta_key' => 'rss_to_post_redirect'));