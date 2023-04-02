<?php
namespace WpNewsApiPuller;

class NewsDataDotIOClient
{
	var $apiKey; 
	var $base_uri = "https://newsdata.io/api/1/news";
	var $query = [];
	function __construct($apiKey)
	{
		$this->apiKey = $apiKey;
		$this->setup_query();
	} 

	public function search($query = [])
	{
		$this->setup_query($query);
		return $this->execute();
	}

	private function setup_query($query = [])
	{
		$this->query = array_merge([
			"apiKey" => $this->apiKey,
			"language" => "en",
			"from_date" => date("Y-m-d"),
			"to_date" => date("Y-m-d")
		], $query);
	}

	public function execute()
	{
		try
		{
			$url = $this->base_uri."?".http_build_query($this->query);
			$request = wp_remote_get($url);
			$response_code = wp_remote_retrieve_response_code($request);
			$response_message = wp_remote_retrieve_response_message($request);
			$body = wp_remote_retrieve_body( $request );
			return [
				'status_code' => $response_code,
				'status' => $response_message,
				'data' => json_decode($body)
			];
		}
		catch(Exception $e)
		{
			return [
				'status_code' => 503,
				'status' => "Internal Server Error",
				'data' => [
					"error" => "An exception occurred while executing request, details:".$e->getMessage()
				]
			];
		}
	}
}