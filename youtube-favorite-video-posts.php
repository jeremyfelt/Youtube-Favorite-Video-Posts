<?php
/*
Plugin Name: YouTube Favorite Video Posts
Plugin URI: http://www.jeremyfelt.com/wordpress/plugins/youtube-favorite-video-posts
Description: Checks your YouTube favorite videos RSS feed and creates new posts in a custom post type.
Version: 0.1
Author: Jeremy Felt
Author URI: http://www.jeremyfelt.com
License: GPL2
*/

/*  Copyright 2011 Jeremy Felt (email: jeremy.felt@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*  jf_yfvp */

/*  Things happen when we activate and deactivate the plugin of course. */
register_activation_hook( __FILE__, 'jf_yfvp_plugin_activation' );
register_deactivation_hook( __FILE__, 'jf_yfvp_plugin_deactivation' );

/*  The stuff we only want to do as an admin. */
if ( is_admin() ){
    /*  Add a fancy icon to the custom post type page in wp-admin. */
    add_action( 'admin_head', 'jf_yfvp_edit_icon' );
    /*  Add our custom settings to the admin menu. */
    add_action( 'admin_menu', 'jf_yfvp_add_settings' );
    /*  Register all of the custom settings. */
    add_action( 'admin_init', 'jf_yfvp_register_settings' );
    /*  Make a pretty link for settings under the plugin information. */
    add_filter( 'plugin_action_links', 'jf_yfvp_plugin_action_links', 10, 2);
}

/*  Of course, the custom post type needs to be there for everyone, so we call this outside of the admin check. */
add_action( 'init', 'jf_yfvp_create_youtube_type' );

/*  And we want to make sure the cron tasks are there for everyone as well. */
add_action( 'jf_yfvp_hourly_action', 'jf_yfvp_on_the_hour' );

function jf_yfvp_plugin_activation(){
    $current_jf_yfvp_options = get_option( 'jf_yfvp_options' );
    $jf_yfvp_options[ 'youtube_rss_feed' ] = $current_jf_yfvp_options[ 'youtube_rss_feed' ] ? $current_jf_yfvp_options[ 'youtube_rss_feed' ] : '';
    $jf_yfvp_options[ 'embed_width' ] = $current_jf_yfvp_options[ 'embed_width' ] ? $current_jf_yfvp_options[ 'embed_width' ] : 330;
    $jf_yfvp_options[ 'embed_height' ] = $current_jf_yfvp_options[ 'embed_height' ] ? $current_jf_yfvp_options[ 'embed_height' ] : 270;
    $jf_yfvp_options[ 'post_type' ] = $current_jf_yfvp_options[ 'post_type' ] ? $current_jf_yfvp_options[ 'post_type' ] : 'jf_yfvp_youtube';
    $jf_yfvp_options[ 'post_status' ] = $current_jf_yfvp_options[ 'post_status' ] ? $current_jf_yfvp_options[ 'post_status' ] : 'publish';
    $jf_yfvp_options[ 'fetch_interval' ] = $current_jf_yfvp_options[ 'fetch_interval' ] ? $current_jf_yfvp_options[ 'fetch_interval' ] : 'hourly';
    $jf_yfvp_options[ 'max_fetch_items' ] = $current_jf_yfvp_options[ 'max_fetch_items' ] ? $current_jf_yfvp_options[ 'max_fetch_items' ] : 5;
    add_option( 'jf_yfvp_options', $jf_yfvp_options );

    /*  Create the custom post type upon activation. */
    jf_yfvp_create_youtube_type();

    /*  Flush the rewrite rules so that the new custom post type works. */
    flush_rewrite_rules( false );

    /*  Schedule the first CRON even to happen 30 seconds from now, then hourly after that. */
    wp_schedule_event( ( time() + 30 ) , 'hourly', 'jf_yfvp_hourly_action' );
    /*  TODO: Make frequency a configurable option. */
}

function jf_yfvp_plugin_deactivation(){
    /*  If the plugin is deactivated, we want to clear our hourly CRON to keep things clean. */
    wp_clear_scheduled_hook( 'jf_yfvp_hourly_action' );
}

function jf_yfvp_plugin_action_links( $links, $file ){
    /*  Function gratefully taken (and barely modified) from Pippin Williamson's
        WPMods article: http://www.wpmods.com/adding-plugin-action-links/ */
    static $this_plugin;

    if ( ! $this_plugin ) {
        $this_plugin = plugin_basename( __FILE__ );
    }

    // check to make sure we are on the correct plugin
    if ( $file == $this_plugin ){
        $settings_path = '/wp-admin/options-general.php?page=youtube-favorite-video-posts-settings';
        $settings_link = '<a href="' . get_bloginfo( 'wpurl' ) . $settings_path . '">' . __( 'Settings', 'youtube-favorite-video-posts' ) . '</a>';
        array_unshift( $links, $settings_link );  // add the link to the list
    }

    return $links;
}

function jf_yfvp_edit_icon(){
    global $post_type;
    if ( 'jf_yfvp_youtube' == $post_type ) {
        echo '<style>#icon-edit { background: url("' . plugins_url( 'images/youtube-icon-32.png', __FILE__ ) . '") no-repeat;
                                  background-size: 32px 32px; }</style>';
    }
}

function jf_yfvp_add_settings(){
    /*  Add the sub-menu item under the Settings top-level menu. */
    add_options_page( __('YouTube Favorites', 'youtube-favorite-video-posts' ), __('YouTube Favorites', 'youtube-favorite-video-posts'), 'manage_options', 'youtube-favorite-video-posts-settings', 'jf_yfvp_view_settings' );
}

function jf_yfvp_view_settings(){

    /*  Display the main settings view for Youtube Favorite Video Posts. */
    echo '<div class="wrap">
        <div class="icon32" id="icon-options-general"></div>
            <h2>' . __( 'YouTube Favorite Video Posts', 'youtube-favorite-video-posts' ) . '</h2>
            <h3>' . __( 'Overview', 'youtube-favorite-video-posts' ) . ':</h3>
            <p style="margin-left:12px;max-width:640px;">
            ' . __( 'The settings below will help determine where to check for your favorite YouTube videos, how often to
            look for them, and how they should be stored once new items are found.', 'youtube-favorite-video-posts' ) . '</p>
            <p style="margin-left:12px;max-width:640px;">
                The most important part of this process will be to determine the RSS feed for your favorite YouTube videos. In order
                to do this, your username <strong>must</strong> be filled out below. This can usually be found in the upper right hand
                corner of <a href="http://www.youtube.com">YouTube.com</a>.
            </p>
            <ol style="margin-left:36px;">
                <li>Username must be filled in below. Email address will not work.</li>
                <li>The embed width and height settings will be applied to the iframe in your post content.</li>
            </ol>';

       echo '<form method="post" action="options.php">';

    settings_fields( 'jf_yfvp_options' );
    do_settings_sections( 'jf_yfvp' ); // Display the main section of settings.

    echo '<p class="submit"><input type="submit" class="button-primary" value="';
    _e( 'Save Changes', 'youtube-favorite-video-posts' );
    echo '" />
            </p>
            </form>
        </div>';
}

function jf_yfvp_register_settings(){
    /*  Register the settings we want available for this. */
    register_setting( 'jf_yfvp_options', 'jf_yfvp_options', 'jf_yfvp_options_validate' );
    add_settings_section( 'jf_yfvp_section_main', '', 'jf_yfvp_section_text', 'jf_yfvp' );
    add_settings_section( 'jf_yfvp_section_post_type', '', 'jf_yfvp_section_post_type_text', 'jf_yfvp' );
    add_settings_section( 'jf_yfvp_section_interval', '', 'jf_yfvp_section_interval_text', 'jf_yfvp' );
    add_settings_field( 'jf_yfvp_youtube_rss_feed', 'YouTube Username:', 'jf_yfvp_youtube_rss_feed_text', 'jf_yfvp', 'jf_yfvp_section_main' );
    add_settings_field( 'jf_yfvp_embed_width', 'Default Embed Width:', 'jf_yfvp_embed_width_text', 'jf_yfvp', 'jf_yfvp_section_main' );
    add_settings_field( 'jf_yfvp_embed_height', 'Default Embed Height:', 'jf_yfvp_embed_height_text', 'jf_yfvp', 'jf_yfvp_section_main' );
    add_settings_field( 'jf_yfvp_max_fetch_items', 'Max Items To Fetch:', 'jf_yfvp_max_fetch_items_text', 'jf_yfvp', 'jf_yfvp_section_main' );
    add_settings_field( 'jf_yfvp_post_type', 'Post Type:', 'jf_yfvp_post_type_text', 'jf_yfvp', 'jf_yfvp_section_post_type' );
    add_settings_field( 'jf_yfvp_post_status', __( 'Default Post Status:', 'youtube-favorite-video-posts' ) , 'jf_yfvp_post_status_text', 'jf_yfvp', 'jf_yfvp_section_post_type' );
    add_settings_field( 'jf_yfvp_fetch_interval', 'Feed Fetch Interval: ', 'jf_yfvp_fetch_interval_text', 'jf_yfvp', 'jf_yfvp_section_interval' );
}

function jf_yfvp_section_text(){
    /*  Placeholder for later. Nothing really needed at the moment. */
}

function jf_yfvp_section_post_type_text(){
    echo '<h3>Custom Or Default Post Type</h3>
    <p style="margin-left:12px;max-width: 640px;">A new custom post type that adds an \'youtube\' slug to new items has been added and selected by default.
    You can change this to any other available post type if you would like.</p>';
}

function jf_yfvp_section_interval_text(){
    echo '<h3>RSS Fetch Frequency</h3>
        <p style="margin-left:12px;max-width: 630px;">This plugin currently depends on WP Cron operating fully as expected. In most cases, you should
        be able to select one of the intervals below and things will work as expected. If not, please let <a href="http://www.jeremyfelt.com">me</a> know. By
        default, we check for new items on an hourly basis.</p>';
    $seconds_till_cron = wp_next_scheduled( 'jf_yfvp_hourly_action' ) - time();
    $user_next_cron = date( 'H:i:sA', wp_next_scheduled( 'jf_yfvp_hourly_action' ) + ( get_option( 'gmt_offset' ) * 3600 ) );
    echo '<p style="margin-left:12px;">The next check is scheduled to run at ' . $user_next_cron . ', which occurs in ' . $seconds_till_cron . ' seconds.</p>';
}

function jf_yfvp_embed_width_text(){
    $jf_yfvp_options = get_option( 'jf_yfvp_options' );
    echo '<input style="width: 100px;" type="text" id="jf_yfvp_embed_width"
                        name="jf_yfvp_options[embed_width]"
                        value="' . $jf_yfvp_options[ 'embed_width' ] . '">';
}

function jf_yfvp_embed_height_text(){
    $jf_yfvp_options = get_option( 'jf_yfvp_options' );
    echo '<input style="width: 100px;" type="text" id="jf_yfvp_embed_height"
                        name="jf_yfvp_options[embed_height]"
                        value="' . $jf_yfvp_options[ 'embed_height' ] . '">';
}

function jf_yfvp_youtube_rss_feed_text(){
    $jf_yfvp_options = get_option( 'jf_yfvp_options' );
    echo '<input style="width: 200px;" type="text" id="jf_yfvp_youtube_rss_feed"
                             name="jf_yfvp_options[youtube_rss_feed]"
                             value="' . $jf_yfvp_options[ 'youtube_rss_feed' ] . '">';
}

function jf_yfvp_post_type_text(){
    $jf_yfvp_options = get_option( 'jf_yfvp_options' );
    $post_types = array( 'post', 'link' );
    $all_post_types = get_post_types( array( '_builtin' => false ) );

    foreach( $all_post_types as $p=>$k ){
        $post_types[] = $p;
    }

    echo '<select id="jf_yfvp_post_type" name="jf_yfvp_options[post_type]">';

    foreach( $post_types as $pt ){
        echo '<option value="' . $pt . '"';

        if ( $pt == $jf_yfvp_options[ 'post_type' ] ) echo ' selected="yes" ';

        echo '>' . $pt . '</option>';
    }
}

function jf_yfvp_post_status_text(){
    $jf_yfvp_options = get_option( 'jf_yfvp_options' );

    /*  TODO: Definitely a better way to do this. See above function and do that. */
    $s1 = '';
    $s2 = '';
    $s3 = '';

    if( 'draft' == $jf_yfvp_options[ 'post_status' ] ){
        $s1 = 'selected="yes"';
    }elseif( 'publish' == $jf_yfvp_options[ 'post_status' ] ){
        $s2 = 'selected="yes"';
    }elseif( 'private' == $jf_yfvp_options[ 'post_status' ] ){
        $s3 = 'selected="yes"';
    }else{
        $s2 = 'selected="yes"';
    }

    echo '<select id="jf_yfvp_post_status" name="jf_yfvp_options[post_status]">
            <option value="draft" ' . $s1 . '>draft</option>
            <option value="publish" ' . $s2 . '>publish</option>
            <option value="private" ' . $s3 . '>private</option>
          </select>';
}

function jf_yfvp_fetch_interval_text(){
    /* TODO: Custom intervals can be added to a WordPress install, so we should query those and offer as an option. */
    $intervals = array( 'hourly', 'twicedaily', 'daily' );
    $jf_yfvp_options = get_option( 'jf_yfvp_options' );

    echo '<select id="jf_yfvp_fetch_interval" name="jf_yfvp_options[fetch_interval]">';

    foreach( $intervals as $i ){
        echo '<option value="' . $i . '" ';

        if( $i == $jf_yfvp_options[ 'fetch_interval' ] ) echo 'selected="yes"';

        echo '>' . $i . '</option>';
    }

    echo '</select>';
}

function jf_yfvp_max_fetch_items_text(){
    $jf_yfvp_options = get_option( 'jf_yfvp_options' );
    echo '<input type="text"
                 id="jf_yfvp_max_fetch_items"
                 name="jf_yfvp_options[max_fetch_items]"
                 value="' . $jf_yfvp_options[ 'max_fetch_items' ] . '">';
}

function jf_yfvp_options_validate( $input ) {
    /*  Validation of a drop down. Hmm. Well, if it isn't on our list, we'll force it onto our list. */
    $valid_post_status_options = array( 'draft', 'publish', 'private' );
    $valid_post_type_options = array( 'post', 'link' );
    $valid_fetch_interval_options = array( 'hourly', 'twicedaily', 'daily' );

    $all_post_types = get_post_types( array( '_builtin' => false ) );
    foreach( $all_post_types as $p=>$k ){
        $valid_post_type_options[] = $p;
    }

    if( ! in_array( $input[ 'post_status' ], $valid_post_status_options ) )
        $input[ 'post_status' ] = 'draft';

    if( ! in_array( $input[ 'post_type' ], $valid_post_type_options ) )
        $input[ 'post_type' ] = 'jf_yfvp_youtube';

    if( ! in_array( $input[ 'fetch_interval' ], $valid_fetch_interval_options ) )
        $input[ 'fetch_interval' ] = 'hourly';

    /*  This seems to be the only place we can reset the scheduled Cron if the frequency is changed, so here goes. */
    wp_clear_scheduled_hook( 'jf_yfvp_hourly_action' );
    wp_schedule_event( ( time() + 30 ) , $input[ 'fetch_interval' ], 'jf_yfvp_hourly_action' );

    $input[ 'max_fetch_items' ] = absint( $input[ 'max_fetch_items' ] );
    $input[ 'embed_width' ] = absint( $input[ 'embed_width' ] );
    $input[ 'embed_height' ] = absint( $input[ 'embed_height' ] );

    return $input;
}

function jf_yfvp_create_youtube_type(){
    /*  Add the custom post type 'jf_yfvp_youtube' to WordPress. */
    register_post_type( 'jf_yfvp_youtube',
        array(
            'labels' => array(
                'name' => __( 'YouTube' ),
                'singular_name' => __( 'YouTube Favorite' ),
                'all_items' => __( 'All YouTube Favorites' ),
                'add_new_item' => __( 'Add YouTube Favorite' ),
                'edit_item' => __( 'Edit YouTube Favorite' ),
                'new_item' => __( 'New YouTube Favorite' ),
                'view_item' => __( 'View YouTube Favorite' ),
                'search_items' => __( 'Search YouTube Favorites' ),
                'not_found' => __( 'No YouTube Favorites found' ),
                'not_found_in_trash' => __( 'No YouTube Favorites found in trash' ),
            ),
        'description' => 'YouTube posts created by the YouTube Favorite Video Posts plugin.',
        'public' => true,
        'menu_icon' => plugins_url( '/images/youtube-icon-16.png', __FILE__ ),
        'menu_position' => 5,
        'hierarchical' => false,
        'supports' => array (
            'title',
            'editor',
            'author',
            'custom-fields',
            'comments',
            'revisions',
            ),
        'has_archive' => true,
        'rewrite' => array(
            'slug' => 'youtube',
            'with_front' => false
            ),
        )
    );
}

function jf_yfvp_on_the_hour(){
    /*  Grab the configured Instapaper Liked RSS feed and create new posts based on that. */

    /*  Go get some options! */
    $youtube_options = get_option( 'jf_yfvp_options' );
    /*  The feed URL we'll be grabbing. */
    $youtube_feed_url = 'http://gdata.youtube.com/feeds/base/users/' . $youtube_options[ 'youtube_rss_feed' ] . '/favorites?alt=rss';
    /*  The post type we'll be saving as. We designed it to be custom, but why not allow anything. */
    $post_type = $youtube_options[ 'post_type' ];
    /*  The post status we'll use. */
    $post_status = $youtube_options[ 'post_status' ];
    /*  Now fetch with the WordPress SimplePie function. */
    $youtube_feed = fetch_feed( $youtube_feed_url );

    if ( ! is_wp_error( $youtube_feed ) ){
        /*  Feed looks like a good object, continue. */
        $max_items = $youtube_feed->get_item_quantity( absint( $youtube_options[ 'max_fetch_items' ] ) );
        $youtube_items = $youtube_feed->get_items(0, $max_items);
        foreach( $youtube_items as $item ){
            $video_token = substr( $item->get_id() ,43 );

            $video_embed_code = '<iframe width=\"' . $youtube_options[ 'embed_width' ] .
                                '\" height=\"' . $youtube_options[ 'embed_height' ] .
                                '\" src=\"http://www.youtube.com/embed/' .
                                $video_token . '\" frameborder=\"0\" allowfullscreen></iframe>';

            /*  We're disabling the kses filters below, so we need to clean up the title as YouTube allows " and the like. */
            $item_title = esc_html( $item->get_title() );

            $item_hash = md5( $video_token );

            if ( get_page_by_title( $item_title, 'OBJECT', $post_type ) ){
                /*  Title already exists. */
                $existing_hash = get_post_meta( get_page_by_title( $item_title, 'OBJECT', $post_type )->ID, 'jf_yfvp_hash', true );

                if ( $item_hash == $existing_hash ){
                    $skip = 1;
                }else{
                    $skip = NULL;
                }
            }else{
                $skip = NULL;
            }

            if ( ! $skip ){

                $youtube_post = array(
                    'post_title' => $item_title,
                    'post_content' => $video_embed_code,
                    'post_author' => 1,
                    'post_status' => $post_status,
                    'post_type' => $post_type,
                    'filter' => true
                );

                kses_remove_filters();
                $item_post_id = wp_insert_post( $youtube_post );
                kses_init_filters();
                add_post_meta( $item_post_id, 'jf_yfvp_hash', $item_hash, true );
            }
        }
    }else{
        /*  Uhhh, feels a little shady to die silently, but for now that's all we got. */
    }
}