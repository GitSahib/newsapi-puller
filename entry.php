<?php
/**
 * @package NewsApi Puller
 * @version 1.0.0
 */
/*
Plugin Name: NewsApi Puller
Plugin URI: https://www.paktweet.com/
Description: Pull news from newsapi
Version: 1.0.0
Author URI: https://www.paktweet.com/
*/
namespace WpNewsApiPuller;
include_once("wp-newsapi-puller-settings.php"); 
include_once("wp-newsapi-puller-rest-settings.php");
class WpNewsApiPullerEntry
{
	public function __construct()
	{
		$this->define_constants();
		$this->init_plugin();
		$this->init_settings();
	}
	public function init_plugin()
	{
		/*hooks*/
		register_activation_hook( __FILE__, array($this, 'news_api_puller_admin_notice_activation_hook' ));
		register_deactivation_hook( __FILE__, array($this, 'news_api_puller_admin_notice_deactivation_hook' ));		
	}
	/**
	 * Runs only when the plugin is activated.
	 * @since 0.1.0
	 */
	function news_api_puller_admin_notice_activation_hook() {
	    /* Create transient data */
	    $sources = file_get_contents(WP_NEWSAPI_PULLER_PLUGIN_DIR."sources.json");
	    $sources = json_decode($sources);
	    if(!$sources || $sources->status !== "ok")
	    {
	    	return;
	    }
	   
	    foreach($sources->sources as $s)
	    {
	    	$parent_args = [ 
				'description' => $s->description,
				'parent'      => 0,
				str_replace(" ", "-", strtolower($s->category))
			]; 
			wp_insert_term( 
				ucfirst($s->category),  
				'category', 
				$parent_args
			);

			$parent = term_exists( $s->category, 'category' )['term_id'];
			$child_args = [	
				'slug' => str_replace(" ", "-", strtolower($s->name)), 
				'parent'=> $parent,
			];
			wp_insert_term(
				$s->name,  
				'category', 
				$child_args
			);
	    }
	}
	/**
	 * Runs only when the plugin is deactivated.
	 * @since 0.1.0
	 */
	function news_api_puller_admin_notice_deactivation_hook(){
		$timestamp = wp_next_scheduled( '_newsapi_puller_pull_news_hook' );
		$timestamp_ai = wp_next_scheduled( '_newsapi_puller_pull_news_ai_hook' );
		wp_unschedule_event( $timestamp, '_newsapi_puller_pull_news_hook' );
    	wp_unschedule_event( $timestamp_ai, '_newsapi_puller_pull_news_ai_hook' );
	}
	public function define_constants()
	{
		DEFINE('WP_NEWSAPI_PULLER_PLUGIN_DIR', plugin_dir_url(__FILE__));
		DEFINE('WP_NEWSAPI_PULLER_PLUGIN_ASSETS', WP_NEWSAPI_PULLER_PLUGIN_DIR."assets/");		
		DEFINE('WP_NEWSAPI_PULLER_SETTINGS_GROUP', 'news-api-puller-settings-group');
		DEFINE('WP_NEWSAPI_PULLER_SETTINGS_MAIN_PAGE', 'news-api-puller-settings-main-admin');
		DEFINE('WP_NEWSAPI_PULLER_REST', 'news-api-puller-rest/v1/');
	}
	public function init_settings()
	{		
		new Settings\Settings();
		new Settings\RestSettings();
	}
}
new WpNewsApiPullerEntry();