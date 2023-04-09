<?php
namespace WpNewsApiPuller\Settings;
require_once('wp-newsapi-puller-utils.php');
use WpNewsApiPuller\Utils\Utils;
class Settings
{
    /**
     * Settings array
     *
     * @var array
     */ 
    /**
     * Menu slug
     *
     * @var string
     */
    protected $slug = WP_NEWSAPI_PULLER_SETTINGS_MAIN_PAGE;
    /**
     * Rest
     *
     * @var string
     */
    protected $rest = WP_NEWSAPI_PULLER_REST;
    /**
     * Menu slug
     *
     * @var string
     */
    protected $settings_group = WP_NEWSAPI_PULLER_SETTINGS_GROUP; 
    /**
     * URL for assets
     *
     * @var string
     */
    protected $assets_url = WP_NEWSAPI_PULLER_PLUGIN_ASSETS;
    /**
     * URL for plugin_dir
     *
     * @var string
     */
    protected $plugin_dir = WP_NEWSAPI_PULLER_PLUGIN_DIR;
    /**
     * Apex_Menu constructor.
     *
     * @param string $assets_url URL for assets
     */
    /**
     * Start up
     */ 
    public function __construct($is_ajax = false)
    {         
        $this->load_settings();
        $this->is_ajax = $is_ajax;
        if(!$is_ajax){
            add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );
        }
        //add_filter("has_post_thumbnail", array($this, "has_post_thumbnail"), 10, 3);
        //add_filter("post_thumbnail_html", array($this, "post_thumbnail_html"), 10, 5);
        //add_filter("wp_get_attachment_image_src", array($this, "wp_get_attachment_image_src"), 10, 4);
        add_filter("cron_schedules", array($this, 'add_custom_cron_schedules'), 10, 1 );
        add_filter("get_the_date", array($this, "get_the_date"), 10 , 3);
        add_filter("author_link", array($this, "author_link"), 10 , 3);
        add_filter("the_author", array($this, "the_author"), 10 , 1);
        add_filter('the_excerpt_rss', array($this, 'the_excerpt_rss'), 10, 1);
        add_filter('the_content_feed', array($this, 'the_excerpt_rss'), 10, 2);
        add_filter('wp_get_attachment_url', array($this, "wp_get_attachment_url"), 10, 2);
        add_filter('wp_lazy_loading_enabled', array($this, "wp_lazy_loading_enabled"), 10, 3 );
        //add_filter("the_content", array($this, "the_content"), 10, 1);
    }
    
    public function wp_lazy_loading_enabled($default, $tag_name, $context)
    {
        return TRUE; //default == 'img';
    }

    public function wp_get_attachment_url($url, $post_id)
    {
        if(!@getimagesize($url) && $post_id)
        { 
            $attachment = get_post_meta($post_id, "_wp_attached_file", true);
            if($attachment)
            {
                $url = $attachment;
            }
        }
        if(FALSE !== strpos($url, "wp-content/uploads/http"))
        { 
            return str_replace(site_url("wp-content/uploads/"), "", $url);
        }
        return $url;
    }
    private function get_meta($post, $key, $altkey)
    {
        $meta = get_post_meta($post->ID, "imported-news-meta", true);
        if($meta && isset($meta[$key]))
        {
            $meta = $meta[$key];
        }
        else 
        {
            $meta = get_post_meta($post->ID, "$altkey", true);
        }
        return $meta;
    }

    public function the_content($content)
    {
        
        if(preg_match('/\R/', trim($content,)))
        {
            return $content;
        }

        $sentences = preg_split('/(?<=[.?!])\s+(?=[A-Z])/', $content);

        // Define a variable to hold the current paragraph
        $paragraph = '';

        // Define an array to hold the final paragraphs
        $paragraphs = [];

        // Loop through the sentences
        foreach ($sentences as $sentence) {
            // Append the current sentence to the current paragraph
            $paragraph .= $sentence;
            
            // Check if the current sentence ends with a period and the next sentence begins with a capital letter
            if (preg_match('/\.\s+[A-Z]/', $sentence) && count($paragraphs) > 0) {
                // Add the current paragraph to the list of paragraphs
                $paragraphs[] = $paragraph;
                
                // Start a new paragraph
                $paragraph = '';
            }
        }

        // Add the last paragraph to the list of paragraphs
        if (!empty($paragraph)) {
            $paragraphs[] = $paragraph;
        }

        return "<p>".implode("</p><p>", $paragraphs)."</p>";
    }

    public function the_excerpt_rss($content, $type = "feed") 
    {
        global $post;
        if ( has_post_thumbnail( $post->ID ) )
        {
            $thumb = get_the_post_thumbnail( $post->ID );
            $content = "<div>$thumb</div>$content";
        }
        return $content;
    }
    
    public function add_custom_cron_schedules( $schedules ) { 
        $schedules['half_hourly'] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display'  => esc_html__( 'Every thirty minutes' ), 
        );
        $schedules['two_hours'] = array(
            'interval' => 2 * HOUR_IN_SECONDS,
            'display'  => esc_html__( 'Every two hours' ), 
        );
        $schedules['three_hours'] = array(
            'interval' => 3 * HOUR_IN_SECONDS,
            'display'  => esc_html__( 'Every three hours' ), 
        );
        $schedules['six_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => esc_html__( 'Every six hours' ), 
        );
        $schedules['nine_hours'] = array(
            'interval' => 9 * HOUR_IN_SECONDS,
            'display'  => esc_html__( 'Every nine hours' ), 
        );
        return $schedules;
    }


    private function load_settings()
    {
        $settings = get_option($this->settings_group);
        if(isset($settings) && is_array($settings)){
            $this->api_key = $settings['apiKey'];
        }
    } 

    public function author_link($link, $author_id, $author_nicename)
    { 
        return "#";
    }

    public function the_author($author)
    { 
        global $post;
        $news_author = $this->get_meta($post, "author", "news-author");
        if(empty($news_author))
        {
            return '';
        }
        if(is_array($news_author))
        {
            $news_author = array_map(function($a){
                if(is_object($a) && isset($a->name)) {
                    $author = $a->name;
                }else {
                    $author = $a;
                }
                return $author;
            }, $news_author);

            $news_author = implode(",", $news_author);
        }
        $truncated = substr($news_author, 0, 10);
        return $truncated == $news_author ? $truncated : $truncated."...";
    }

    public function get_the_date($the_date, $format, $post)
    {  
        $published_at = $this->get_meta($post, "published_at", "published-at");   
       
        if(empty($published_at))
        {
            return $the_date;
        }

        $the_date = date( get_option("date_format"), strtotime($published_at) );
        
        return $the_date;
    }

    public function wp_get_attachment_image_src($image, $attachment_id, $size, $icon)
    {
        if(is_array($image) && !empty($image))
        {
            return $image;
        }

        if(isset($attachment_id) && $attachment_id > 0)
        {
            return $image;
        }

        if(!isset( $GLOBALS['post'] )){
            return $image;
        }

        $post = $GLOBALS['post'];
        
        $is_imported = $this->get_meta($post, "is_imported", "is_imported");
        
        if(!$is_imported)
        {
            return $image;
        }

        $url = $this->get_meta($post, "imported_news_thumbnail_url", "imported_news_thumbnail_url");

        return [Utils::resolve_image_url($url)];
    }

    public function post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr)
    {
        //return $html;
        if(isset($post_thumbnail_id) && $post_thumbnail_id > 0)
        {
            return $html;
        }

        if(!$post_id && isset( $GLOBALS['post'] ))
        {
            $post = $GLOBALS['post'];
        }
        else if($post_id)
        {
            $post = get_post($post_id);
        }
        else
        {
            return $html;
        }
       
        $url = $this->get_meta($post, "imported_news_thumbnail_url", "imported_news_thumbnail_url");
        $url = Utils::resolve_image_url($url);
        if(empty($url))
        {
            return $html;
        }

        
        return "<img class='img-fluid not-transparent wp-post-image img-responsive' src='$url' />";

    }

    public function has_post_thumbnail($has_thumbnail, $post_id, $thumbnail_id )
    { 
        
        if(isset($thumbnail_id) && $thumbnail_id > 0)
        {
            return $has_thumbnail;
        }

        if(!$post_id && isset( $GLOBALS['post'] ))
        {
            $post = $GLOBALS['post'];
        }
        else if($post_id)
        {
            $post = get_post($post_id);
        }
        else
        {
            return $has_thumbnail;
        }


        $is_imported = $this->get_meta($post, "is_imported", "is_imported");

        return $is_imported == 1 ? true : false;
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin', 
            'NewsApi Puller Settings', 
            'manage_options', 
            $this->slug, 
            array( $this, 'create_admin_page' )
        );
    } 
    /**
     * Options page callback
     */
    public function create_admin_page()
    {   
        global $pagenow;
        $tab = isset($_GET['tab']) ? $_GET['tab'] : "import";
        $import_url  = admin_url($pagenow)."?page=".$this->slug;         
        $use_api_url = $import_url . "&tab=useapi";
        $schedule_url = $import_url. "&tab=schedule";
        $settings_url = $import_url. "&tab=settings";
        ?>
        <div class="wrap newsapi-puller-settings-wrap col-md-12">
            <div class="newsapi-puller-thinking">
                <span class="loader-text">Please Wait...</span>
                <span class="loader"></span>
            </div>                  
            <img class="logo" src="<?php echo "{$this->assets_url}img/newsapi-puller.png"; ?>" />
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo $import_url; ?>" data-tab='import-data' class="nav-tab <?php echo $tab == "import" ? "nav-tab-active": ""; ?>"> <span class="dashicons dashicons-database-import"></span> Import</a>
                <a href="<?php echo $settings_url;?>" data-tab='settings-data' class="nav-tab <?php echo $tab == "settings" ? "nav-tab-active": ""; ?>"><span class="dashicons dashicons-admin-settings"></span> Settings</a>
                <a href="<?php echo $use_api_url;?>" data-tab='use-api-data' class="nav-tab <?php echo $tab == "useapi" ? "nav-tab-active": ""; ?>"><span class="dashicons dashicons-rest-api"></span> Use Api</a>
                <a href="<?php echo $schedule_url;?>" data-tab='schedule-data' class="nav-tab <?php echo $tab == "schedule" ? "nav-tab-active": ""; ?>"><span class="dashicons dashicons-schedule"></span> Schedule</a>
            </h2>
            <div class="body <?php echo $tab?>">       
                <?php  
                   $this->print_settings_page($tab);
                ?>
            </div> 
        </div>
        <?php    
    } 


    /**
     * Register CSS and JS for page
     *
     * @uses "admin_enqueue_scripts" action
     */
    public function register_assets()
    {
        wp_register_script( 
                $this->slug."-notificationService", 
                $this->assets_url . 'js/notificationService.js', array( 'jquery' ) );
        wp_register_script( 
                $this->slug."-dataService", 
                $this->assets_url . 'js/dataService.js', array( 'jquery' ) );
        wp_register_script( 
                $this->slug."-admin", 
                $this->assets_url . 'js/admin.js', array( 'jquery') );
        
        wp_register_style( $this->slug, $this->assets_url . 'css/admin.css' );
        $toLocalize = array(
            'strings' => array(
                'saved' => __( 'Settings Saved', 'text-domain' ),
                'error' => __( 'Error', 'text-domain' )
            ),
            'api'     => array(
                'base'   => esc_url_raw( rest_url( $this->rest ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),                
            )
        );
        wp_localize_script( $this->slug."-notificationService", 'NewsApiPuller', $toLocalize );
        wp_localize_script( $this->slug."-dataService", 'NewsApiPuller', $toLocalize );
        wp_localize_script( $this->slug."-admin", 'NewsApiPuller', $toLocalize ); 
        $this->enqueue_assets(); 
    }

   /**
     * Enqueue CSS and JS for page
    */
    public function enqueue_assets(){  
        wp_enqueue_script( $this->slug."-notificationService" );
        wp_enqueue_script( $this->slug."-dataService" );
        wp_enqueue_script( $this->slug."-admin" );
        wp_enqueue_style( $this->slug );
    }
    private function print_settings_page($tab)
    {
         ?>
            <div class="row">
                <div class="col-md-12">
                    <?php  $this->print_tab($tab); ?>
                </div>
            </div>
        <?php 
    }

    private function print_tab($tab)
    {
        switch($tab) {
            case 'import': ?>
                <div class="text-left">
                    <h3>Enter Json to Import</h3>
                </div>
                <?php
                $this->print_row('Json', 'api_json_textarea_callback', '', 'required');
                $this->submit_button("Import");
                break;
            case "useapi":?>
                <div class="text-left">
                    <h3>Please select country or source to pull news</h3>
                </div>
                <?php
                $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
                if(!isset($settings['api_key'])) {
                    echo "<p>Please provide your API Key in the Settings tab and save to continue here.</p>";
                    return;
                }
                echo "<div class='col-md-5 pull-news-box'>";
                echo "<p class='heading'>Select country or source and hit pull news</p>";
                $this->print_row('Country', 'countries_callback', '', 'required');
                $this->print_row('Source', 'sources_callback', '', 'required');
                $this->print_row('Category', 'categories_callback', '', 'required');
                $this->print_row('Api', 'api_types_callback', '', 'required');
                $this->submit_button("Pull News", "button-secondary", "pull-news");
                echo "</div>";              
                break;
            case "schedule":?>
                <div class="text-left">
                    <h3>Please select country or source and your schedule frequency to schedule pulling news</h3>
                </div>
                <?php
                $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
                if(!isset($settings['api_key'])) {
                    echo "<p>Please provide your API Key in the Settings tab and save to continue here.</p>";
                    return;
                }
                echo "<div class='col-md-5 pull-news-box'>";
                echo "<p class='heading'>Select country or source and hit Schedule</p>";
                $timestamp = wp_next_scheduled('_newsapi_puller_pull_news_hook');
                $timestamp_ai = wp_next_scheduled('_newsapi_puller_pull_news_ai_hook');
                $timestamp_io = wp_next_scheduled('_newsapi_puller_pull_newsdata_io_hook');
                $pull_news_hook_started = get_option('pull_news_hook_started');
                $pull_news_hook_done = get_option('pull_news_hook_done');
                $pull_news_ai_hook_started = get_option('pull_news_ai_hook_started');
                $pull_news_ai_hook_done = get_option('pull_news_ai_hook_done');
                $pull_newsdata_io_hook_started = get_option('pull_newsdata_io_hook_started');
                $pull_newsdata_io_hook_done = get_option('pull_newsdata_io_hook_done');
                $iso_date        = date( 'Y-m-d H:i:s', $timestamp ); 
                $iso_date_ai     = date( 'Y-m-d H:i:s', $timestamp_ai ); 
                $iso_date_io     = date( 'Y-m-d H:i:s', $timestamp_io );
                if($timestamp)
                {
                    echo "<p class='schedule text-green'>NewsApi</p>";
                    echo "<p class='schedule text-green'>Nex schedule will run at $iso_date</p>";
                    echo "<p class='schedule text-green'>$pull_news_hook_started</p>";
                    echo "<p class='schedule text-green'>$pull_news_hook_done</p>";
                }
                if($timestamp_ai) {
                    echo "<p class='schedule text-green'>NewsApi.AI</p>";
                    echo "<p class='schedule text-green'>Nex schedule will run at $iso_date_ai</p>";
                    echo "<p class='schedule text-green'>$pull_news_ai_hook_started</p>";
                    echo "<p class='schedule text-green'>$pull_news_ai_hook_done</p>";
                }
                if($timestamp_io) {
                    echo "<p class='schedule text-green'>NewsData.IO</p>";
                    echo "<p class='schedule text-green'>Nex schedule will run at $iso_date_io</p>";
                    echo "<p class='schedule text-green'>$pull_newsdata_io_hook_started</p>";
                    echo "<p class='schedule text-green'>$pull_newsdata_io_hook_done</p>";
                }
                $this->print_row('Country', 'countries_callback', '', 'required');
                $this->print_row('Source', 'sources_callback', '', 'required');
                $this->print_row('Frequency', 'frequencies_callback', '', 'required');
                $this->print_row('Api', 'api_types_callback', '', 'required');
                $this->submit_button("Schedule", "button-secondary", "schedule-news");
                echo "<div class='clear'></div>";
                $this->submit_button("Unschedule", "button-secondary", "unschedule-news");
                echo "</div>";
                break;
            case "settings": ?>
                <div class="text-left">
                    <h3>Enter Your api settings</h3>
                </div>
                <?php $this->print_row('Api Key', 'api_key_callback', '', 'required');
                $this->print_row('News.ai Api Key', 'news_ai_api_key_callback', '', 'required');
                $this->print_row('NewsData.io Api Key', 'newsdata_io_api_key_callback', '', 'required');
                $this->submit_button("Save");
                break;
        }     
    }

    private function print_row($title, $method, $style='', $required = '')
    { 
        printf('
        <div class="row" style="%s" id="%s">
            <div class="form-group">
                <label class="control-label %s">%s</label>',
                $style, str_replace(" ", "-", strtolower($title))."-row", $required, $title);
                $this->{$method}();
        printf('</div>
        </div>');
    }

    private function submit_button($value = 'Save Changes', $class = "button-primary", $id = "submit")
    {
        ?>
        <div class="row">
            <div class="form-group">
                <label class="control-label submit"></label>
                <input type="submit" name="submit" id="<?php echo $id; ?>" class="button <?php echo $class; ?>" value="<?php echo $value; ?>">
            </div>
        </div>
        <?php 
    } 
    

    /** 
     * Get the settings option array and print one of its values
     */
    private function api_json_textarea_callback()
    { 
        printf(
            '<textarea cols="200" rows="25" class="form-control" required="required" id="api_json_textarea" name="%s[api_json_textarea]"></textarea>
            <span class="error-hint hidden">Json input is required.</span>
            <span class="error-hint error-json-hint hidden">Please make sure you provide a valid json input.</span>', 
            WP_NEWSAPI_PULLER_SETTINGS_GROUP
        );
    }

    private function api_key_callback()
    { 
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        $value = "";
        if(isset($settings["api_key"])) 
        {
            $value = $settings["api_key"];
        }
        printf(
            '<input type="text" class="form-control mb-10" value="%s" required="required" id="api_key" name="%s[api_key]"/>
            <span class="error-hint hidden">Api key is required.</span>',
            $value,
            WP_NEWSAPI_PULLER_SETTINGS_GROUP
        );
    }
    
    private function news_ai_api_key_callback()
    { 
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        $value = "";
        if(isset($settings["news_ai_api_key"])) 
        {
            $value = $settings["news_ai_api_key"];
        }
        printf(
            '<input type="text" class="form-control mb-10" value="%s" required="required" id="news_ai_api_key" name="%s[news_ai_api_key]"/>
            <span class="error-hint hidden">Api key is required.</span>',
            $value,
            WP_NEWSAPI_PULLER_SETTINGS_GROUP
        );
    }
    private function newsdata_io_api_key_callback()
    { 
        $settings = get_option(WP_NEWSAPI_PULLER_SETTINGS_GROUP);
        $value = "";
        if(isset($settings["newsdata_io_api_key"])) 
        {
            $value = $settings["newsdata_io_api_key"];
        }
        printf(
            '<input type="text" class="form-control mb-10" value="%s" required="required" id="newsdata_io_api_key" name="%s[newsdata_io_api_key]"/>
            <span class="error-hint hidden">Api key is required.</span>',
            $value,
            WP_NEWSAPI_PULLER_SETTINGS_GROUP
        );
    }
    private function countries_callback()
    { 
        $available_countries = $this->available_countries();
        $options = $this->build_country_options($available_countries);
        printf( 
            '<select class="form-control" required="required" id="country" name="country">%s</select>
             <div class="error-hint hidden">Country is required.</div>', 
             implode('', $options)
        );
    }

    private function frequencies_callback()
    { 
        $frequencies = $this->available_frequencies();
        $options = $this->build_frequency_options($frequencies);
        printf( 
            '<select class="form-control" id="frequency" name="frequency">%s</select>
             <div class="error-hint hidden">You must specify a frequency.</div>', 
             implode('', $options)
        );
    }
    
    private function api_types_callback()
    { 
        $types = $this->available_api_types();
        $options = $this->build_api_options($types);
        printf( 
            '<select class="form-control" id="api_type" name="api_type">%s</select>
             <div class="error-hint hidden">You must specify the API type.</div>', 
             implode('', $options)
        );
    }

    private function sources_callback()
    { 
        $sources = $this->available_sources();

        $options = $this->build_sources_options($sources);
        printf( 
            '<select class="form-control" id="source" name="source">%s</select>
             <div class="error-hint hidden">You must specify a source if country is not selected.</div>', 
             implode('', $options)
        );
    }

    private function categories_callback()
    { 
        $cats = $this->available_categories();
        $options = $this->build_cat_options($cats);
        printf( 
            '<select class="form-control" id="category" name="category">%s</select>
             <div class="error-hint hidden"></div>', 
             implode('', $options)
        );
    }

    private function available_countries()
    {
        $allowed_countries = explode(" ", "ae ar at au be bg br ca ch cn co cu cz de eg fr gb gr hk hu id ie il in it jp kr lt lv ma mx my ng nl no nz ph pl pt ro rs ru sa se sg si sk th tr tw ua us ve za");
        $all_countries = file_get_contents(WP_NEWSAPI_PULLER_PLUGIN_DIR."countries.json");
        $all_countries = json_decode($all_countries);
        return array_filter($all_countries, function($c) use ($allowed_countries) {
            return in_array(strtolower($c->code), $allowed_countries);
        }); 
    }

    private function available_sources()
    { 
        $sources = file_get_contents(WP_NEWSAPI_PULLER_PLUGIN_DIR."sources.json");
        $sources = json_decode($sources);
        return array_filter($sources->sources, function($s) {
            return $s->language == "en";
        }); 
    }

    private function available_frequencies()
    { 
        return [
            ".5"=> "Every 30 minutes",
            "1" => "Every 1 hour",
            "2" => "Every 2 hours",
            "3" => "Every 3 hours",
            "6" => "Every 6 hours",
            "9" => "Every 9 hours",
            "12" => "Every 12 hours",
        ];
    }
    
    private function available_api_types()
    { 
        return [
            "1"=> "News API",
            "2" => "News API.AI",
            "3" => "NewsData.io"
        ];
    }

    private function available_categories(){
        $cats = "business entertainment environment food health politics science sports technology top tourism world";
        $cats = explode(" ", $cats);
        $options = [];
        foreach($cats as $c) {
            $options[$c] = ucfirst($c);
        }
        return $options;
    }

    private function build_country_options($countries)
    {
        return $this->build_options($countries, 'code', 'name');
    }

    private function build_sources_options($sources)
    {
         return $this->build_options($sources, 'id', 'name');
    }

    private function build_frequency_options($frequencies)
    {
        return $this->build_options($frequencies);
    }
    private function build_api_options($apis)
    {
        return $this->build_options($apis);
    }
    private function build_cat_options($cats)
    {
        return $this->build_options($cats);
    }

    private function build_options($data, $k = '' , $v = '') 
    {
        $options[] ="<option value=''>Please Select</option>";

        foreach ($data as $key => $row)  
        { 
            if(empty($k)) 
            { 
                $options[] = "<option value='{$key}'>{$row}</option>";
            }
            else
            {
                $options[] = "<option value='{$row->{$k}}'>{$row->{$v}}</option>";
            }
        }

        return $options;
    }
}