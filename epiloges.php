<?php

/*
Plugin Name: Epiloges
Description: Optimises the Wordpress <em>options</em> table by allowing you to change plugin options so that they don't autoload and by allowing you to delete any orphaned options left behind by plugins and theme frameworks. Why this plugin name? Epiloges means "options" in Greek.
Plugin URI: https://github.com/lutrov/epiloges
Version: 2.3
Author: Ivan Lutrov
Author URI: http://lutrov.com
Copyright: 2018, Ivan Lutrov

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc., 51 Franklin
Street, Fifth Floor, Boston, MA 02110-1301, USA. Also add information on how to
contact you by electronic and paper mail.
*/

defined('ABSPATH') || die('Ahem.');

//
// Set max execution time to 3 minutes.
//
ini_set('max_execution_time', 180);

//
// Get recursive list of PHP files in specified directory.
//
function epiloges_recursive_directory($folder, &$array) {
	$fp = opendir($folder);
	while ($file = readdir($fp)) {
		if ($file <> '.' && $file <> '..') {
			$path = sprintf('%s/%s', $folder, $file);
			if (is_dir($path)) {
				epiloges_recursive_directory($path, $array);
			} else {
				if (substr($file, -4) === '.php') {
					$array[] = $path;
				}
			}
		}
	}
	closedir($fp);
	return $array;
}

//
// Get keys for all installed plugins and themes by searching the source code.
//
function epiloges_plugin_options() {
	$list = array();
	$options = array();
	$path = str_replace('\\', '/', dirname(dirname(dirname(__FILE__))));
	$list = epiloges_recursive_directory($path, $list);
	foreach ($list as $path) {
		if (($fp = fopen($path, 'r'))) {
			$filesize = filesize($path);
			if ($filesize > 0) {
				$content = fread($fp, $filesize);
				$matches = array();
				preg_match_all('#get_option\((.+)\)#', $content, $matches);
				if (isset($matches[1])) {
					foreach ($matches[1] as $value) {
						$value = strtok($value, ')');
						$value = strtok($value, ',');
						$value = trim($value);
						if (substr($value, 0, 1) == "'") {
							$value = substr(substr($value, 1), 0, -1);
						}
						$options[$value] = $path;
					}
				}
			}
			fclose($fp);
		}
	}
	return $options;
}

//
// Convert bytes to human friendly file size.
//
function epiloges_human_friendly_size($value, $precision = 1) {
	$result = null;
	if (($value / 1024) < 1) {
		$result = sprintf('%sB', $value);
	} elseif (($value / 1024 / 1024) < 1) {
		$result = sprintf('%sK', number_format($value / 1024, $precision));
	} else {
		$result = sprintf('%sM', number_format($value / 1024 / 1024, $precision));
	}
	return $result;
}

//
// Screen and main processing.
//
function epiloges_process() {
	global $wpdb;
	if (current_user_can('manage_options') == false) {
		die('Access denied.');
	}
	echo sprintf("<div id=\"epiloges\" class=\"wrap\">\n");
	echo sprintf("<h1>Epiloges</h1>\n");
	echo sprintf("<p>Epiloges Optimises the Wordpress <em>options</em> table by allowing you to change plugin options so that they don't autoload and by allowing you to delete any orphaned options left behind by plugins and theme frameworks. <a href=\"#\" id=\"epiloges-help-toggle\">Help</a></p>\n");
	echo sprintf("<div id=\"epiloges-help\" style=\"display:none\">\n");
	echo sprintf("<p>In Wordpress, some options are loaded whenever Wordpress loads a page. These are marked as autoload options. This is done to speed up Wordpress and prevent the programs from hitting the database every time some plugin needs to look up an option. Automatic loading of options at start-up makes Wordpress fast, but it can also use up memory for options that will seldom or never be used.</p>\n");
	echo sprintf("<p>You can safely switch options so that they don't load automatically. Probably the worst thing that will happen is that the page will render a little slower because the option is retrieved separately from other options. The best thing that can happen is there is a lower demand on memory because the unused options are not loaded when Wordpress starts loading a page.</p>\n");
	echo sprintf("<p>When plugins are uninstalled they are supposed to clean up their options. Many options do not do any clean-up during uninstall. It is quite possible that you have many orphan options from plugins that you deleted long ago. These are autoloaded on every page, slowing down your pages and eating up memory. These options can be safely marked so that they will not autoload. If you are sure they are not needed you can delete them.</p>\n");
	echo sprintf("<p>You can change the autoload settings or delete an option on the form below. Be aware that you can break some plugins by deleting their options. I do not show most of the built-in options used by Wordpress. The list below should be just plugin options.</p>\n");
	echo sprintf("<p>It is far safer to change the autoload option value to \"no\" than to delete an option. Only delete an option if you are sure that it is from an uninstalled plugin. If you find your pages slowing down, turn the autoload option back to \"on\".</p>\n");
	echo sprintf("<p>In order to see if the change in autoload makes any difference, you can view the source of your blog pages and look for an html comment that shows your current memory usage and the load time and number of queries for the page. This is added to the footer by this plugin. It is a HTML comment so you have to view the page source to see it.</p>\n");
	echo sprintf("<p>Options names are determined by the plugin author. Some are obvious, but some make no sense. You maye have to do a little detective work to figure out where an option came from. Deactivate this plugin when you are not using it in order to save memory and speed up your site loading time.</p>\n");
	echo sprintf("</div>\n");
	$nonce = null;
	if (array_key_exists('epiloges_nonce', $_POST)) {
		$nonce = $_POST['epiloges_nonce'];
	}
	if (strlen($nonce) > 0 && wp_verify_nonce($nonce, 'epiloges_static_update')) {
		if (array_key_exists('epiloges_autoload', $_POST)) {
			$autoload = $_POST['epiloges_autoload'];
			echo sprintf("<ul>");
			foreach ($autoload as $name) {
				$au = substr($name, 0, strpos($name,'_'));
				$name = substr($name, strpos($name,'_') + 1);
				echo sprintf("<li>Changing <em>%s</em> autoload to <em>%s</em></li>", $name, $au);
				$query = sprintf("UPDATE %s SET autoload = '%s' WHERE option_name = '%s'", $wpdb->options, $au, $name);
				$wpdb->query($query);
			}
			echo sprintf("</ul>\n");
		}
		if (array_key_exists('epiloges_delete', $_POST)) {
			$delete = $_POST['epiloges_delete'];
			echo sprintf("<ul>");
			foreach ($delete as $name) {
				echo sprintf("<li>Deleting <em>%s</em></li>", $name);
				$query = sprintf("DELETE FROM %s WHERE option_name = '%s'", $wpdb->options, $name);
				$wpdb->query($query);
			}
			echo sprintf("</ul>\n");
		}

	}
	$system_options = array(
		'_transient_',
		'active_plugins',
		'admin_email',
		'advanced_edit',
		'avatar_default',
		'avatar_rating',
		'blacklist_keys',
		'blog_charset',
		'blog_public',
		'blogdescription',
		'blogname',
		'can_compress_scripts',
		'category_base',
		'close_comments_days_old',
		'close_comments_for_old_posts',
		'comment_max_links',
		'comment_moderation',
		'comment_order',
		'comment_registration',
		'comment_whitelist',
		'comments_notify',
		'comments_per_page',
		'cron',
		'current_theme',
		'dashboard_widget_options',
		'date_format',
		'db_version',
		'default_category',
		'default_comment_status',
		'default_comments_page',
		'default_email_category',
		'default_link_category',
		'default_ping_status',
		'default_pingback_flag',
		'default_post_edit_rows',
		'default_post_format',
		'default_role',
		'embed_autourls',
		'embed_size_h',
		'embed_size_w',
		'enable_app',
		'enable_xmlrpc',
		'fileupload_url',
		'ftp_credentials',
		'gmt_offset',
		'gzipcompression',
		'hack_file',
		'home',
		'ht_user_roles',
		'html_type',
		'image_default_align',
		'image_default_link_type',
		'image_default_size',
		'initial_db_version',
		'large_size_h',
		'large_size_w',
		'link_manager_enabled',
		'links_recently_updated_append',
		'links_recently_updated_prepend',
		'links_recently_updated_time',
		'links_updated_date_format',
		'mailserver_login',
		'mailserver_pass',
		'mailserver_port',
		'mailserver_url',
		'medium_size_h',
		'medium_size_w',
		'moderation_keys',
		'moderation_notify',
		'page_comments',
		'page_for_posts',
		'page_on_front',
		'permalink_structure',
		'ping_sites',
		'posts_per_page',
		'posts_per_rss',
		'recently_edited',
		'require_name_email',
		'rss_use_excerpt',
		'show_avatars',
		'show_on_front',
		'sidebars_widgets',
		'siteurl',
		'start_of_week',
		'sticky_posts',
		'stylesheet',
		'tag_base',
		'template',
		'theme_mods_harptab',
		'theme_mods_twentyeleven',
		'theme_switched',
		'thread_comments',
		'thread_comments_depth',
		'thumbnail_crop',
		'thumbnail_size_h',
		'thumbnail_size_w',
		'time_format',
		'timezone_string',
		'uninstall_plugins',
		'upload_path',
		'upload_url_path',
		'uploads_use_yearmonth_folders',
		'use_balanceTags',
		'use_smilies',
		'use_trackback',
		'users_can_register',
		'widget_archives',
		'widget_categories',
		'widget_meta',
		'widget_recent-comments',
		'widget_recent-posts',
		'widget_rss',
		'widget_search',
		'widget_text',
		/* Some we added because changing caused problems */
		'_user_roles',
		'akismet_available_servers',
		'akismet_connectivity_time',
		'akismet_discard_month',
		'akismet_spam_count',
		'category_children',
		'db_upgraded',
		'logged_in_key',
		'logged_in_salt',
		'nav_menu_options',
		'nonce_key',
		'nonce_salt',
		'recently_activated',
		'rewrite_rules',
		'theme_mods_',
		'widget_',
		'wordpress_api_key',
	);
	$plugin_options = epiloges_plugin_options();
	$query = sprintf("SELECT option_id, option_name, option_value, autoload, LENGTH(option_value) AS size FROM %s ORDER BY option_name ASC", $wpdb->options);
	$results = $wpdb->get_results($query, ARRAY_A);
	$rows = array();
	foreach ($results as $row) {
		$uop = true;
		if (in_array($row['option_name'], $system_options) == false) {
			foreach ($system_options as $op) {
				if (strpos($row['option_name'], $op) <> false) {
					$uop = false;
					break;
				}
			}
		} else {
			$uop = false;
		}
		if ($uop) {
			$rows[] = $row;
		}
	}
	$count = count($rows);
	$nonce = wp_create_nonce('epiloges_static_update');
	echo sprintf("<p><strong>Options marked in <span class=\"red\">red</span> have been identified as currently used by an installed plugin or theme and cannot be deleted.</strong></p>\n");
	echo sprintf("<form method=\"post\" name=\"epiloges\" action=\"\">\n");
	echo sprintf("<input type=\"hidden\" name=\"epiloges_nonce\" value=\"%s\">\n", $nonce);
	echo sprintf("<p>There were %s options found.</p>\n", $count);
	echo sprintf("<table class=\"widefat\">\n");
	echo sprintf("<thead>\n");
	echo sprintf("<tr><th scope=\"col\" class=\"manage-column\" style=\"width:60%%;text-align:left\">Option</th><th scope=\"col\" class=\"manage-column\" style=\"width:10%%;text-align:right\">Size</th><th scope=\"col\" class=\"manage-column\" style=\"width:10%%;text-align:left\">Autoload</th><th scope=\"col\" class=\"manage-column\" style=\"width:15%%;text-align:left\">Change Autoload</th><th scope=\"col\" class=\"manage-column\" style=\"width:5%%;text-align:right\">Delete</th></tr>\n", strtok($_SERVER['REQUEST_URI'], '&'),  strtok($_SERVER['REQUEST_URI'], '&'), strtok($_SERVER['REQUEST_URI'], '&'));
	echo sprintf("</thead>\n");
	echo sprintf("<tbody>\n");
	$abspath = str_replace('\\', '/', ABSPATH);
	$class = 'alternate';
	$used = null;
	foreach ($rows as $row) {
		$option = $row['option_name'];
		$id = strtok($option, '_');
		$value = htmlentities($row['option_value']);
		$autoload = $row['autoload'];
		$size = epiloges_human_friendly_size($row['size']);
		$path = isset($plugin_options[$option]) ? $plugin_options[$option] : null;
		$au = $autoload == 'yes' ? 'no' : 'yes';
		echo sprintf("<tr class=\"%s\">", $class);
		if (strlen($path) > 0) {
			$path = $plugin_options[$option];
			$used = $id;
		}
		if (strlen($path) > 0 || substr($option, 0, strlen($id) + 1) == ($used . '_')) {
			echo sprintf("<td><strong class=\"red\" title=\"%s\">%s</strong></td>", str_replace($abspath, '/', $path), $option);
		} else {
			echo sprintf("<td><a href=\"http://google.com/search?q=%s+wordpress&pws=0\" title=\"Google lookup\" target=\"_blank\">%s</a></td>", $option, $option);
		}
		echo sprintf("<td style=\"text-align:right\"><span title=\"%s\">%s</span></td>", $value, $size);
		echo sprintf("<td style=\"text-align:left\">%s</td>", $autoload);
		echo sprintf("<td style=\"text-align:left\"><span class=\"change\"><input type=\"checkbox\" value=\"%s_%s\" name=\"epiloges_autoload[]\">%s</span></td>", $au, $option, $au);
		if (strlen($path) > 0 || substr($option, 0, strlen($id) + 1) == ($used . '_')) {
			echo sprintf("<td></td>");
		} else {
			echo sprintf("<td style=\"text-align:right\"><input type=\"checkbox\" value=\"%s\" name=\" epiloges_delete[]\"></td>", $option);
		}
		echo sprintf("</tr>\n");
		$class = strlen($class) > 0 ? null : 'alternate';
	}
	echo sprintf("</tbody>\n");
	echo sprintf("</table>\n");
	echo sprintf("<p>Current memory usage is %s, which peaked at %s.</p>", epiloges_human_friendly_size(memory_get_usage()), epiloges_human_friendly_size(memory_get_peak_usage()));
	echo sprintf("<p class=\"submit\"><input class=\"button-primary\" value=\"Save Changes\" type=\"submit\" onclick=\"return confirm('Are you sure? There is no undo for this.');\"></p>\n");
	echo sprintf("</form>\n");
	echo sprintf("</div>\n");
}

//
// Add custom stylesheet to admin header.
//
add_filter('admin_head', 'epiloges_custom_stylesheet', 1);
function epiloges_custom_stylesheet() {
	if (strpos($_SERVER['REQUEST_URI'], 'page=epiloges') <> false) {
		echo sprintf("<style>\n");
		echo sprintf("#epiloges-help-toggle {padding-left:6px;font-size:85%%;text-transform:uppercase}\n");
		echo sprintf("#epiloges span.red, #epiloges strong.red {color:#c00}\n");
		echo sprintf("#epiloges table th a, #epiloges table td a {color:#222;text-decoration:none}\n");
		echo sprintf("#epiloges table th a:hover, #epiloges table td a:hover {text-decoration:underline}\n");
		echo sprintf("</style>\n");
	}
}

//
// Add plugin to dashboard tools menu.
//
add_filter('admin_menu', 'epiloges_init');
function epiloges_init() {
	add_management_page('Epiloges', 'Epiloges', 'manage_options', 'epiloges', 'epiloges_process');
}

//
// Add help show/hide toggle to admin footer.
//
add_filter('admin_footer', 'epiloges_admin_jquery');
function epiloges_admin_jquery() {
	echo sprintf("<script type=\"text/javascript\">\n");
	echo sprintf("jQuery(document).ready(function(){jQuery('#epiloges-help-toggle').click(function(e){e.preventDefault();if(jQuery('#epiloges-help').is(':hidden')){jQuery('#epiloges-help').show();}else{jQuery('#epiloges-help').hide();}});});\n");
	echo sprintf("</script>\n");
}

?>
