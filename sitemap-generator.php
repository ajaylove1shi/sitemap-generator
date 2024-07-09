<?php
/*
Plugin Name: Sitemap Generator
Plugin URI: https://sitemap-generator.com/
Description: Generate a complete and customizable sitemap for your website to improve SEO and enhance site navigation. This plugin creates an XML sitemap that helps search engines like Google, Bing, and Yahoo index your site more effectively. With an easy-to-use interface, you can include or exclude specific URLs, prioritize your content, and ensure all critical pages are discovered by search engines.
Version: 1.0.0
Requires at least: 5.8
Requires PHP: 8+
Author: Ajay Lowanshi
Author URI: https://ajaylove1shi.com/wordpress-plugins/
License: MIT
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

/**
 * Activate sitemap generator plugin hook.
 */
register_activation_hook( __FILE__, 'sitemap_generator_plugin_activation' );

/**
 * Activate sitemap generator plugin callbacks.
 */
function sitemap_generator_plugin_activation() {

    global $wpdb;

    $table_name = $wpdb->prefix . 'sitemap_generator_data';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        sitemap_generator_url varchar(255) NOT NULL,
        sitemap_generator_type varchar(50) NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

}

/**
 * Deactivate sitemap generator plugin hook.
 */
register_deactivation_hook( __FILE__, 'sitemap_generator_plugin_deactivation' );

/**
 * Deactivate sitemap generator plugin callbacks.
 */
function sitemap_generator_plugin_deactivation() {

    global $wpdb;

    // Removing sitemap shortcode
    remove_shortcode('sitemap');

    // Deleting sitemap.xml file
    $sitemap_file = ABSPATH . "sitemap.xml";
    if (file_exists($sitemap_file)) {
        unlink($sitemap_file);
    }

    // Dropping the sitemap_generator_data table
    $table_name = $wpdb->prefix . 'sitemap_generator_data';
    $sql = "DROP TABLE IF EXISTS $table_name;";
    $wpdb->query($sql);

}

/**
 * Create form, insert data, and create sitemap_generator.xml file.
 */
function sitemap_generator_plugin_form_shortcode( $atts = [], $content = null, $tag = '' ) {
    /**
     * Normalize attribute keys, lowercase.
     */
    $atts = array_change_key_case( (array) $atts, CASE_LOWER );

    /**
     * Override default attributes with user attributes.
     */
    $sitemap_generator_atts = shortcode_atts(
        array(
            'title' => 'sitemap-generator.org',
        ), $atts, $tag
    );

    /***
     * Check if request is post.
     */
    if (isset($_POST['sitemap_generator_submit'])) {
        global $wpdb;

        /**
         * Get the table name.
         */
        $table_sitemap_generator = $wpdb->prefix . 'sitemap_generator_data';

        $sitemap_generator_url = $_POST['sitemap_generator_url'];
        $sitemap_generator_type = $_POST['sitemap_generator_type'];

        $data = array(
            'sitemap_generator_url'   => $sitemap_generator_url,
            'sitemap_generator_type' => $sitemap_generator_type,
        );
        $format = array( '%s', '%s' );

        /**
         * Insert data into table.
         */
        $wpdb->insert( $table_sitemap_generator, $data, $format );

        /**
         * Fetch data from table.
         */
        $sitemap_generator_results = $wpdb->get_results( "SELECT * FROM {$table_sitemap_generator} WHERE sitemap_generator_type = '$sitemap_generator_type'", OBJECT );

        /**
         * Create sitemap_generator.xml file.
         */
        sitemap_generator_plugin_create_sitemap_generator($sitemap_generator_results);
    }

    /**
     * Create HTML Form.
     */
    $o = '<div class="sitemap-generator-box">';
    $o .= '<h2>' . esc_html( $sitemap_generator_atts['title'] ) . '</h2>';
    $o .= '<form method="post">';
    $o .= '<label for="url">URL:</label> ';
    $o .= '<input type="url" name="sitemap_generator_url" placeholder="Please enter sitemap generator URL." required><br><br>';
    $o .= '<label for="url">Sitemap Generator:</label>';
    $o .= '<input type="radio" id="sitemap_generator_type" name="sitemap_generator_type" value="HTML" required>';
    $o .= '<label for="html">HTML</label>';
    $o .= '<input type="radio" id="sitemap_generator_type" name="sitemap_generator_type" value="XML" required>';
    $o .= '<label for="xml">XML</label><br><br>';
    $o .= '<input type="submit" name="sitemap_generator_submit">';
    $o .= '</form>';

    /**
     * Enclosing tags...
     */
    if ( ! is_null( $content ) ) {
        /**
         * $content here holds everything in between the opening and the closing tags of your shortcode. e.g. [my-shortcode]content[/my-shortcode].
         * Depending on what your shortcode supports, you will parse and append the content to your output in different ways.
         * In this example, we just secure output by executing the_content filter hook on $content.
         */
        $o .= apply_filters( 'the_content', $content );
    }
    $o .= '</div>';

    /**
     * Return output/html.
     */
    return $o;
}

/**
 * Create shortcode for HTML Form.
 */
add_action( 'init', 'sitemap_generator_plugin_form_shortcode_init' );
function sitemap_generator_plugin_form_shortcode_init() {
    add_shortcode('sitemap_generator', 'sitemap_generator_plugin_form_shortcode');
}

/**
 * Create sitemap_generator.xml file in root directory of site.
 */
function sitemap_generator_plugin_create_sitemap_generator($sitemap_generator_results) {

    if ( str_replace( '-', '', get_option( 'gmt_offset' ) ) < 10 ) {
        $tempo = '-0' . str_replace( '-', '', get_option( 'gmt_offset' ) );
    } else {
        $tempo = get_option( 'gmt_offset' );
    }

    if (strlen( $tempo ) == 3) { $tempo = $tempo . ':00'; }

    $sitemap_generator = '<?xml version="1.0" encoding="UTF-8"?>' . '<?xml-stylesheet type="text/xsl" href="' .
    esc_url( home_url( '/' ) ) . 'sitemap.xsl"?>';

    $sitemap_generator .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $sitemap_generator .=  '<urlset xmlns="http://www.sitemap.org/schemas/sitemap/0.9">' . "\n";

    // Query for pages
    // This section adds all the pages to the sitemap_generator
    $page_args = array(
        'post_type' => 'page',
        'posts_per_page' => -1, // Retrieve all pages
    );
    $page_query = new WP_Query($page_args);

    if ($page_query->have_posts()) {
        while ($page_query->have_posts()) {
            $page_query->the_post();
            $sitemap_generator .=  '<url>' . "\n";
            $sitemap_generator .=  '<loc>' . esc_url(get_permalink()) . '</loc>' . "\n";
            $sitemap_generator .=  '<lastmod>' . get_the_modified_date('c') . '</lastmod>' . "\n";
            $sitemap_generator .=  '<changefreq>monthly</changefreq>' . "\n";
            $sitemap_generator .=  '<priority>0.6</priority>' . "\n";
            $sitemap_generator .=  '</url>' . "\n";
        }
    }

    // Reset post data
    wp_reset_postdata();

    // Query for posts
    // This section adds all the blog posts to the sitemap generator
    $post_args = array(
        'post_type' => 'post',
        'posts_per_page' => -1, // Retrieve all posts
    );
    $post_query = new WP_Query($post_args);

    if ($post_query->have_posts()) {
        while ($post_query->have_posts()) {
            $post_query->the_post();
            $sitemap_generator .=  '<url>' . "\n";
            $sitemap_generator .=  '<loc>' . esc_url(get_permalink()) . '</loc>' . "\n";
            $sitemap_generator .=  '<lastmod>' . get_the_modified_date('c') . '</lastmod>' . "\n";
            $sitemap_generator .=  '<changefreq>weekly</changefreq>' . "\n";
            $sitemap_generator .=  '<priority>0.5</priority>' . "\n";
            $sitemap_generator .=  '</url>' . "\n";
        }
    }

    // Reset post data
    wp_reset_postdata();

    foreach( $sitemap_generator_results as $sitemap_generator_result ) {
        $lastmod = explode( " ", $sitemap_generator_result->created_at );
        $sitemap_generator .=  '<url>' . "\n";
        $sitemap_generator .=  '<loc>' . $sitemap_generator_result->url . '</loc>' . "\n";
        $sitemap_generator .=  '<lastmod>' . $lastmod[0] . 'T' . $lastmod[1] . $tempo . '</lastmod>' . "\n";
        $sitemap_generator .=  '<changefreq>weekly</changefreq>' . "\n";
        $sitemap_generator .=  '<priority>0.5</priority>' . "\n";
        $sitemap_generator .=  '</url>' . "\n";
    }

    $sitemap_generator .= '</urlset>';

    $fp = fopen( ABSPATH . "sitemap.xml", 'w' );

    fwrite( $fp, $sitemap_generator );

    fclose( $fp );
}
