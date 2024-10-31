<?php
/**
 * Podcast player utility functions.
 *
 * @link       https://www.vedathemes.com
 * @since      3.3.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Functions;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;

/**
 * Podcast player utility functions.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@vedathemes.com>
 */
class Utility {

	/**
	 * Constructor method.
	 *
	 * @since  3.3.0
	 */
	public function __construct() {}

	/**
	 * Convert hex color code to equivalent RGB code.
	 *
	 * @since 3.3.0
	 *
	 * @param string  $hex_color Hexadecimal color value.
	 * @param boolean $as_string Return as string or associative array.
	 * @param string  $sep       String to separate RGB values.
	 * @return string
	 */
	public static function hex_to_rgb( $hex_color, $as_string, $sep = ',' ) {
		$hex_color = preg_replace( '/[^0-9A-Fa-f]/', '', $hex_color );
		$rgb_array = array();
		if ( 6 === strlen( $hex_color ) ) {
			$color_val          = hexdec( $hex_color );
			$rgb_array['red']   = 0xFF & ( $color_val >> 0x10 );
			$rgb_array['green'] = 0xFF & ( $color_val >> 0x8 );
			$rgb_array['blue']  = 0xFF & $color_val;
		} elseif ( 3 === strlen( $hex_color ) ) {
			$rgb_array['red']   = hexdec( str_repeat( substr( $hex_color, 0, 1 ), 2 ) );
			$rgb_array['green'] = hexdec( str_repeat( substr( $hex_color, 1, 1 ), 2 ) );
			$rgb_array['blue']  = hexdec( str_repeat( substr( $hex_color, 2, 1 ), 2 ) );
		} else {
			return false; // Invalid hex color code.
		}
		return $as_string ? implode( $sep, $rgb_array ) : $rgb_array;
	}

	/**
	 * Calculate color contrast.
	 *
	 * The returned value should be bigger than 5 for best readability.
	 *
	 * @link https://www.splitbrain.org/blog/2008-09/18-calculating_color_contrast_with_php
	 *
	 * @since 1.5
	 *
	 * @param int $r1 First color R value.
	 * @param int $g1 First color G value.
	 * @param int $b1 First color B value.
	 * @param int $r2 First color R value.
	 * @param int $g2 First color G value.
	 * @param int $b2 First color B value.
	 * @return float
	 */
	public static function lumdiff( $r1, $g1, $b1, $r2, $g2, $b2 ) {
		$l1 = 0.2126 * pow( $r1 / 255, 2.2 ) + 0.7152 * pow( $g1 / 255, 2.2 ) + 0.0722 * pow( $b1 / 255, 2.2 );
		$l2 = 0.2126 * pow( $r2 / 255, 2.2 ) + 0.7152 * pow( $g2 / 255, 2.2 ) + 0.0722 * pow( $b2 / 255, 2.2 );

		if ( $l1 > $l2 ) {
			return ( $l1 + 0.05 ) / ( $l2 + 0.05 );
		} else {
			return ( $l2 + 0.05 ) / ( $l1 + 0.05 );
		}
	}

	/**
	 * Get multiple columns from an array.
	 *
	 * @since 3.3.0
	 *
	 * @param array $keys     Array keys to be fetched.
	 * @param array $get_from Array from which data needs to be fetched.
	 */
	public static function multi_array_columns( $keys, $get_from ) {
		$keys = array_flip( $keys );
		array_walk(
			$keys,
			function ( &$val, $key ) use ( $get_from ) {
				if ( isset( $get_from[ $key ] ) ) {
					$val = $get_from[ $key ];
				} else {
					$val = array();
				}
			}
		);
		return $keys;
	}

	// /**
	//  * Update feeds and their data in the feed index.
	//  *
	//  * @since 3.4.0
	//  */
	// public static function refresh_index_new() {
	// 	$all_feeds = get_option( 'pp_feed_index' );
	// 	$new       = array();
	// 	$updated   = false;
	// 	if ( $all_feeds && is_array( $all_feeds ) ) {
	// 		foreach ( $all_feeds as $key => $args ) {
	// 			$store_manager = StoreManager::get_instance();
	// 			$feed          = $store_manager->get_data( $key );
	// 			if ( $feed ) {
	// 				if ( is_array( $args ) && isset( $args['url'] ) && $args['url'] ) {
	// 					$new[ $key ] = $args;
	// 				} else {
	// 					$feed        = $feed->retrieve();
	// 					$title       = isset( $feed['title'] ) && $feed['title'] ? $feed['title'] : esc_html__( 'Untitled Feed', 'podcast-player' );
	// 					$url         = isset( $feed['furl'] ) && $feed['furl'] ? $feed['furl'] : '';
	// 					$new[ $key ] = array(
	// 						'title' => $title,
	// 						'url'   => $url,
	// 					);
	// 					$updated     = true;
	// 				}
	// 			}
	// 		}
	// 		if ( $updated || count( $new ) !== count( $all_feeds ) ) {
	// 			update_option( 'pp_feed_index', $new, 'no' );
	// 		}
	// 	}
	// 	return $new;
	// }

	/**
	 * Upload image to wp upload directory.
	 *
	 * @since 5.1.0
	 *
	 * @param string $url   Image URL.
	 * @param string $title Podcast episode title.
	 */
	public static function upload_image( $url = '', $title = '' ) {
		$url   = esc_url_raw( $url );
		$title = sanitize_text_field( $title );
		if ( ! $url ) {
			return false;
		}

		global $wpdb;

		$fid     = md5( $url );
		$sql     = $wpdb->prepare(
			"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'pp_featured_key' AND meta_value = %s",
			$fid
		);
		$post_id = $wpdb->get_var( $sql );
		$post_id = (int) $post_id;
		if ( $post_id ) {
			return $post_id;
		} else {
			// Require relevant WordPress core files for processing images.
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$post_id = media_sideload_image( $url, 0, $title, 'id' );
			if ( ! is_wp_error( $post_id ) ) {
				add_post_meta( $post_id, 'pp_featured_key', $fid, true );
				return $post_id;
			}
		}
		return false;
	}

	/**
	 * New Import function for podcast episodes.
	 *
	 * @since 7.4.0
	 *
	 * @param string $feed_key        Podcast feed key.
	 * @param array  $elist           IDs of episodes to be imported.
	 * @param array  $import_settings Import settings
	 */
	public static function import_episodes( $feed_key, $elist, $import_settings = array() ) {
        $post_author = isset( $import_settings['author'] ) ? intval( $import_settings['author'] ) : 0;
        $post_status = isset( $import_settings['post_status'] ) ? sanitize_text_field( $import_settings['post_status'] ) : 'draft';
        $post_type   = isset( $import_settings['post_type'] ) ? sanitize_text_field( $import_settings['post_type'] ) : 'post';
        $is_get_img  = isset( $import_settings['is_get_img'] ) ? (bool) $import_settings['is_get_img'] : false;
        $taxonomy    = isset( $import_settings['taxonomy'] ) ? sanitize_text_field( $import_settings['taxonomy'] ) : '';

        // Get items data to be imported as WP posts.
        $req_fields = array(
            'title',
            'description',
            'date',
            'timestamp',
            'src',
            'featured',
            'featured_id',
            'mediatype',
            'categories',
			'episode_id',
            'post_id'
        );

        // Get required episodes data from the feed.
		$fdata        = Get_Fn::get_feed_data( $feed_key, array( 'elist' => $elist ), $req_fields );
        $custom_data  = Get_Fn::get_modified_feed_data( $feed_key );
        $custom_items = $custom_data->get( 'items' );

        // Return error message if feed data is not proper.
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

        $items = $fdata['items'];
        $items = array_slice( $items, 0, 10 );

        // Store the original time limit
        $original_time_limit = ini_get('max_execution_time');
        set_time_limit( 300 ); // Give it 5 minutes
        foreach ( $items as $key => $item ) {
            $post_id = isset( $item['post_id'] ) ? absint( $item['post_id'] ) : false;
            $date    = isset( $item['timestamp'] ) ? date( 'Y-m-d H:i:s', $item['timestamp'] ) : date( 'Y-m-d H:i:s', strtotime( $item['date'] ) );
			$post_id = self::check_if_post_exists( $post_id, $item['title'], $date, $post_type );
            if ( $post_id ) {
				$custom_items[ $key ]->set( 'post_id', $post_id );
                continue;
            }

            // Importing the post.
            $new_post_id = wp_insert_post(
				apply_filters(
					'pp_import_post_data',
					array(
						'post_author'  => $post_author,
						'post_content' => wp_kses_post( $item['description'] ),
						'post_date'    => $date,
						'post_status'  => $post_status,
						'post_title'   => sanitize_text_field( $item['title'] ),
						'post_type'    => $post_type,
					)
				)
			);

            // Return error message if the import generate errors.
			if ( is_wp_error( $new_post_id ) ) {
				return array( $new_post_id, $elist );
			}

            // Add post specific information.
			add_post_meta(
				$new_post_id,
				'pp_import_data',
				array(
					'podkey'  => sanitize_text_field( $feed_key ),
					'episode' => sanitize_text_field( $key ),
					'src'     => esc_url_raw( $item['src'] ),
					'type'    => sanitize_text_field( $item['mediatype'] ),
				)
			);

			// Add episode specific information.
			add_post_meta( $new_post_id, 'pp_episode_id', $item['episode_id'], true );

            // Conditionally import and set post featured image.
            if ( $is_get_img ) {
                $img_id = ! empty( $item['featured_id'] ) ? absint( $item['featured_id'] ) : self::upload_image( $item['featured'], $item['title'] );
                if ( $img_id ) {
                    set_post_thumbnail( $new_post_id, $img_id );
                }
            }

			// Assign terms to the post or post type.
			if ( $taxonomy && ! empty( $item['categories'] ) && is_array( $item['categories'] ) ) {
                wp_set_object_terms( $new_post_id, array_map('sanitize_text_field', $item['categories']), $taxonomy );
            }

            // Store post id in custom feed data.
            if ( ! isset( $custom_items[ $key ] ) || ! $custom_items[ $key ] instanceof ItemData ) {
                $custom_items[ $key ] = new ItemData();
            }
            $custom_items[ $key ]->set( 'post_id', $new_post_id );
        }

        // Update custom feed data.
        $custom_data->set( 'items', $custom_items );
        $store_manager = StoreManager::get_instance();
        $store_manager->update_data( $custom_data, $feed_key, 'modified_feed_data' );

		// Return all imported episodes.
		return array_filter( array_map(
			function ( $item ) {
				if ( ! empty( $item->get( 'post_id' ) ) ) {
					return array( 'post_id' => $item->get( 'post_id' ) );
				}
				return false;
			},
			$custom_items
		) );
	}

	/**
     * Check if post exists.
     *
     * @since 7.4.0
     *
     * @param int    $post_id   Post ID.
     * @param string $title     Post title.
     * @param string $date      Post date.
     * @param string $post_type Post type.
     */
    public static function check_if_post_exists( $post_id, $title, $date, $post_type ) {

        // Return if episode post id is available.
        if ( $post_id && false !== get_post_status( $post_id ) ) {
            return $post_id;
        }

        // Query to check if a post with the same title, date, and post type exists
        $args = array(
            'post_type'   => $post_type,
            'post_status' => 'any', // Include all statuses if needed
            'title'       => sanitize_text_field( $title ),
            'date_query'  => array(
                array(
                    'year'  => date( 'Y', strtotime( $date ) ),
                    'month' => date( 'm', strtotime( $date ) ),
                    'day'   => date( 'd', strtotime( $date ) ),
                ),
            ),
            'fields'      => 'ids', // Only get post IDs
        );

        $query = new \WP_Query($args);
        if( $query->have_posts() ) {
			$post_ids = $query->posts;
			$post_id  = $post_ids[0];
			return $post_id;
		}

		return false;
    }

	/**
	 * Schedule next auto update for the podcast.
	 *
	 * @since 5.8.0
	 *
	 * @param string $feed Podcast feed URL or feed key.
	 */
	public static function schedule_next_auto_update( $feed ) {
		// If valid feed URL is provided, let's convert it to feed key.
		if ( Validation_Fn::is_valid_url( $feed ) ) {
			$feed = md5( $feed );
		}

		// Remove all scheduled updates for the feed.
		wp_clear_scheduled_hook( 'pp_auto_update_podcast', array( $feed ) );

		// Auto update time interval. Have at least 10 minutes time interval.
		$cache_time = absint( Get_Fn::get_plugin_option( 'refresh_interval' ) );
		$cache_time = max( $cache_time, 10 ) * 60;
		$time       = apply_filters( 'podcast_player_auto_update_time_interval', $cache_time, $feed );

		// Short circuit filter.
		$is_update = apply_filters( 'podcast_player_auto_update', $feed );
		if ( $is_update ) {
			wp_schedule_single_event( time() + $time, 'pp_auto_update_podcast', array( $feed ) );
		}
	}

	/**
	 * Move podcast custom data from options table to the post table.
	 *
	 * @since 6.6.0
	 *
	 * @param string $feed Podcast feed URL or feed key.
	 */
	public static function move_custom_data( $feed ) {
		$ckey        = 'pp_feed_data_custom_' . $feed;
		$custom_data = get_option( $ckey );
		if ( ! $custom_data || ! is_array( $custom_data ) ) {
			return false;
		}

		$store_manager = StoreManager::get_instance();
		$is_updated    = $store_manager->update_data( $custom_data, $feed, 'custom_feed_data' );
		if ( $is_updated ) {
			delete_option( $ckey );
			delete_option( 'pp_feed_data_' . $feed );
		}
		return $custom_data;
	}

	/**
	 * Get modified custom feed data.
	 *
	 * @since 7.4.0
	 *
	 * @param string $feed Podcast feed URL or feed key.
	 */
	public static function get_modified_feed_data( $feed ) {
		$store_manager = StoreManager::get_instance();
		$feed_data     = $store_manager->get_data( $feed, 'modified_feed_data' );
		if ( $feed_data && $feed_data instanceof FeedData ) {
			return $feed_data;
		}

		$feed_data = $store_manager->get_data( $feed, 'custom_feed_data' );
		if ( ! $feed_data ) {
			return new FeedData();
		}

		if ( $feed_data instanceof FeedData ) {
			return $feed_data;
		}

		// Compatibility with old data, if any.
		if ( is_array( $feed_data ) ) {
			// We assume earlier versions only save item data in the custom feed data array.
			$items = array_map(
				function ( $item ) {
					$item_data = new ItemData();
					$item_data->set( $item, false, 'none' );
					return $item_data;
				},
				$feed_data
			);
	
			$feed_data = new FeedData();
			$feed_data->set( 'items', $items );
			return $feed_data;
		}

		return new FeedData();
	}
}
