=== YouTube Favorite Video Posts ===

Contributors: jeremyfelt
Donate link: http://www.jeremyfelt.com/wordpress/plugins/youtube-favorite-video-posts/
Tags: youtube, custom post type, embed, video, rss, feed
Requires at least: 3.2.1
Tested up to: 3.3
Stable tag: 0.2

YouTube Favorite Video Posts grabs videos you mark as favorites in YouTube and adds them to WordPress under a custom post type.

== Description ==

YouTube Favorite Video Posts works in the background to grab videos you mark as favorites in YouTube. The feed is parsed into
new posts in WordPress and videos are automatically embedded in the content of those posts.

Once this data is available, you can add support to your theme to display your latest favorite videos for your readers.

Settings are available for:

* YouTube Username
    * The most important. From this the plugin will determine the RSS feed to use for your favorites.
* Embed Width & Embed Height
    * These values are applied to the embedded iframe code that is inserted into your post content.
* Max Items To Fetch
    * If you aren't a regular YouTube favoriter, you may want to reduce this so that your server doesn't process the same items over and over again.
* Post Type
    * By default a new custom post type for YouTube favorites has been added. You can change this to any of your other custom post types.
* Default Post Status
    * Choose to publish the new posts immediately, or save them as drafts for later processing.
* Feed Fetch Interval
    * Defaults to hourly, but can be changed to either daily or twice daily.

== Installation ==

1. Upload 'youtube-favorite-video-posts' to your plugin directory, usually 'wp-content/plugins/', or install automatically via your WordPress admin page.
1. Activate YouTube Favorite Video Posts in your plugin menu.
1. Add your YouTube username and change other options using the YouTube Videos menu under Settings in your admin page. (*See Screenshot*)

That's it!

== Frequently Asked Questions ==

= Why aren't there any FAQs? =

*  Because nobody has asked a question yet.

== Screenshots ==

1. An overview of the YouTube Favorite Video Posts settings screen.

== Changelog ==
= 0.2 =

* Video titles with double quotes were not saving correctly. Added appropriate escaping.
* General code cleanup.

= 0.1 =

* In which a plugin begins its life.

== Upgrade Notice ==
= 0.1 =

* Initial installation.