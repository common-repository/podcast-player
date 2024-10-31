<?php
/**
 * Perform background tasks.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 * @package    Podcast_Player
 */

namespace Podcast_Player\Backend\Inc;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Utility as Utility_Fn;
use Podcast_Player\Helper\Core\Singleton;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;
use Podcast_Player\Helper\Feed\Get_Feed;

/**
 * Perform background tasks.
 *
 * @package    Podcast_Player
 * @author     vedathemes <contact@vedathemes.com>
 */
class Background_Tasks extends Singleton {

    /**
     * Download episode featured images.
     *
     * @since 7.4.0
     *
     * @param array $return Task result.
     * @param array $args   Background task args.
     */
    public function download_images( $return, $args ) {
        global $wpdb;

        if ( 'yes' !== Get_Fn::get_plugin_option( 'img_save' ) ) {
            // Skip task and remove it from the queue.
            return array( true, $args['data'] );
        }

        $feed_url = isset( $args['identifier'] ) ? $args['identifier'] : '';
        $items    = isset( $args['data'] ) ? $args['data'] : array();
        if ( empty( $feed_url ) || empty( $items ) ) {
            $error = new \WP_Error(
				'no-data-available',
				esc_html__( 'Feed URL or items not found.', 'podcast-player' )
			);
            return ( array( $error, false ) );
        }

        // Process maximum 50 items at a time.
        $items     = array_slice( $items, 0, 50 );
        $in_clause = implode( ',', array_filter( array_map( function ( $item ) use ( $wpdb ) {
            if ( ! empty( $item['featured'] ) ) {
                return $wpdb->prepare( '%s', md5( $item['featured'] ) );
            }
            return false;
        }, $items ) ) );

        $sql           = "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'pp_featured_key' AND meta_value IN ( $in_clause )";
        $results       = $wpdb->get_results( $sql, ARRAY_A );
        $featured_keys = array_column( $results, 'post_id', 'meta_value' );
        $completed     = array();
        $pending       = array();
        foreach ( $items as $key => $item ) {
            $item_image_key = isset( $item['featured'] ) ? md5( $item['featured'] ) : '';
            if ( ! empty( $item_image_key ) && isset( $featured_keys[ $item_image_key ] ) ) {
                $completed[ $key ] = array_merge( $item, array( 'post_id' => $featured_keys[ $item_image_key ] ) );
                continue;
            }

            $pending[ $key ] = $item;
        }

        $pending = array_slice( $pending, 0, 2 );
        return $this->fetch_featured_images( $feed_url, $completed, $pending );
    }

    /**
     * Fetch featured images.
     *
     * @since 7.4.0
     *
     * @param string $feed_url  Feed URL.
     * @param array  $completed Completed items.
     * @param array  $pending   Pending items.
     */
    private function fetch_featured_images( $feed_url, $completed, $pending ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
        foreach ( $pending as $key => $item ) {
            $image_url = isset( $item['featured'] ) ? $item['featured'] : '';
            $title     = isset( $item['title'] ) ? $item['title'] : '';
            if ( empty( $image_url ) ) {
                $completed[ $key ] = array_merge( $item, array( 'post_id' => false ) );
            } else {
                $post_id = media_sideload_image( $image_url, 0, $item['title'], 'id' );
                if ( ! is_wp_error( $post_id ) ) {
                    add_post_meta( $post_id, 'pp_featured_key', md5( $image_url ), true );
                    $completed[ $key ] = array_merge( $item, array( 'post_id' => $post_id ) );
                } else {
					return ( array( $post_id, false ) );
				}
            }
        }
        $this->update_episode_featured_id( $feed_url, $completed );
        return array( true, $completed );
    }

    /**
     * Update episode featured ID.
     *
     * @since 7.4.0
     *
     * @param string $feed_url Feed URL.
     * @param array  $episodes Episodes for which featured image is downloaded.
     */
    private function update_episode_featured_id( $feed_url, $episodes ) {
        if ( empty( $episodes ) ) {
            return;
        }
        $store_manager = StoreManager::get_instance();
        $custom_data   = Get_Fn::get_modified_feed_data( $feed_url );

        if ( ! $custom_data || ! $custom_data instanceof FeedData ) {
            $custom_data = new FeedData();
        }

        if ( isset( $episodes['cover_image'] ) ) {
            $cover_id = $episodes['cover_image']['post_id'];
            if ( $cover_id ) {
                $custom_data->set( 'cover_id', $cover_id );
            }
            unset( $episodes['cover_image'] );
        }

        if ( ! empty( $episodes ) ) {
            $items = $custom_data->get( 'items' );
            foreach ( $episodes as $key => $episode ) {
                $featured_id = $episode['post_id'];
                if ( ! $featured_id ) {
                    continue;
                }
                if ( ! isset( $items[ $key ] ) || ! $items[ $key ] instanceof ItemData ) {
                    $items[ $key ] = new ItemData();
                }
                $items[ $key ]->set( 'featured_id', $featured_id );
            }
            $custom_data->set( 'items', $items );
        }

        $store_manager->update_data( $custom_data, $feed_url, 'modified_feed_data' );
    }

    /**
     * Import podcast episodes as WordPress posts or custom post type.
     *
     * @since 7.4.0
     *
     * @param array $return Task result.
     * @param array $args   Background task args.
     */
    public function import_episodes( $return, $args ) {
        $feed_url = isset( $args['identifier'] ) ? $args['identifier'] : '';
        $elist    = isset( $args['data'] ) ? $args['data'] : array();
        if ( empty( $feed_url ) || empty( $elist ) ) {
            $error = new \WP_Error(
				'no-data-available',
				esc_html__( 'Feed URL or episode list not found.', 'podcast-player' )
			);
            return ( array( $error, false ) );
        }

        $import_settings = Get_Fn::get_feed_import_settings( $feed_url );

        if ( ! $import_settings['is_auto'] ) {
            $error = new \WP_Error(
				'auto-update-disabled',
				esc_html__( 'Auto update has been disabled.', 'podcast-player' )
			);
            return ( array( $error, false ) );
		}

        $imported_episodes = Utility_Fn::import_episodes( $feed_url, $elist, $import_settings );
        if ( is_wp_error( $imported_episodes ) ) {
            return array( $imported_episodes, false );
        }

        $completed = array_intersect( array_keys( $imported_episodes ), $elist );
        return array( true, $completed );
    }


    /**
     * Update podcast feed data.
     *
     * @since 7.4.4
     *
     * @param array $return Task result.
     * @param array $args   Background task args.
     */
    public function update_podcast_data( $return, $args ) {
        $feed_url = isset( $args['identifier'] ) ? $args['identifier'] : '';
        $feed_url = Get_Fn::get_valid_feed_url( $feed_url );
		
        // Skip task and remove it from the queue if feed URL is not valid.
        if ( empty( $feed_url ) || is_wp_error( $feed_url ) ) {
            return ( array( true, false ) );
		}

        // Skip task and remove it from the queue if podcast data is not found.
        $podcast_data = isset( $args['data'] ) ? $args['data'] : array();
        if ( empty( $podcast_data ) ) {
            return ( array( true, false ) );
        }

        $get_feed = new Get_Feed( $feed_url );
        $data     = $get_feed->fetch_podcast_data( $podcast_data );

        // Remove task from the queue if error in fetching podcast data.
        if ( is_wp_error( $data ) ) {
            return array( true, false );
        }
        return array( true, $data );
    }
}
