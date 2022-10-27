<?php defined('ABSPATH') || exit;

/**
 * The plugin bootstrap file
 *
 * Plugin Name:			RSS to Post
 * Plugin URI:			https://github.com/apedestrian/rss-to-post
 * Description:			Uses either a white or black list to automatically add posts that redirect to their origin
 * Version:				1.0.0
 * Author:				apedestrian
 * Author URI:			https://github.com/apedestrian
 * License:				GPL-2.0+
 * License URI:			http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:			rss-to-post
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