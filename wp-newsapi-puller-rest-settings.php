<?php

namespace WpNewsApiPuller\Settings;
include_once 'wp-newsapi-puller-process-news.php';
include_once 'vendor/autoload.php';
include_once 'newsapi-client.php';
include_once 'newsdata-io-client.php';
require_once('wp-newsapi-puller-cache-pre-loader.php');
use WpNewsApiPuller\Cache\WpRocketCachePreloader;
use \GuzzleHttp\Client;
use WpNewsApiPuller\Processing;
/**
 * 
 */
use WP_REST_Server;
use WP_REST_Request;
class RestSettings
{
    private $rest = WP_NEWSAPI_PULLER_REST;
    public function __construct()
    {
        add_action( 'rest_api_init', array($this, 'api_init'));
        add_action( '_newsapi_puller_pull_news_hook', array($this, "pull_news_hook") ); 
        add_action( '_newsapi_puller_pull_news_ai_hook', array($this, "pull_news_ai_hook") ); 
        add_action( '_newsapi_puller_pull_newsdata_io_hook', array($this, "pull_newsdata_io_hook") ); 
    }
    public function api_init()
    {        
        $namespace = $this->rest;
        register_rest_route( $namespace,
            '/settings',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE, 
                    'callback' => array($this, 'get_settings'), 
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'save_settings'),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_settings'),
                ),
                array(
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'delete_settings'),
                ),
            )
        );
        register_rest_route( $namespace,
            '/news',
            array(
                array(
                    'methods' => WP_REST_Server::READABLE, 
                    'callback' => array($this, 'pull_news'), 
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'import_news'),
                )
            )
        );

        register_rest_route( $namespace,
            '/schedule',
            array(
                array(
                    'methods' => WP_REST_Server::CREATABLE, 
                    'callback' => array($this, 'schedule'), 
                ),
                array(
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'unschedule'),
                )
            )
        );

        register_rest_route( $namespace,
            '/cache',
            array(
                array(
                    'methods' => WP_REST_Server::CREATABLE, 
                    'callback' => array($this, 'refresh_cache'), 
                )
            )
        );

    }
    /*endpoint [GET]settings*/
    public function get_settings(WP_REST_Request $request)
    { 
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        $response = [
            'message' => "Settings",
            'status'  => 1,
            'settings'    => $settings
        ];
        return rest_ensure_response($response);
    }
    /*endpoint [DELETE]settings*/
    public function delete_settings(WP_REST_Request $request)
    { 
        delete_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        $response = [
            'message' => "Settings Deleted",
            'status'  => 1,
            'data'    => []
        ];
        return rest_ensure_response($response);
    }
    /*endpoint [PUT]settings*/
    public function update_settings(WP_REST_Request $request)
    { 
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        
        if(!is_array($settings))
        {
            $settings = [];
        }
        
        $settings['api_key'] = $request->get_param("api_key");
        $settings['news_ai_api_key'] = $request->get_param("news_ai_api_key");
        $settings['newsdata_io_api_key'] = $request->get_param("newsdata_io_api_key");

        if(empty($settings['api_key']) 
            && empty($settings['news_ai_api_key'])
            && empty($settings['newsdata_io_api_key']))
        {
            return;
        }      

        update_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP, $settings);
        $response = [
            'message' => "Settings Updated",
            'status'  => 1,
            'data'    => $settings
        ];
        return rest_ensure_response($response);
    }
    /*endpoint [POST]settings*/
    public function save_settings(WP_REST_Request $request)
    { 
       return $this->update_settings($request);
    }
    /*endpoint [POST]news*/
    public function import_news(WP_REST_Request $request)
    { 
        $processor = new \WpNewsApiPuller\Processing\NewsProcessor();        
        $response = [
            'message' => "News Imported",
            'status'  => 1,
            'data'    => []
        ];
        $newsjson = $request->get_param('api_json_textarea');
        $newsjson = str_replace("-{", "{", $newsjson);
        $newsjson = str_replace("-\"", "\"", $newsjson);
        $newsjson = str_replace("…", "...", $newsjson);
        $newsobject = json_decode($newsjson);
        //echo $newsjson;die;
        if(empty($newsjson) || empty($newsobject))
        {
            $response['message'] = "Json input is required.";
            $response['status'] = 0;
            return rest_ensure_response($response);
        }  
        $processor = new \WpNewsApiPuller\Processing\NewsProcessor();
        $errors = $processor->create_post_from_news_articles($newsobject);
        $response['data']['count'] = count($newsobject->articles);
        if(count($errors) > 0)
        {
            $response['status'] = 0;
            $response['errors'] = $errors;
            $response['message'] = count($errors). " failed out of ".count($newsobject->articles)." total articles.";
        }
        return rest_ensure_response($response);
    }
    
    /*endpoint [GET]news*/
    public function pull_news(WP_REST_Request $request)
    {
        if($request->get_param("api_type") == "2")
        {
            return rest_ensure_response($this->pull_newsapi_ai());
        }
        if($request->get_param("api_type") == "3")
        {
            return rest_ensure_response($this->pull_newsdata_io());
        }

        $response = [
            'message' => "News Imported",
            'status'  => 0,
            'data'    => []
        ];
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        if(!is_array($settings) || !isset($settings['api_key']))
        { 
            $response['message'] = "api key not found in settings";
            return rest_ensure_response($response);
        }

        $country = $request->get_param("country");
        $source = $request->get_param("source");
        if(empty($country) && empty($source))
        { 
            $response['message'] = "country is required to pull news for";
            return rest_ensure_response($response);
        }

        $today = date("Y-m-d");         
        if(!empty($source))
        {
            $q = "sources=$source";
            $data_meta_key = "pull_news_{$source}_data";
            update_option("pull_news_$source", $today);            
        }
        else
        {
            $q = "country=$country";
            $data_meta_key = "pull_news_{$country}_data";
            update_option("pull_news_$country", $today);
        }
        
        $execute_response = $this->execute_request($q);
        if($execute_response['error'] === true)
        {
            $response['message'] = $execute_response['message'];
            return rest_ensure_response($response);
        }
        $apiResponse = $execute_response['apiResponse'];
        // $apiResponse = get_option($data_meta_key);
        // $apiResponse = json_decode($apiResponse);
        //log the response
        //update_option($data_meta_key, json_encode($apiResponse));
        $response['articlesRecived'] = count($apiResponse->articles);
        $processor = new \WpNewsApiPuller\Processing\NewsProcessor();
        $errors = $processor->create_post_from_news_articles($apiResponse);
        if(count($errors) > 0)
        { 
            $response['errors'] = $errors;            
            $response['message'] = count($errors). " failed out of ".count($apiResponse->articles)." total articles.";
        }
        $response['status'] = 1;
        return rest_ensure_response($response);
    }

    /*endpoint [POST]schedule*/
    public function schedule(WP_REST_Request $request)
    {
        $response = [
            'message' => "Schedule created",
            'status'  => 0,
            'data'    => []
        ];
        $frequency = $request->get_param('frequency');
        $api_type = $request->get_param("api_type");
        if(empty($frequency))
        {
            $response['message'] = "Schedule frequency is required";
            return rest_ensure_response($response);
        }
        if(empty($api_type))
        {
            $response['message'] = "Api Type is required";
            return rest_ensure_response($response);
        }
        
        $country = $request->get_param("country");
        $source = $request->get_param("source");
        if(empty($country) && empty($source) && $api_type == "1")
        { 
            $response['message'] = "country is required to pull news for";
            return rest_ensure_response($response);
        }

        if(!empty($source))
        {
            $q = "sources=$source";             
        }
        else
        {
            $q = "country=$country";
        }

        $interval = $this->convert_to_interval($frequency);
        if(empty($interval))
        {
            $response['message'] = "Schedule frequency is invalid";
            return rest_ensure_response($response);
        }
        if($api_type == "1")
        {
            $this->delete_old_schedule();
            update_option("pull_news_hook_params", $q);        
            wp_schedule_event( time(), $interval, '_newsapi_puller_pull_news_hook' );
        }
        else if($api_type == "2")
        {
            $this->delete_old_schedule_ai();       
            wp_schedule_event( time(), $interval, '_newsapi_puller_pull_news_ai_hook' );
        }
        else if($api_type == "3")
        {
            $this->delete_old_schedule_data_io();       
            wp_schedule_event( time(), $interval, '_newsapi_puller_pull_newsdata_io_hook' );
        }
        $response['status'] = 1;
        return rest_ensure_response($response);
    }
    /*endpoint [DELETE]schedule*/
    public function unschedule(WP_REST_Request $request)
    {
        $response = [
            'message' => "Schedule was deleted.",
            'status'  => 0,
            'data'    => []
        ];
        $api_type = $request->get_param("api_type");
        if($api_type == "1")
        {
            $this->delete_old_schedule();
        }
        else if($api_type == "2")
        {
            $this->delete_old_schedule_ai();
        }
        else if($api_type == "3")
        {
            $this->delete_old_schedule_data_io();
        }
        else {
            $response['message'] = "Api Type is required.";
        }
        $response['status'] = 1;
        return rest_ensure_response($response);
    }    
    /*endpoint [POST]cache*/
    public function refresh_cache(WP_REST_Request $request)
    {        
        $response = [
            'message' => "Cache refreshed",
            'status'  => 1,
            'data'    => []
        ]; 
        $pages = $request->get_param("pages");
        $pages = json_decode($pages);
        foreach($pages as $page)
        {
            $pattern = "/Top\s([0-9]+)\s([a-zA-z]*)/";
            if(preg_match($pattern, $page))
            {
                $type = preg_replace($pattern, "$2", $page);
                $limit = preg_replace($pattern, "$1", $page);
                $cache = new WpRocketCachePreloader([]);
                $response['data'][] = $cache->reload_top($limit);
            }
            else
            {
                $cache->reload_page($page);
                $response['data'][] = $page;
            }
        }        
        return rest_ensure_response($response);
    }

    /*schedule hook pull_news_hook*/
    public function pull_news_hook()
    {
        $response = [
            'message' => "Cron started",
            'status'  => 0,
            'data'    => []
        ];
        $params = get_option("pull_news_hook_params");
        update_option("pull_news_hook_started", "Started at ".date('Y-m-d H:i:s')." with params ".$params);
        try {
            $this->execute_sheduled_hook($params);
        }catch(Exception $ex){
            update_option("pull_news_hook_done", $ex->getMessage());
        }
    }
    /*schedule hook pull_news_ai_hook*/
    public function pull_news_ai_hook()
    { 
        $response = [
            'message' => "News Imported",
            'status'  => 1,
            'data'    => []
        ];
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        $api_key = $settings['news_ai_api_key'];
        if(empty($api_key))
        { 
            update_option("pull_news_ai_hook_done", "Api Key was not found in settings");
            return;
        }
        $client = new \WpNewsApiPuller\NewsApiClient($api_key);
        update_option("pull_news_ai_hook_started", "Started at ".date('Y-m-d H:i:s'));
        $results = $client->search();
        if($results['status'] != "OK" || isset($results['data']->error))
        {
            $response['message'] =  $results['data']->error; 
            update_option("pull_news_ai_hook_done", "Resulted with error, detail: ". json_encode($response));
            return;
        }
        $articles = $results['data']->articles->results;
        $processor = new \WpNewsApiPuller\Processing\NewsProcessor();
        $errors = $processor->create_post_from_news_ai_articles($articles);
        if(count($errors) > 0)
        { 
            $response['errors'] = $errors;            
            $response['message'] = count($errors). " failed out of ".count($articles)." total articles.";
        }
        $response['status'] = 1;
        update_option("pull_news_ai_hook_done", "Finished at ".date('Y-m-d H:i:s')." with results ". json_encode($response));
    }
    /*schedule hook pull_newsdata_io_hook*/
    public function pull_newsdata_io_hook()
    { 
        $response = [
            'message' => "News Imported",
            'status'  => 1,
            'data'    => []
        ];
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        $api_key = $settings['newsdata_io_api_key'];
        if(empty($api_key))
        { 
            update_option("pull_newsdata_io_hook_done", "Api Key was not found in settings");
            return;
        }
        update_option("pull_newsdata_io_hook_started", "Started at ".date('Y-m-d H:i:s'));
        $client = new \WpNewsApiPuller\NewsDataDotIOClient($settings['newsdata_io_api_key']);
        $results = $client->search();
        if($results['status'] != "OK" || $results['data']->status == "error")
        {
            $response['message'] =  $results['data']->results->message;
            $response['status'] = 0;
            update_option("pull_newsdata_io_hook_done", "Resulted with error, detail: ". json_encode($response));
        }
        $articles = $results['data']->results;
        $processor = new \WpNewsApiPuller\Processing\NewsProcessor();
        $errors = $processor->create_post_from_newsdata_io_articles($articles);
        if(count($errors) > 0)
        { 
            $response['errors'] = $errors;            
            $response['message'] = count($errors). " failed out of ".count($articles)." total articles.";
        }
        $response['status'] = 1;
        $now = date('Y-m-d H:i:s');
        $response_json = json_encode($response);
        update_option("pull_newsdata_io_hook_done", "Finished at $now with results $response_json");
    }

    private function pull_newsapi_ai()
    {
        $response = [
            'message' => "News Imported",
            'status'  => 1,
            'data'    => []
        ]; 
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        if(empty($settings['news_ai_api_key']))
        {
            $response['message'] =  "API Key was not found in settings.";
            $response['status'] = 0;
            return rest_ensure_response($response);
        }
        $client = new \WpNewsApiPuller\NewsApiClient($settings['news_ai_api_key']);
        $results = $client->search();
        if($results['status'] != "OK" || isset($results['data']->error))
        {
            $response['message'] =  $results['data']->error;
            $response['status'] = 0;
            return rest_ensure_response($response);
        }
        $articles = $results['data']->articles->results;
        $processor = new \WpNewsApiPuller\Processing\NewsProcessor();
        $errors = $processor->create_post_from_news_ai_articles($articles);
        if(count($errors) > 0)
        { 
            $response['errors'] = $errors;            
            $response['message'] = count($errors). " failed out of ".count($articles)." total articles.";
        }
        $response['status'] = 1;
        return $response;
    }

    private function pull_newsdata_io()
    {
        $response = [
            'message' => "News Imported",
            'status'  => 1,
            'data'    => []
        ]; 
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        if(empty($settings['newsdata_io_api_key']))
        {
            $response['message'] =  "API Key was not found in settings.";
            $response['status'] = 0;
            return rest_ensure_response($response);
        }
        $client = new \WpNewsApiPuller\NewsDataDotIOClient($settings['newsdata_io_api_key']);
        $results = $client->search();
        if($results['status'] != "OK" || $results['data']->status == "error")
        {
            $response['message'] =  $results['data']->results->message;
            $response['status'] = 0;
            return rest_ensure_response($response);
        }
        $articles = $results['data']->results;
        $processor = new \WpNewsApiPuller\Processing\NewsProcessor();
        $errors = $processor->create_post_from_newsdata_io_articles($articles);
        if(count($errors) > 0)
        { 
            $response['errors'] = $errors;            
            $response['message'] = count($errors). " failed out of ".count($articles)." total articles.";
        }
        $response['status'] = 1;
        return $response;
    }

    private function delete_old_schedule()
    {
        $timestamp = wp_next_scheduled( '_newsapi_puller_pull_news_hook', );
        if ($timestamp) {
            wp_unschedule_event($timestamp, '_newsapi_puller_pull_news_hook' );
        }
    }

    private function delete_old_schedule_ai()
    {
        $timestamp = wp_next_scheduled( '_newsapi_puller_pull_news_ai_hook', );
        if ($timestamp) {
            wp_unschedule_event($timestamp, '_newsapi_puller_pull_news_ai_hook' );
        }
    }

    private function delete_old_schedule_data_io()
    {
        $timestamp = wp_next_scheduled( '_newsapi_puller_pull_newsdata_io_hook', );
        if ($timestamp) {
            wp_unschedule_event($timestamp, '_newsapi_puller_pull_newsdata_io_hook' );
        }
    }

    private function execute_sheduled_hook($params)
    {
        $execute_response = $this->execute_request($params);
        if($execute_response['error'] === true)
        {
            $response['message'] = $execute_response['message'];
            update_option("pull_news_hook_done", "RResulted with error, detail: ". json_encode($response));
            return;
        }
        $apiResponse = $execute_response['apiResponse'];
        $response['articlesRecived'] = count($apiResponse->articles);
        $processor = new \WpNewsApiPuller\Processing\NewsProcessor();
        $errors = $processor->create_post_from_news_articles($apiResponse);
        if(count($errors) > 0)
        { 
            $response['errors'] = $errors;            
            $response['message'] = count($errors). " failed out of ".count($apiResponse->articles)." total articles.";
        }
        $response['status'] = 1;
        update_option("pull_news_hook_done", "Finished at ".date('Y-m-d H:i:s')." with results ". json_encode($response));
    }

    private function convert_to_interval($frequency)
    {
        $interval = "";
        switch($frequency)
        {
            case .5:
                $interval = "half_hourly";
                break;
            case 1:
                $interval = "hourly";
                break;
            case 2:
                $interval = "two_hours";
                break;
            case 3:
                $interval = "three_hours";
                break;
            case 6:
                $interval = "six_hours";
                break;
            case 9:
                $interval = "nine_hours";
                break;
            case 12:
                $interval = "twicedaily";
                break;
        }
        return $interval;
    }

    private function execute_request($q)
    {
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        $api_key = $settings['api_key'];
        $url = "https://newsapi.org/v2/top-headlines?$q&apiKey=$api_key&sortBy=publishedAt&from".date('Y-m-d');
        $response = [
            'error' => false
        ];
        $client = new \GuzzleHttp\Client();
        try 
        {
            $apiResponse = $client->get($url);
            $apiResponse = $apiResponse->getBody()->getContents();
            $apiResponse = json_decode($apiResponse);
            if($apiResponse->status != 'ok'){
                $response['message'] = $apiResponse->message;
                $response['error'] = true;
            }
            $response['apiResponse'] = $apiResponse;
        } catch(GuzzleHttp\Exception\ClientException $e){
            $response['message'] = $e->getMessage();
            $response['error'] = true;
        }

        return $response;
    }
}