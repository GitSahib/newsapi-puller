<?php
namespace WpNewsApiPuller\Processing;
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
require_once(ABSPATH . 'wp-admin/includes/post.php');
require_once('wp-newsapi-puller-utils.php');
use WpNewsApiPuller\Utils\Utils;
class NewsProcessor
{
	public function __construct()
	{

	}

	function create_post_from_news_articles($news)
	{  
		global $wpdb;
	    $pattern= "/…[\s]*[\+[0-9]+\schars]/";
	    $errors = [];
	    $articles = $news->articles;
	    $index = 0;
	    foreach($articles as $a)
	    {        
	    	$index += 1;
	        $readmore = ' <a href="'.$a->url.'" title="Read More">Read More</a>';
	        $a->urlToImage = $this->get_image_url($a, 'urlToImage');
	        if(empty($a->content) && !empty($a->title))
	    	{
	    		$content = $a->title.$readmore;
	    	}
	    	else
	    	{
	    		$content =  str_replace("…", "...", $content);
	    		$content = preg_replace($pattern, $readmore, $a->content);	        
	    	}

	    	if(empty($a->description) && !empty($a->title))
	    	{
	    		$a->description = $a->title;
	    	}
	        
	        if(empty($content))
	        {
	        	continue;
	        }
 			
 			$title = preg_replace('/[^\w ]+/', "", $a->title);
	        $post_name = str_replace(" ", "-", strtolower($title));
			
			$existing_post = post_exists( $a->title, $content, '', 'post', 'publish' );
			
			if(!empty($existing_post))
			{
				$errors[] = "$post_name already exists.";
				continue;
			}
	        $post = [
	            "post_content" => $content,
	            "post_excerpt"  => $a->description,
	            "post_title" =>   $a->title,
	            "post_status" => 'publish',
	            "post_name" => $post_name,
	            "post_type" => "post"
	        ];

	        $source = $a->source;
		    $category = ""; 
		    if(isset($source->name) && $source->name)
		    {
		    	$category = $source->name;
		    }
	        
	        if(!empty($category))
	        {
	        	$exists = category_exists($category);
	        	if(!$exists)
	        	{
	        		$inserted = wp_insert_term( 
						$category,  
						'category', 
						[]
					);
					$exists = $inserted['term_id'];
	        	}
	        	$post['post_category'] = [$exists];
	        }

	        $excerpt = $a->description;   
	        $post = wp_insert_post($post);
	        if($post == 0)
	        { 
	        	$errors[] = $wpdb->last_error;
	        	continue;
	        } 
	        $meta = [
	        	"is_imported" => 1,
	        	"published_at" => $a->publishedAt
	        ];
	        $meta["imported_news_thumbnail_url"] = $a->urlToImage;
	        if(!empty($a->author))
	        {
	        	$meta["author"] = $a->author; 
	        }

	        update_post_meta($post, "imported-news-meta", $meta); 
	               
	        $this->download_feature_image_for_post($post, $a->urlToImage, $post_name, $excerpt);
	    }
	    return $errors;
	}

	function create_post_from_news_ai_articles($articles)
	{  
		global $wpdb;
		$replace = "\n\nContinue Reading Show full articles without \"Continue Reading\" button for {0} hours.";
	    foreach($articles as $a)
	    {    
	    	$a->image = $this->get_image_url($a, "image");
	    	$content = $a->body;    	        
	        if(empty($content) && !empty($a->title))
	    	{
	    		$content = $a->title;
	    	}
	    	if(empty($content))
	    	{
	    		continue;
	    	}
	    	$content = str_replace("$replace", "", $content);
	    	$paragraphs = explode("\n\n", $content);
	        $post_excerpt = $paragraphs[0];
 			
 			$title = preg_replace('/[^\w ]+/', "", $a->title);
	        $post_name = str_replace(" ", "-", substr(strtolower($title), 0, 60));
			
			$existing_post = post_exists( $title, $content, '', 'post', 'publish' );
			
			if(!empty($existing_post))
			{
				$errors[] = "$post_name already exists.";
				continue;
			}
	        $post = [
	            "post_content" => $content,
	            "post_excerpt"  => $post_excerpt,
	            "post_title" =>   $title,
	            "post_status" => 'publish',
	            "post_name" => $post_name,
	            "post_type" => "post",
	            "post_category" => []
	        ];

	        $categories = $a->categories;
		    $categories = array_map(function($c) {
		    	return substr($c->label, strrpos($c->label, '/') + 1);
		    }, $categories);

		  	foreach($categories as $c)
		  	{
		  		if(empty($c))
		  		{
		  			continue;
		  		}
		  		$exists = category_exists($c);
	        	if(!$exists)
	        	{
	        		$inserted = wp_insert_term( 
						$c,  
						'category', 
						[]
					);
					$exists = $inserted['term_id'];
	        	}
	        	$post['post_category'][] = $exists;
		  	}
	        $post = wp_insert_post($post);
	        if($post == 0)
	        { 
	        	$errors[] = $wpdb->last_error;
	        	continue;
	        }
	        $meta = [
	        	"is_imported" => 1,
	        	"published_at" => $a->dateTimePub
	        ];
	        $meta["imported_news_thumbnail_url"] =  $a->image;
	        if(!empty($a->authors))
	        {
	        	$meta["author"] = $a->authors; 
	        }

	        update_post_meta($post, "imported-news-meta", $meta); 
	    }
	    return $errors;
	}

	function create_post_from_newsdata_io_articles($articles)
	{  
		global $wpdb;
		$errors = [];
	    foreach($articles as $a)
	    {     
	        $a->image_url = $this->get_image_url($a, "image_url");
	    	$content = $a->content;    	        
	        if(empty($content) && !empty($a->title))
	    	{
	    		$content = $a->title;
	    	}
	    	if(empty($content))
	    	{
	    		continue;
	    	}
	        $post_excerpt = $a->description;
 			
 			$title = preg_replace('/[^\w ]+/', "", $a->title);
	        $post_name = str_replace(" ", "-", substr(strtolower($title), 0, 60));
			$existing_post = post_exists( $title, $content, '', 'post', 'publish' );
			
			if(!empty($existing_post))
			{
				$errors[] = "$post_name already exists.";
				continue;
			}
	        $post = [
	            "post_content" => $content,
	            "post_excerpt"  => $post_excerpt,
	            "post_title" =>   $title,
	            "post_status" => 'publish',
	            "post_name" => $post_name,
	            "post_type" => "post",
	            "post_category" => []
	        ];

	        $categories = $a->category;
		  	foreach($categories as $c)
		  	{
		  		if(empty($c))
		  		{
		  			continue;
		  		}
		  		$exists = category_exists($c);
	        	if(!$exists)
	        	{
	        		$inserted = wp_insert_term( 
						$c,  
						'category', 
						[]
					);
					$exists = $inserted['term_id'];
	        	}
	        	$post['post_category'][] = $exists;
		  	}
	        $post = wp_insert_post($post);
	        if($post == 0)
	        { 
	        	$errors[] = $wpdb->last_error;
	        	continue;
	        } 

	        if(!empty($a->keywords))
	        {
	        	wp_set_object_terms($post, $a->keywords, 'post_tag', true);
	        }

	        $meta = [
	        	"is_imported" => 1,
	        	"published_at" => $a->pubDate
	        ];
	        $meta["imported_news_thumbnail_url"] =  $a->image_url;
	        if(!empty($a->creator))
	        {
	        	$meta["author"] = $a->creator; 
	        }
	        update_post_meta($post, "imported-news-meta", $meta);
	    }
	    return $errors;
	}

	function build_image_name_from_url($url, $image_name)
	{
		global $wpdb;
	    $image_url        = $url; // Define the image URL here
	    $oext = pathinfo($url)['extension'];
	    $next = $oext;
	    switch($next)
	    {
	        case 'jpeg':
	        case 'jpg':
	        case 'tiff':
	        case 'png':
	        case 'gif':
	            break;
	        default:
	            $next = 'jpg';
	            break;
	    }
	    $next = ".$next";
	    $image_name = str_replace($oext, $next, $image_name);
	    if(strpos($image_name, $next) === FALSE){
	        $image_name  = "$image_name$next";
	    }
	    return $image_name;
	}

	function download_feature_image_for_post($post_id, $url, $name, $excerpt)
	{ 
	    // Add Featured Image to Post
	    // $image_name       = $this->build_image_name_from_url($url, "feature_image_$post_id");

	    // $upload_dir       = wp_upload_dir(); // Set upload folder
	    // $image_data       = file_get_contents($url); // Get image data
	    // $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
	    // $filename         = basename( $unique_file_name ); // Create image file name

	    // // Check folder permission and define file location
	    // if( wp_mkdir_p( $upload_dir['path'] ) ) {
	    //     $file = $upload_dir['path'] . '/' . $filename;
	    // } else {
	    //     $file = $upload_dir['basedir'] . '/' . $filename;
	    // }
	    $file = Utils::resolve_image_url($url);

	    // Create the image  file on the server
	    //file_put_contents( $file, $image_data );

	    // Check image file type
	    $wp_filetype = wp_check_filetype( $file, null );

	    // Set attachment data
	    $attachment = array(
	        'post_mime_type' => $wp_filetype['type'],
	        'post_title'     => $name,
	        'post_content'   => $excerpt,
	        'post_status'    => 'inherit'
	    ); 
	    
	    // Create the attachment
	    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
	    //update_post_meta($attach_id, "_wp_attached_file")
	    // Include image.php
	    //require_once(ABSPATH . 'wp-admin/includes/image.php');

	    // Define attachment metadata
	    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );

	    // Assign metadata to attachment
	    wp_update_attachment_metadata( $attach_id, $attach_data );

	    // And finally assign featured image to post
	    set_post_thumbnail( $post_id, $attach_id );

	}

	private function get_image_url($article, $image_url)
	{
		return Utils::resolve_image_url($article->{$image_url});
	}
}