<?php
/*
Plugin Name: YouTube Favorite Video Posts
Plugin URI: http://www.jeremyfelt.com/wordpress/plugins/youtube-favorite-video-posts
Description: Checks your YouTube favorite videos RSS feed and creates new posts in a custom post type.
Version: 1.0
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

class Youtube_Favorite_Video_Posts_Foghlaim {

	public function __construct() {
		/*  Things happen when we activate and deactivate the plugin of course. */
		register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate_plugin' ) );
		/*  Make a pretty link for settings under the plugin information. */
		add_filter( 'plugin_action_links', array( $this, 'add_plugin_action_links' ), 10, 2 );
		/*  Add a fancy icon to the custom post type page in wp-admin. */
		add_action( 'admin_head', array( $this, 'edit_admin_icon' ) );
		/*  Add our custom settings to the admin menu. */
		add_action( 'admin_menu', array( $this, 'add_settings' ) );
		/*  Register all of the custom settings. */
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		/*  Of course, the custom post type needs to be there for everyone, so we call this outside of the admin check. */
		add_action( 'init', array( $this, 'create_content_type' ) );
		/*  And we want to make sure the cron tasks are there for everyone as well. */
		add_action( 'jf_yfvp_hourly_action', array( $this, 'process_feed' ) );
	}

	public function activate_plugin(){
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
		$this->create_content_type();
		/*  Flush the rewrite rules so that the new custom post type works. */
		flush_rewrite_rules( false );
		/*  Schedule the first CRON event to happen 60 seconds from now, then hourly after that. */
		wp_schedule_event( ( time() + 60 ) , 'hourly', array( $this, 'process_feed' ) );
		/*  TODO: Make frequency a configurable option. */
	}

	public function deactivate_plugin(){
		/*  If the plugin is deactivated, we want to clear our hourly CRON to keep things clean. */
		wp_clear_scheduled_hook( 'jf_yfvp_hourly_action' );
	}

	public function add_plugin_action_links( $links, $file ){
		/*  Function gratefully taken (and barely modified) from Pippin Williamson's
			WPMods article: http://www.wpmods.com/adding-plugin-action-links/ */
		static $this_plugin;

		if ( ! $this_plugin )
			$this_plugin = plugin_basename( __FILE__ );

		if ( $file == $this_plugin ){
			$settings_path = '/wp-admin/options-general.php?page=youtube-favorite-video-posts-settings';
			$settings_link = '<a href="' . get_bloginfo( 'wpurl' ) . $settings_path . '">' . __( 'Settings', 'youtube-favorite-video-posts' ) . '</a>';
			array_unshift( $links, $settings_link );  // add the link to the list
		}
		return $links;
	}

	public function edit_admin_icon(){
		global $post_type;

		if ( 'jf_yfvp_youtube' == $post_type )
			echo '<style>#icon-edit { background: url("' . plugins_url( 'images/youtube-icon-32.png', __FILE__ ) . '") no-repeat; background-size: 32px 32px; }</style>';

	}

	public function add_settings(){
		/*  Add the sub-menu item under the Settings top-level menu. */
		add_options_page( __('YouTube Favorites', 'youtube-favorite-video-posts' ), __('YouTube Favorites', 'youtube-favorite-video-posts'), 'manage_options', 'youtube-favorite-video-posts-settings', array( $this, 'view_settings' ) );
	}

	public function view_settings(){

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

	public function register_settings(){
		/*  Register the settings we want available for this. */
		register_setting( 'jf_yfvp_options', 'jf_yfvp_options', array( $this, 'validate_options' ) );
		add_settings_section( 'jf_yfvp_section_main', '', array( $this, 'main_section_text' ), 'jf_yfvp' );
		add_settings_section( 'jf_yfvp_section_post_type', '', array( $this, 'post_type_section_text' ), 'jf_yfvp' );
		add_settings_section( 'jf_yfvp_section_interval', '', array( $this, 'interval_section_text' ), 'jf_yfvp' );
		add_settings_field( 'jf_yfvp_youtube_rss_feed', 'YouTube Username:', array( $this, 'youtube_user_text' ), 'jf_yfvp', 'jf_yfvp_section_main' );
		add_settings_field( 'jf_yfvp_embed_width', 'Default Embed Width:', array( $this, 'embed_width_text' ), 'jf_yfvp', 'jf_yfvp_section_main' );
		add_settings_field( 'jf_yfvp_embed_height', 'Default Embed Height:', array( $this, 'embed_height_text' ), 'jf_yfvp', 'jf_yfvp_section_main' );
		add_settings_field( 'jf_yfvp_max_fetch_items', 'Max Items To Fetch:', array( $this, 'max_fetch_items_text' ), 'jf_yfvp', 'jf_yfvp_section_main' );
		add_settings_field( 'jf_yfvp_post_type', 'Post Type:', array( $this, 'post_type_selection_text' ), 'jf_yfvp', 'jf_yfvp_section_post_type' );
		add_settings_field( 'jf_yfvp_post_status', __( 'Default Post Status:', 'youtube-favorite-video-posts' ) , array( $this, 'post_status_selection_text' ), 'jf_yfvp', 'jf_yfvp_section_post_type' );
		add_settings_field( 'jf_yfvp_fetch_interval', 'Feed Fetch Interval: ', array( $this, 'fetch_interval_selection_text' ), 'jf_yfvp', 'jf_yfvp_section_interval' );
	}

	public function main_section_text() {
		/*  Placeholder for later. Nothing really needed at the moment. */
	}

	public function post_type_section_text() {
		?>
		<h3>Custom Or Default Post Type</h3>
		<p style="margin-left:12px;max-width: 640px;">A new custom post type that adds an 'youtube' slug to new items has been added
			and selected by default. You can change this to any other available post type if you would like.</p>
		<?php
	}

	public function interval_section_text() {
		$next_scheduled_time = wp_next_scheduled( 'jf_yfvp_hourly_action' );
		$time_till_cron = human_time_diff( time(), $next_scheduled_time );
		$user_next_cron = date( 'H:i:sA', $next_scheduled_time + ( get_option( 'gmt_offset' ) * 3600 ) );
		?>
		<h3>RSS Fetch Frequency</h3>
		<p style="margin-left:12px; max-width: 630px;">This plugin currently depends on WP Cron operating fully as expected. In most
			cases, you should be able to select one of the intervals below and things will work as expected. If not, please let
			<a href="http://www.jeremyfelt.com">me</a> know. By default, we check for new items on an hourly basis.</p>
		<p style="margin-left:12px; max-width: 630px;">The next check of your Youtube favorites is scheduled to run at <?php echo $user_next_cron; ?>, which occurs in <?php echo $time_till_cron; ?></p>
		<?php
	}

	public function embed_width_text() {
		$jf_yfvp_options = get_option( 'jf_yfvp_options', array() );

		if ( ! isset( $jf_yfvp_options[ 'embed_width' ] ) )
			$jf_yfvp_options[ 'embed_width' ] = 330;

		?>
		<input style="width: 100px;" type="text" id="jf_yfvp_embed_width" name="jf_yfvp_options[embed_width]" value="<?php echo esc_attr( $jf_yfvp_options[ 'embed_width' ] ); ?>" />
		<?php
	}

	public function embed_height_text() {
		$jf_yfvp_options = get_option( 'jf_yfvp_options', array() );

		if ( ! isset( $jf_yfvp_options[ 'embed_height' ] ) )
			$jf_yfvp_options[ 'embed_height' ] = 270;

		?>
		<input style="width: 100px;" type="text" id="jf_yfvp_embed_height" name="jf_yfvp_options[embed_height]" value="<?php echo esc_attr( $jf_yfvp_options[ 'embed_height' ] ); ?>" />
		<?php
	}

	public function youtube_user_text() {
		$jf_yfvp_options = get_option( 'jf_yfvp_options', array() );

		if ( ! isset( $jf_yfvp_options[ 'youtube_rss_feed' ] ) )
			$jf_yfvp_options[ 'youtube_rss_feed' ] = '';
		?>
		<input style="width: 200px;" type="text" id="jf_yfvp_youtube_rss_feed" name="jf_yfvp_options[youtube_rss_feed]" value="<?php echo esc_attr( $jf_yfvp_options[ 'youtube_rss_feed' ] ); ?>" />
		<?php
	}

	public function post_type_selection_text() {
		$jf_yfvp_options = get_option( 'jf_yfvp_options', array() );

		if ( ! isset( $jf_yfvp_options[ 'post_type' ] ) )
			$jf_yfvp_options[ 'post_type' ] = 'jf_yfvp_youtube';

		$post_types = array( 'post', 'link' );
		$all_post_types = get_post_types( array( '_builtin' => false ) );

		foreach( $all_post_types as $p => $k ) {
			$post_types[] = $p;
		}

		echo '<select id="jf_yfvp_post_type" name="jf_yfvp_options[post_type]">';

		foreach( $post_types as $pt ){
			echo '<option value="' . esc_attr( $pt ) . '" ' . selected( $jf_yfvp_options[ 'post_type' ], $pt, false ) . '>' . esc_html( $pt ) . '</option>';
		}

		echo '</select>';
	}

	public function post_status_selection_text() {
		$jf_yfvp_options = get_option( 'jf_yfvp_options', array() );

		if ( ! isset( $jf_yfvp_options[ 'post_status' ] ) )
			$jf_yfvp_options[ 'post_status' ] = 'publish';

		echo '<select id="jf_yfvp_post_status" name="jf_yfvp_options[post_status]">
            <option value="draft" '   . selected( $jf_yfvp_options[ 'post_status' ], 'draft', false )   . '>draft</option>
            <option value="publish" ' . selected( $jf_yfvp_options[ 'post_status' ], 'publish', false ) . '>publish</option>
            <option value="private" ' . selected( $jf_yfvp_options[ 'post_status' ], 'private', false ) . '>private</option>
          </select>';
	}

	public function fetch_interval_selection_text() {
		/* TODO: Custom intervals can be added to a WordPress install, so we should query those and offer as an option. */
		$intervals = array( 'hourly', 'twicedaily', 'daily' );

		$jf_yfvp_options = get_option( 'jf_yfvp_options', array() );

		if ( ! isset( $jf_yfvp_options[ 'fetch_interval' ] ) )
			$jf_yfvp_options[ 'fetch_interval' ] = 'hourly';

		echo '<select id="jf_yfvp_fetch_interval" name="jf_yfvp_options[fetch_interval]">';

		foreach( $intervals as $i ){
			echo '<option value="' . esc_attr( $i ) . '" ' . selected( $jf_yfvp_options[ 'fetch_interval' ], $i, false ) . '>' . esc_html( $i ) . '</option>';
		}

		echo '</select>';
	}

	public function max_fetch_items_text() {
		$jf_yfvp_options = get_option( 'jf_yfvp_options', array() );

		if ( ! isset( $jf_yfvp_options[ 'max_fetch_items' ] ) )
			$jf_yfvp_options[ 'max_fetch_items' ] = 5;
		?>
		<input type="text" id="jf_yfvp_max_fetch_items" name="jf_yfvp_options[max_fetch_items]" value="<?php echo esc_attr( $jf_yfvp_options[ 'max_fetch_items' ] ); ?>" />
		<?php
	}

	public function validate_options( $input ) {
		/*  Validation of a drop down. Hmm. Well, if it isn't on our list, we'll force it onto our list. */
		$valid_post_status_options = array( 'draft', 'publish', 'private' );
		$valid_fetch_interval_options = array( 'hourly', 'twicedaily', 'daily' );

		$valid_post_type_options = array( 'post', 'link' );
		$all_post_types = get_post_types( array( '_builtin' => false ) );
		foreach( $all_post_types as $p=>$k ){
			$valid_post_type_options[] = $p;
		}

		if( ! in_array( $input[ 'post_status' ], $valid_post_status_options ) )
			$input[ 'post_status' ] = 'publish';

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

	public function create_content_type() {
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

	public function modify_simplepie_cache_lifetime() {
		/* The default SimplePie cache lifetime is 12 hours. We really do want to update more
		 * frequently, so we'll make it 30 seconds during our update. */
		return 30;
	}

	public function process_feed() {
		/*  Grab the configured YouTube favorites RSS feed and create new posts based on that. */

		/*  Go get some options! */
		$youtube_options = get_option( 'jf_yfvp_options', array() );

		/* No username, no feed. No feed, no work. */
		if ( empty( $youtube_options[ 'youtube_rss_feed' ] ) )
			return;

		/*  The feed URL we'll be grabbing. */
		$youtube_feed_url = 'http://gdata.youtube.com/feeds/base/users/' . esc_attr( $youtube_options[ 'youtube_rss_feed' ] ) . '/favorites?alt=rss';
		/*  The post type we'll be saving as. We designed it to be custom, but why not allow anything. */

		if ( isset( $youtube_options[ 'post_type' ] ) )
			$post_type = $youtube_options[ 'post_type' ];
		else
			$post_type = 'jf_yfvp_youtube';

		/*  The post status we'll use. */
		if ( isset( $youtube_options[ 'post_status' ] ) )
			$post_status = $youtube_options[ 'post_status' ];
		else
			$post_status = 'publish';

		if ( isset( $youtube_options[ 'max_fetch_items' ] ) )
			$max_fetch_items = absint( $youtube_options[ 'max_fetch_items' ] );
		else
			$max_fetch_items = 5;

		/*  Now fetch with the WordPress SimplePie function. */
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'modify_simplepie_cache_lifetime' ) );
		$youtube_feed = fetch_feed( $youtube_feed_url );
		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'modify_simplepie_cache_lifetime' ) );

		if ( ! is_wp_error( $youtube_feed ) ){
			/*  Feed looks like a good object, continue. */

			$max_items = $youtube_feed->get_item_quantity( $max_fetch_items );
			$youtube_items = $youtube_feed->get_items( 0, $max_items );
			foreach( $youtube_items as $item ){
				$video_token = substr( $item->get_id(), 43 );

				$video_embed_code = '<iframe width=\"' . absint( $youtube_options[ 'embed_width' ] ) .
					'\" height=\"' . absint( $youtube_options[ 'embed_height' ] ) .
					'\" src=\"http://www.youtube.com/embed/' .
					esc_attr( $video_token ) . '\" frameborder=\"0\" allowfullscreen></iframe>';

				/*  We're disabling the kses filters below, so we need to clean up the title as YouTube allows " and the like. */
				$item_title = esc_html( $item->get_title() );

				/*  Create a hash of the video token to store as post meta in order to check for unique content
				 * if a video of the same title is stored one day. */
				$item_hash = md5( $video_token );

				if ( get_page_by_title( $item_title, 'OBJECT', $post_type ) ){
					/*  Title already exists. */
					$existing_hash = get_post_meta( get_page_by_title( $item_title, 'OBJECT', $post_type )->ID, 'jf_yfvp_hash', true );

					if ( $item_hash == $existing_hash )
						$skip = 1;
					else
						$skip = NULL;
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
						'filter' => true,
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
}

/* Fire away */
new Youtube_Favorite_Video_Posts_Foghlaim();