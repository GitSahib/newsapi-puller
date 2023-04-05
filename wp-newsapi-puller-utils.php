<?php 
namespace WpNewsApiPuller\Utils;
class Utils
{
	public static function resolve_image_url($image_url)
    {
        if(empty($image_url) || !@getimagesize($image_url))
        {
            return plugin_dir_url(__FILE__)."placeholder-image.png";
        }
        return $image_url;
    }
}