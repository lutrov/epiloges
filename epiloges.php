<?php

/*
Plugin Name: Epiloges
Description: Optimises the Wordpress <em>options</em> table by allowing you to change plugin options so that they don't autoload and by allowing you to delete any orphaned options left behind by plugins and theme frameworks. Why this plugin name? Epiloges means "options" in Greek.
Author: Ivan Lutrov
Version: 1.8
Author URI: http://lutrov.com
*/

defined('ABSPATH') || die('Ahem.');

//
// This plugin is only while in the admin dashboard.
//
if (is_admin() == false) {
	return;
}

//
// Set max execution time to 3 minutes.
//
ini_set('max_execution_time', 180);

//
// Get recursive list of PHP files in specified directory.
//
function epiloges_recursive_directory($folder, &$array) {
	$handle = opendir($folder);
	while ($file = readdir($handle)) {
		if ($file !== '.' && $file !== '..') {
			$path = $folder . '/' . $file;
			if (is_dir($path)) {
				epiloges_recursive_directory($path, $array);
			} else {
				if (substr($file, -4) === '.php') {
					$array[] = $path;
				}
			}
		}
	}
	closedir($handle);
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
		$handle = fopen($path, 'r');
		$filesize = filesize($path);
		if ($filesize > 0) {
			$contents = fread($handle, $filesize);
			preg_match_all('#get_option\((.+)\)#', $contents, $matches);
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
		fclose($handle);
	}
	return $options;
}

//
// Convert bytes to human friendly file size.
//
function epiloges_human_friendly_size($value, $precision = 1) {
	$result = null;
	if (($value / 1024) < 1) {
		$result = $value . 'B';
	} elseif (($value / 1024 / 1024) < 1) {
		$result = number_format($value / 1024, $precision) . 'K';
	} else {
		$result = number_format($value / 1024 / 1024, $precision) . 'M';
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
	printf("<div id=\"epiloges\" class=\"wrap\">\n");
	printf("<h1>Epiloges</h1>\n");
	printf("<p>Epiloges is installed and working correctly. <a href=\"#\" id=\"epiloges-help-toggle\">Help</a></p>\n");
	printf("<div id=\"epiloges-help\">\n");
	printf("<p>In Wordpress, some options are loaded whenever Wordpress loads a page. These are marked as autoload options. This is done to speed up Wordpress and prevent the programs from hitting the database every time some plugin needs to look up an option. Automatic loading of options at start-up makes Wordpress fast, but it can also use up memory for options that will seldom or never be used.</p>\n");
	printf("<p>You can safely switch options so that they don't load automatically. Probably the worst thing that will happen is that the page will paint a little slower because the option is retrieved separately from other options. The best thing that can happen is there is a lower demand on memory because the unused options are not loaded when Wordpress starts loading a page.</p>\n");
	printf("<p>When plugins are uninstalled they are supposed to clean up their options. Many options do not do any clean-up during uninstall. It is quite possible that you have many orphan options from plugins that you deleted long ago. These are autoloaded on every page, slowing down your pages and eating up memory. These options can be safely marked so that they will not autoload. If you are sure they are not needed you can delete them.</p>\n");
	printf("<p>You can change the autoload settings or delete an option on the form below. Be aware that you can break some plugins by deleting their options. I do not show most of the built-in options used by Wordpress. The list below should be just plugin options.</p>\n");
	printf("<p>It is far safer to change the autoload option value to \"no\" than to delete an option. Only delete an option if you are sure that it is from an uninstalled plugin. If you find your pages slowing down, turn the autoload option back to \"on\".</p>\n");
	printf("<p>In order to see if the change in autoload makes any difference, you can view the source of your blog pages and look for an html comment that shows your current memory usage and the load time and number of queries for the page. This is added to the footer by this plugin. It is a HTML comment so you have to view the page source to see it.</p>\n");
	printf("<p>Options names are determined by the plugin author. Some are obvious, but some make no sense. You maye have to do a little detective work to figure out where an option came from. Deactivate this plugin when you are not using it in order to save memory and speed up your site loading time.</p>\n");
	printf("</div>\n");
	$ptab = $wpdb->options;
	if (array_key_exists('epiloges_token', $_POST)) {
		$nonce = $_POST['epiloges_token'];
		if (strlen($nonce) > 0 && wp_verify_nonce($nonce, 'epiloges_token')) {
			if (array_key_exists('epiloges_autoload', $_POST)) {
				$autoload = $_POST['epiloges_autoload'];
				printf("<ul>");
				foreach ($autoload as $name) {
					$au = substr($name, 0, strpos($name,'_'));
					$name = substr($name, strpos($name,'_') + 1);
					printf("<li>Changing <em>%s</em> autoload to <em>%s</em></li>", $name, $au);
					$sql = "UPDATE $ptab SET autoload='$au' WHERE option_name='$name'";
					$wpdb->query($sql);
				}
				printf("</ul>\n");
			}
			if (array_key_exists('epiloges_delete', $_POST)) {
				$delete = $_POST['epiloges_delete'];
				printf("<ul>");
				foreach ($delete as $name) {
					printf("<li>Deleting <em>%s</em></li>", $name);
					$sql = "DELETE FROM $ptab WHERE option_name='$name'";
					$wpdb->query($sql);
				}
				printf("</ul>\n");
			}
		} else {
			die('Failed nonce security check.');
		}
	}
	$nonce = wp_create_nonce('epiloges_token');
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
	$ptab = $wpdb->options;
	$orderby = 'autoload, option_name';
	if (array_key_exists('orderby', $_GET)) {
		switch ($_GET['orderby']) {
			case 'name':
				$orderby = 'option_name';
				break;
			case 'size':
				$orderby = 'size, option_name';
				break;
			case 'autoload':
				$orderby = 'autoload, option_name';
				break;
		}
	}
	$sql = "SELECT option_id, option_name, option_value, autoload, LENGTH(option_value) AS size FROM $ptab ORDER BY $orderby ASC";
	$results = $wpdb->get_results($sql, ARRAY_A);
	$rows = array();
	foreach ($results as $row) {
		$uop = true;
		if (in_array($row['option_name'], $system_options) == false) {
			foreach ($system_options as $op) {
				if (strpos($row['option_name'], $op) !== false) {
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
	printf("<p><strong>Options marked in <span class=\"red\">red</span> have been identified as currently used by an installed plugin or theme and cannot be deleted.</strong></p>\n");
	printf("<form method=\"post\" name=\"epiloges\" action=\"\">\n");
	printf("<p>There were %s options found.</p>\n", $count);
	printf("<table class=\"widefat\">\n");
	printf("<thead>\n");
	printf("<tr><th scope=\"col\" class=\"manage-column\"><a href=\"%s&orderby=name\" title=\"Sort on this column\">Option</a></th><th scope=\"col\" class=\"manage-column num\"><a href=\"%s&orderby=size\" title=\"Sort on this column\">Size</a></th><th scope=\"col\" class=\"manage-column num\"><a href=\"%s&orderby=autoload\" title=\"Sort on this column\">Autoload</th><th scope=\"col\" class=\"manage-column num\">Change Autoload</th><th scope=\"col\" class=\"manage-column num\">Delete</th></tr>\n", strtok($_SERVER['REQUEST_URI'], '&'),  strtok($_SERVER['REQUEST_URI'], '&'), strtok($_SERVER['REQUEST_URI'], '&'));
	printf("</thead>\n");
	printf("<tbody>\n");
	$abspath = str_replace('\\', '/', ABSPATH);
	$class = 'alternate';
	$prefixes = array();
	$used = null;
	foreach ($rows as $row) {
		$option = $row['option_name'];
		$id = strtok($option, '_');
		$value = htmlentities($row['option_value']);
		$autoload = $row['autoload'];
		$size = epiloges_human_friendly_size($row['size']);
		$path = isset($plugin_options[$option]) ? $plugin_options[$option] : null;
		$au = $autoload == 'yes' ? 'no' : 'yes';
		printf("<tr class=\"%s\">", $class);
		if (strlen($path) > 0) {
			$path = $plugin_options[$option];
			$used = $id;
		}
		if (strlen($path) > 0 || substr($option, 0, strlen($id) + 1) == $used . '_') {
			printf("<td><strong class=\"red\" title=\"%s\">%s</strong></td>", str_replace($abspath, '/', $path), $option);
		} else {
			printf("<td><a href=\"http://google.com/search?q=%s+wordpress&pws=0\" title=\"Google lookup\" target=\"_blank\">%s</a></td>", $option, $option);
			if (strpos($option, '_')) {
				$prefix = strtok($option, '_');
				if (isset($prefixes[$prefix])) {
					$prefixes[$prefix]++;
				} else {
					$prefixes[$prefix] = 1;
				}
			}
		}
		printf("<td class=\"num\"><span title=\"%s\">%s</span></td>", $value, $size);
		printf("<td class=\"num\">%s</td>", $autoload);
		printf("<td class=\"\"><span class=\"change\"><input type=\"checkbox\" value=\"%s_%s\" name=\"epiloges_autoload[]\">&nbsp;%s</span></td>", $au, $option, $au);
		if (strlen($path) > 0 || substr($option, 0, strlen($id) + 1) == $used . '_') {
			printf("<td class=\"num\">&nbsp;</td>");
		} else {
			printf("<td class=\"num\"><input type=\"checkbox\" value=\"%s\" name=\"epiloges_delete[]\"></td>", $option);
		}
		printf("</tr>\n");
		$class = strlen($class) > 0 ? null : 'alternate';
	}
	printf("</tbody>\n");
	printf("</table>\n");
	printf("<p class=\"submit\"><input class=\"button-primary\" value=\"Save Changes\" type=\"submit\" onclick=\"return confirm('Are you sure? There is no undo for this.');\"></p>\n");
	printf("<input type=\"hidden\" name=\"epiloges_token\" value=\"%s\">\n", $nonce);
	printf("</form>\n");
	$info = null;
	$c = 0;
	foreach ($prefixes as $key => $value) {
		if ($value > 1) {
			$info = sprintf("%s\nDELETE FROM %s WHERE option_name LIKE '%s_%%';\n", $info, $ptab, $key);
			$c++;
		}
	}
	$info = trim($info);
	printf("<p class=\"usage\">Current memory usage is %s, which peaked at %s.%s</p>", epiloges_human_friendly_size(memory_get_usage()), epiloges_human_friendly_size(memory_get_peak_usage()), $c > 0 ? ' <a href="#" id="epiloges-debug-toggle">Debug Info</a>' : null);
	if (strlen($info) > 0) {
		printf('<div id="epiloges-debug"><p><em>It may be quicker to do a wildcard delete of these %s prefixes using your favourite database tool instead:</em></p><pre>%s</pre></div>', $c, $info);
	}
	printf("</div>\n");
}


//
// Add custom stylesheet to admin header.
//
function epiloges_custom_stylesheet() {
	if (strpos($_SERVER['QUERY_STRING'], 'page=epiloges') !== false) {
		printf("<style>\n");
		printf("#epiloges span.red, #epiloges strong.red {color:#c00}\n");
		printf("#epiloges table th a, #epiloges table td a {color:#222;text-decoration:none}\n");
		printf("#epiloges table th a {margin: 0 5px}\n");
		printf("#epiloges table th a:hover, #epiloges table td a:hover {text-decoration:underline}\n");
		printf("#epiloges table td span.change {padding-left:40%%}\n");
		printf("#epiloges-help-toggle {padding-left:6px;font-size:90%%;text-transform:uppercase}\n");
		printf("#epiloges-help {display:none}\n");
		printf("#epiloges-debug-toggle {padding-left:6px;font-size:90%%;text-transform:uppercase}\n");
		printf("#epiloges-debug {background:#fff;border:1px dotted #999;padding:5px 15px;margin:20px 0;line-height:1;display:none}\n");
		printf("#epiloges-debug pre {padding:0 40px}\n");
		printf("#epiloges p.usage {padding-top:10px}\n");
		printf("</style>\n");
	}
}
add_filter('admin_head', 'epiloges_custom_stylesheet', 1);

//
// Add plugin to dashboard tools menu.
//
function epiloges_init() {
	add_management_page('Epiloges', 'Epiloges', 'manage_options', 'epiloges', 'epiloges_process');
}
add_filter('admin_menu', 'epiloges_init');

//
// Add help show/hide toggle to admin footer.
//
function epiloges_admin_jquery() {
	printf("<script type=\"text/javascript\">\n");
	printf("jQuery(document).ready(function(){jQuery('#epiloges-help-toggle').click(function(e){e.preventDefault();if(jQuery('#epiloges-help').is(':hidden')){jQuery('#epiloges-help').show();}else{jQuery('#epiloges-help').hide();}});});\n");
	printf("jQuery(document).ready(function(){jQuery('#epiloges-debug-toggle').click(function(e){e.preventDefault();if(jQuery('#epiloges-debug').is(':hidden')){jQuery('#epiloges-debug').show();}else{jQuery('#epiloges-debug').hide();}});});\n");
	printf("</script>\n");
}
add_filter('admin_footer', 'epiloges_admin_jquery');

?>