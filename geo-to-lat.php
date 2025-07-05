<?php
/*
Plugin Name: Geo to Lat
Plugin URI: http://wordpress.org/plugins/geo-to-lat/
Description: Converts Georgian characters in post, term slugs and media file names to Latin characters. Based on the original plugin by Sergey Biryukov.
Author: Dimitri Gogelia
Author URI: http://gogelia.ge/
Version: 1.0.9
*/ 

function ctl_sanitize_title($title) {
	global $wpdb;

	$geo2lat = array(
		'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v',
		'ზ' => 'z', 'თ' => 'th', 'ი' => 'i', 'კ' => 'k', 'ლ' => 'l', 'მ' => 'm',
		'ნ' => 'n', 'ო' => 'o', 'პ' => 'p','ჟ' => 'zh','რ' => 'r','ს' => 's',
		'ტ' => 't','უ' => 'u','ფ' => 'ph','ქ' => 'q','ღ' => 'gh','ყ' => 'qh',
		'შ' => 'sh','ჩ' => 'ch','ც' => 'ts','ძ' => 'dz','წ' => 'ts','ჭ' => 'tch',
		'ხ' => 'kh','ჯ' => 'j','ჰ' => 'h'
	);

	$is_term = false;
	$backtrace = debug_backtrace();
	foreach ( $backtrace as $backtrace_entry ) {
		if ( $backtrace_entry['function'] == 'wp_insert_term' ) {
			$is_term = true;
			break;
		}
	}

	$term = $is_term ? $wpdb->get_var("SELECT slug FROM {$wpdb->terms} WHERE name = '$title'") : '';
	if ( empty($term) ) {
		$title = strtr($title, apply_filters('ctl_table', $geo2lat));
		if (function_exists('iconv')){
			$title = iconv('UTF-8', 'UTF-8//TRANSLIT//IGNORE', $title);
		}
		$title = preg_replace("/[^A-Za-z0-9'_\-\.]/", '-', $title);
		$title = preg_replace('/\-+/', '-', $title);
		$title = preg_replace('/^-+/', '', $title);
		$title = preg_replace('/-+$/', '', $title);
	} else {
		$title = $term;
	}

	return $title;
}
add_filter('sanitize_title', 'ctl_sanitize_title', 9);
add_filter('sanitize_file_name', 'ctl_sanitize_title');

function ctl_convert_existing_slugs() {
	global $wpdb;

	$posts = $wpdb->get_results("SELECT ID, post_name FROM {$wpdb->posts} WHERE post_name REGEXP('[^A-Za-z0-9\-]+') AND post_status IN ('publish', 'future', 'private')");
	foreach ( (array) $posts as $post ) {
		$sanitized_name = ctl_sanitize_title(urldecode($post->post_name));
		if ( $post->post_name != $sanitized_name ) {
			add_post_meta($post->ID, '_wp_old_slug', $post->post_name);
			$wpdb->update($wpdb->posts, array( 'post_name' => $sanitized_name ), array( 'ID' => $post->ID ));
		}
	}

	$terms = $wpdb->get_results("SELECT term_id, slug FROM {$wpdb->terms} WHERE slug REGEXP('[^A-Za-z0-9\-]+') ");
	foreach ( (array) $terms as $term ) {
		$sanitized_slug = ctl_sanitize_title(urldecode($term->slug));
		if ( $term->slug != $sanitized_slug ) {
			$wpdb->update($wpdb->terms, array( 'slug' => $sanitized_slug ), array( 'term_id' => $term->term_id ));
		}
	}
}

function ctl_schedule_conversion() {
	add_action('shutdown', 'ctl_convert_existing_slugs');
}
register_activation_hook(__FILE__, 'ctl_schedule_conversion');
