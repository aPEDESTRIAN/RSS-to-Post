<?php defined('ABSPATH') || exit;

class RSS_to_Post
{
	// Defines the core functionality of the plugin.
	public function __construct()
	{
		// Register admin hooks?
		if (is_admin())
		{
			add_action('admin_menu',	[$this, 'add_options_page']);
			add_action('admin_init',	[$this, 'options_page_init']);
		}

		// Theme hooks
		add_action('template_redirect',			[$this, 'rss_post_redirect']);
		add_filter('post_link',					[$this, 'rss_post_link'], 10, 3);
		add_action('rss_to_post_check_feeds',	[$this, 'rss_to_post_check_feeds_callback']);

		// Shortcodes
		add_shortcode('rss_post_origin_link', [$this, 'rss_post_origin_link']);
	}

	#region ====================[ Theme Hooks ]====================

	// Redirects to the origin url of the post that was added via RSS to Post
	public function rss_post_redirect()
	{
		if (is_single() && metadata_exists('post', get_the_ID(), 'rss_to_post_redirect'))
		{
			wp_redirect(get_post_meta(get_the_ID(), 'rss_to_post_redirect', true), 301);
			exit;
		}
	}

	// Overrides the hyperlink of posts that were added via RSS to Post to the origin of the post
	public function rss_post_link($post_link, $post, $leavename)
	{
		if (metadata_exists('post', $post->ID, 'rss_to_post_redirect'))
		{
			$post_link = get_post_meta($post->ID, 'rss_to_post_redirect', true);
		}
		return $post_link;
	}

	// Checks all the feeds added in settings and adds any new posts that satisfy the black/white list
	public function rss_to_post_check_feeds_callback()
	{
		require_once plugin_dir_path(dirname(__FILE__ )) .'includes/class-wp-download-remote-image.php';

		global $wpdb;
		$feeds = json_decode(get_option('rss_to_post_settings', '[]'));
	
		foreach ($feeds as $feed)
		{
			if ($feed->url == '') {
				continue;
			}
			
			$rss = simplexml_load_string(file_get_contents($feed->url));
			$mode = $feed->mode;
			
			foreach($rss->channel->item as $item)
			{
				$title = (string) $item->title;
				$slug = sanitize_title_with_dashes($title, '', 'save');
				$guid = (string) $item->guid;
	
				$post_exists = (bool) $wpdb->get_row("SELECT post_name FROM wp_posts WHERE post_name = '{$slug}' OR post_name = '{$slug}__trashed' OR guid = '{$guid}'", 'ARRAY_A');
	
				if (!$post_exists && $slug != '')
				{
					$description = ((string) $item->description);
	
					foreach (explode(' ', $feed->word_list) as $word)
					{
						$needle = strtolower($word);
						$haystack = strtolower("{$title} {$description}");
						$word_found = (strpos($haystack, $needle) !== false);
						$save_post = ($word_found && $mode == 1) || (!$word_found && $mode == 0);
	
						if ($save_post)
						{
							if ($post_id = wp_insert_post([
								'post_date_gmt' => date("Y-m-d H:i:s", strtotime((string) $item->pubDate)),
								'post_title' => $title,
								'post_content' => "<!-- wp:paragraph --><p>{$description}</p><!-- /wp:paragraph -->",
								'post_status' => 'publish',
								'post_name' => $slug,
								'guid' => $guid,
							]))
							{
								foreach ($item->children('media', true) as $key => $value)
								{
									$attributes = $value->attributes();
							
									if ($key == 'content' && property_exists($attributes, 'url'))
									{
										$url = $attributes->url;
										
										// Do we already have this image? If so, just reuse it
										if ($attachment_id = $this->attachment_exists($url))
										{
											set_post_thumbnail($post_id, $attachment_id);
										}
										else
										{
											$download_remote_image = new WP_DownloadRemoteImage((string) $url);
	
											if ($attachment_id = $download_remote_image->download())
											{
												set_post_thumbnail($post_id, $attachment_id);
											}
										}
									}
								}
								
								add_post_meta($post_id, 'rss_to_post_redirect', ((string) $item->link));
							}
							break;
						}
					}
				}
			}
		}
	}

	public function rss_post_origin_link()
	{
		$origin_url = parse_url(get_the_permalink(), PHP_URL_HOST);
		return '<a target="_blank" href="https://'.$origin_url.'">'.$origin_url.'</a>';
	}

	private function attachment_exists($url)
	{
		// Remove GET params if we have any
		if (strpos($url, '?') !== false)
		{
			$t = explode('?',$url);
			$url = $t[0];            
		}

		$pathinfo = pathinfo($url);
		$post_title = trim($pathinfo['filename']);

		$get_attachment = new WP_Query(array(
			'posts_per_page' => 1,
			'post_type' => 'attachment',
			'post_title' => $post_title,
		));

		if (!$get_attachment || !isset($get_attachment->posts, $get_attachment->posts[0]))
		{
			return false;
		}

		return $get_attachment->posts[0]->ID;
	}

	#endregion =================[ Theme Hooks ]====================
	
	#region ====================[ Admin Hooks ]====================

	public function add_options_page()
	{
		add_options_page(
			'RSS to Post Settings', // string $page_title
			'RSS to Post', // string $menu_title
			'manage_options', // string $capability
			'rss-to-post-options-page', // string $menu_slug
			array($this, 'display_options_page') // callable $callback
		);
	}

	// Displays the admin page we added in add_options_page
	public function display_options_page()
	{
		?>
		<div class="wrap">
			<h1>RSS to Post Settings</h1>
			<form method="post" action="options.php">
				<?php
				do_settings_sections('rss-to-post-options-page'); // $menu_slug from add_options_page() call
				settings_fields('rss_to_post_settings_option_group'); // $option_group from register_setting calls
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function options_page_init()
	{
		// Add sections to form
		// add_settings_section(string $id, 		string $title, 			callable $callback (section description),	string $page ($menu_slug from add_options_page))
		add_settings_section('rss_to_post_general',	'General Settings',		null,										'rss-to-post-options-page');

		// Register settings
		// register_setting(string $option_group,				string $option_name, 		array $args)
		register_setting('rss_to_post_settings_option_group',	'rss_to_post_settings',		array('type' => 'array', 'sanitize_callback' => [$this, 'sanitize_array']));
		

		// Add the registered settings fields to sections
		// add_settings_field(string $option_name,			string $title,				callable $callback (field input),		string $page,					string $section)
		add_settings_field('rss_to_post_settings',			'Settings',					array($this, 'settings_input'),			'rss-to-post-options-page',		'rss_to_post_general');
	}

	public function settings_input()
	{
		?>
		<textarea name="<?php echo esc_attr('rss_to_post_settings'); ?>" id="<?php echo esc_attr('rss_to_post_settings'); ?>" class="large-text code" rows="4"><?php echo esc_textarea(get_option('rss_to_post_settings', '')); ?></textarea>
		<?php
	}

	public function sanitize_array($text)
	{
		return $text;
	}

	#endregion =================[ Admin Hooks ]====================
}