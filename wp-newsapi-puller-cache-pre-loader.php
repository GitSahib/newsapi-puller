<?php
namespace WpNewsApiPuller\Cache;
// Load WordPress.
require_once( ABSPATH . 'wp-load.php' );
if (!defined('WP_USE_THEMES'))
{
	define( 'WP_USE_THEMES', false );
}
class WpRocketCachePreloader {
	
	var $urls_to_preload = array();
	
	public function __construct($post_parama_links = [])
	{
		$this->urls_to_preload = $post_parama_links;
	}

	public function purge_home_page()
	{ 
		if(function_exists("rocket_clean_home"))
		{
			rocket_clean_home('');
			$this->rocket_preload_page([site_url("/")], array());
		}		
	}

	public function purge_categories($limit = 10, $offset = 0)
	{ 
		if(!function_exists("rocket_clean_files"))
		{
			return;
		}		
		global $wpdb;
		$query = "SELECT url FROM {$wpdb->prefix}wpr_rocket_cache WHERE url like '".site_url("category/")."%' limit $limit offset $offset";
		$urls = $wpdb->get_col($query);
		$this->purge_urls($urls);
		$this->pre_load_urls($urls);
		return $urls;
	}

	public function categories_count()
	{ 
		if(!function_exists("rocket_clean_files"))
		{
			return;
		}		
		global $wpdb;
		$query = "SELECT count(url) FROM {$wpdb->prefix}wpr_rocket_cache WHERE url like '".site_url("category/")."%'";
		$urls_count = $wpdb->get_var($query);
		return $urls_count;
	}

	public function purge_urls($urls)
	{
		if(function_exists('rocket_clean_files'))
		{
			rocket_clean_files($urls);
		}
	}

	public function pre_load_posts()
	{
		$this->pre_load_urls($this->urls_to_preload);
	}


	public function reload_top($limit)
	{
		global $wpdb;
		$query = "SELECT guid FROM {$wpdb->prefix}posts ORDER BY post_date DESC LIMIT $limit";
		$urls = $wpdb->get_col($query);
		$this->purge_urls($urls);
		$this->pre_load_urls($urls);
		return $urls;
	}

	public function reload_page($page)
	{
		$this->purge_urls($page);
		$this->pre_load_urls([$page]);
	}

	private function pre_load_urls($pages_to_clean_preload = [])
	{
		if(!function_exists('get_rocket_option'))
		{
			return;
		}

		$args = array();

		if( 1 == get_rocket_option( 'cache_webp' ) ) {
			$args[ 'headers' ][ 'Accept' ]      	= 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8';
			$args[ 'headers' ][ 'HTTP_ACCEPT' ] 	= 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8';
		}
	
		// Preload desktop pages/posts.
		$this->rocket_preload_page( $pages_to_clean_preload, $args );
		
		if( 1 == get_rocket_option( 'do_caching_mobile_files' ) ) {
			$args[ 'headers' ][ 'user-agent' ] 	= 'Mozilla/5.0 (Linux; Android 8.0.0;) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Mobile Safari/537.36';
		
			// Preload mobile pages/posts.
			$this->rocket_preload_page(  $pages_to_clean_preload, $args );
		}
	}

	private function rocket_preload_page ( $pages_to_preload, $args ){
	
		foreach( $pages_to_preload as $page_to_preload ) {
			wp_remote_get( esc_url_raw ( $page_to_preload ), $args );
		}
	}
}
