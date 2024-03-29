<?php
/**
 * Plugin Name: WGOM Automatic Featured Image
 * Description: Automatically select a featured image for a post based on the post's category and tags.
 * Version: 1.0.0
 * Author: Sean Kelly
 * License: GPL2+
 */

defined('ABSPATH') or die("This file must be used with WordPress.");

class AutoFeaturedImage {
	public function __construct() {
		add_action('init', array($this, 'init'));
	}

	public function init() {
		// Facebook Auto Publish uses the transition_post_status action
		// with a priority of 10. Use a lower priority to go first.
		add_action('transition_post_status', array($this, 'transition_post'), 5, 3);
		add_action('publish_post', array($this, 'publish_post'));
	}

	public function transition_post($new_status, $old_status, $post) {
		if ($new_status !== 'publish') {
			return;
		}

		$postid = $post->ID;
		$this->publish_post($postid);
	}

	public function publish_post($postid) {
		// If the post already has a featured image set, don't do anything.
		if (has_post_thumbnail($postid)) {
			return;
		}

		// No thumbnail so search the media library for something.

		// Start with searching through the tags first. This is to
		// allow a post to use a more specific image. For both tags and
		// categories, sort the slugs and pick the first one that finds
		// an image.
		$the_tags = get_the_tags($postid);
		$tags = array();
		if (!empty($the_tags)) {
			foreach ($the_tags as $t) {
				$tags[] = $t->slug;
			}
		}

		$image_id = $this->check_slugs($postid, 'tag', $tags);
		if (is_int($image_id)) {
			return;
		}

		$the_cats = get_the_category($postid);
		$categories = array();
		foreach ($the_cats as $cat) {
			$categories[] = $cat->slug;
		}

		$image_id = $this->check_slugs($postid, 'category', $categories);
		if (is_int($image_id)) {
			return;
		}
	}

	private function set_thumbnail_id($postid, $image_id) {
		add_post_meta($postid, '_thumbnail_id', $image_id, true);
	}

	private function check_slugs($postid, $what, $slugs) {
		sort($slugs);

		// Put all of the image ids into an array and shuffle the
		// array. Pick the first element from the shuffled array and
		// return it.
		$available_ids = array();
		foreach ($slugs as $slug) {
			$image_id = $this->find_image($what, $slug);
			if (is_int($image_id)) {
				$available_ids[] = $image_id;
			}
		}

		if (count($available_ids) > 0) {
			shuffle($available_ids);
			$image_id = $available_ids[0];
			$this->set_thumbnail_id($postid, $image_id);
			return $image_id;
		}
	}

	private function find_image($what, $slug) {
		$title_filter = "active $what $slug";
		add_filter('posts_where', array($this, 'media_where_filter'), 10, 2);
		$images = new WP_Query(array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => 'image',
			'orderby' => 'rand',
			'posts_per_page' => 1,
			'auto_featured_image_slug' => $title_filter,
		));
		remove_filter('posts_where', array($this, 'media_where_filter'), 10, 2);

		if ($images->have_posts()) {
			return $images->posts[0]->ID;
		}

	}

	public function media_where_filter($where, &$query) {
		global $wpdb;
		if ($title = $query->get('auto_featured_image_slug')) {
			$where .= ' AND '
				. $wpdb->posts
				. '.post_title LIKE \''
				. esc_sql($wpdb->esc_like($title))
				. '%\'';
		}
		return $where;
	}
}

new AutoFeaturedImage();
