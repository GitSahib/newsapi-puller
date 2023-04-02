<?php
namespace WpNewsApiPuller;

class NewsApiClient
{
	var $apiKey;
	var $query = [
		'$query' => [
			'$and' => [
				["locationUri" => "http://en.wikipedia.org/wiki/United_States"],
				[
					"lang" => "eng",
					"dateStart" => "",
					"dateEnd" => ""
				]
			],
			'$filter' => 
			[
				"isDuplicate" => "skipDuplicates"
			]
		]
	];
	var $request = [
		"query" => "",
		"resultType" => "articles",
	    "articlesSortBy" => "date",
	    "articlesCount" => 100,
	    //"includeArticleSocialScore" => true, 
	    //"includeArticleConcepts" => true,
	    "includeArticleCategories" => true,
	    "includeArticleLocation" => true,
	    "includeArticleImage" => true,
	    "includeArticleOriginalArticle" => true,
	    //"includeConceptImage" => true
	    "articleBodyLen" => -1,
	    "apiKey" => ""
	];
	var $base_uri = "https://newsapi.ai/api/v1/article/getArticles";
	function __construct($apiKey)
	{
		$this->apiKey = $apiKey;
		$this->request["apiKey"] = $this->apiKey;
		$this->setup_query();
	}

	public function get_query()
	{ 
		return $this->request;
	}

	public function search($query = [])
	{
		if(!empty($query))
		{
			$this->setup_query($query);
		}
		return $this->execute();
	}

	private function setup_query($query = [])
	{
		if(empty($query))
		{
			$query = $this->query;
		}
		$query['$query']['$and'][1]["dateStart"] = date("Y-m-d");
		$query['$query']['$and'][1]["dateEnd"] = date("Y-m-d");
		$this->request["query"] = json_encode($query);
	}

	public function execute()
	{
		try
		{
			$url = $this->base_uri."?".http_build_query($this->request);
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