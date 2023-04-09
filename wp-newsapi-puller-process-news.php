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
	        $a->{'image_url'} = $this->get_image_url($a, 'urlToImage');
	        $a->{'link'} = $a->url;
	        $a->{'published_at'} = $a->publishedAt;
	        $source = $a->source;
		    if($source && !empty($source->name))
		    {
		    	 $a->{'category'} = [$a->source->name];
		    }
		    else 
		    {
		    	$a->category = [];
		    }	        
	        $error = $this->insert_post_from_common_article($a);
	        if(!empty($error))
	        {
	        	$errors[] = $error;
	        }
	    }
	    return $errors;
	}

	function create_post_from_news_ai_articles($articles)
	{  
		global $wpdb;
	    foreach($articles as $a)
	    {    
	    	$a->{'image_url'} = $this->get_image_url($a, "image");
	    	$a->{'link'} = $a->url;
	    	$a->{'content'} = $a->body; 
	    	$paragraphs = explode("\n\n", $a->content);
	        $a->{'description'} = $paragraphs[0];
	    	if(!empty($a->categories))
	    	{
	    		$a->{'category'} = array_map(function($c) {
		    		return substr($c->label, strrpos($c->label, '/') + 1);
		    	}, $a->categories);
	    	}
	    	else
	    	{
	    		$a->{'category'} = [];
	    	}
	    	$a->{'published_at'} = $a->dateTimePub;
	    	$a->{'author'} = $a->authors;
		  	$error = $this->insert_post_from_common_article($a);
	        if(!empty($error))
	        {
	        	$errors[] = $error;
	        }
	    }
	    return $errors;
	}

	function create_post_from_newsdata_io_articles($articles)
	{  
		global $wpdb;
		$errors = [];
	    foreach($articles as $a)
	    {    
	        $a->{'published_at'} = $a->pubDate;
	        $a->{'author'} = $a->creator;
	        $a->image_url = $this->get_image_url($a, "image_url");
	        $error = $this->insert_post_from_common_article($a);
			if(!empty($error))
			{
				$errors[] = $error;
			}
	    }
	    return $errors;
	}

	function insert_post_from_common_article($a)
	{
		$readmore =  "<a href='{$a->link}' title='Read More'>Read More</a>";    	        
        
        if(empty($a->content))
    	{
    		$a->content = $a->title;
    	}
    	
    	if(empty($a->description))
    	{
    		$a->description = wp_trim_words($a->content, 100);
    	}

    	if(empty($a->content))
    	{
    		return "No Content.";
    	}

    	if(empty($a->description))
        {
        	return "Mo excerpt";
        }

    	$content = wp_trim_words($a->content, 50).$readmore;

        $post_excerpt = $a->description; 

		$title = preg_replace('/[^\w ]+/', "", $a->title);
        
        $post_name = str_replace(" ", "-", substr(strtolower($title), 0, 60));

		$existing_post = post_exists( $title, '', '', 'post', 'publish' );
		
		if(	
			!empty($existing_post) && 
			( 
				!isset($a->link) || 
				!empty(get_post_meta($existing_post, "external_link", true))
			)
		) 
		{
			return "$post_name already exists.";
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
        	return 0;
        } 

        if(isset($a->keywords) && !empty($a->keywords))
        {
        	wp_set_object_terms($post, $a->keywords, 'post_tag', true);
        }

        $meta = [
        	"is_imported" => 1,
        	"published_at" => $a->published_at,
        	"author" => $a->author,
        	"imported_news_thumbnail_url" => $a->image_url,        	
        ];

        update_post_meta($post, "external_link", $a->link);
        update_post_meta($post, "imported-news-meta", $meta);
        $this->download_feature_image_for_post($post, $a->image_url, $title." picture", $post_excerpt);
	}

	function build_image_name_from_url($url, $image_name)
	{
		global $wpdb;
	    $image_url  = $url; // Define the image URL here
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

	function download_feature_image_for_post($post_id, $file, $name, $excerpt)
	{   
 		//get file info
	    $wp_filetype = wp_check_filetype( $file, null );
	    // Set attachment data
	    $attachment = array(
	        'post_mime_type' => $wp_filetype['type'],
	        'post_title'     => $name,
	        'post_content'   => $excerpt,
	        'post_status'    => 'inherit'
	    ); 
	    //insert attachment post
	    $attach_id = wp_insert_attachment( $attachment, $file, $post_id ); 
	    // Define attachment metadata
	    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
	    //echo $attach_id.$file.$excerpt.$name;
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