<?php defined('ABSPATH') || exit;

/*
	Plugin Name: RSS to Post
	Author: Alex
*/

function rss_to_post_activation()
{
	// Set default settings if none exist
	$option = get_option('rss_to_post_settings', false);
	if ($option === false)
	{
		update_option('rss_to_post_settings', '[{"url":"","word_list":"","mode":1}]');
	}

	// Schedule the update if it is not already scheduled
	if (!wp_next_scheduled('rss_to_post_check_feeds'))
	{
		// wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = array(), bool $wp_error = false)
		wp_schedule_event(time(), 'hourly', 'rss_to_post_check_feeds');
	}
}
register_activation_hook(__FILE__, 'rss_to_post_activation');

// Begins execution of the plugin.
require plugin_dir_path(__FILE__) . 'includes/class-rss-to-post.php';
$plugin = new RSS_to_Post();