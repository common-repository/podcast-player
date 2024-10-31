<?php
/**
 * Base class to store podcast feed data.
 *
 * @link       https://easypodcastpro.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Store;

use Podcast_Player\Helper\Core\Singleton;
use Podcast_Player\Helper\Store\StorageRegister;

/**
 * Store Manager Class
 *
 * @since 1.0.0
 */
class StoreManager extends Singleton {

	/**
	 * Setup initial state of this class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Setup store manager.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Register post types to store all playlists and its data.
		$test = register_post_type(
			'podcast_player',
			array(
				'labels'    => array(
					'name'          => esc_html__( 'Podcasts', 'podcast-player' ),
					'singular_name' => esc_html__( 'Podcast', 'podcast-player' ),
				),
				'query_var' => false,
			)
		);
	}

	/**
	 * Get a stored object data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to access the post object bucket.
	 * @param string $data_key   Key to access specific data stored in the object.
	 */
	public function get_data( $object_key, $data_key = 'feed_data' ) {
		$index = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return false;
		}
		if ( is_array( $data_key ) ) {
			$data = array();
			foreach ( $data_key as $key ) {
				$data[ $key ] = get_post_meta( $index->get( 'object_id' ), $key, true );
			}
			return $data;
		}
		return get_post_meta( $index->get( 'object_id' ), $data_key, true );
	}

	/**
	 * Update an existing data object or create a new one.
	 *
	 * @since 1.0.0
	 *
     * @param string $data             Data to store in the object.
	 * @param string $object_key       Key to access the post object bucket.
	 * @param string $data_key         Key to access specific data stored in the object.
	 * @param string $object_key_alias Alias to the object key.
	 */
	public function update_data( $data, $object_key, $data_key = 'feed_data', $object_key_alias = false ) {
		$index = $this->get_object_index( $object_key );

		// If main object key is not indexed, try with alias key.
		if ( ! $index instanceof StorageRegister && $object_key_alias ) {
			$index = $this->get_object_index( $object_key_alias );
			if ( $index instanceof StorageRegister ) {
				list( $object_key, $object_key_alias ) = array( $object_key_alias, $object_key );
			}
		}

		if ( $index instanceof StorageRegister ) {
			update_post_meta( $index->get( 'object_id' ), $data_key, $data );
			if ( $object_key_alias ) {
				$this->add_alias_to_object_key( $object_key, $object_key_alias );
			}
			return true;
		}
	}

	/**
	 * Delete stored data or object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to access the post object bucket.
	 * @param string $data_key   Key to access specific data stored in the object.
	 */
	public function delete_data( $object_key, $data_key = false ) {
		$return = false;
		$index  = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return $return;
		}
        if ( $data_key ) {
			if ( is_array( $data_key ) ) {
				foreach ( $data_key as $key ) {
					delete_post_meta( $index->get( 'object_id' ), $key );
				}
				return true;
			}
			$return = delete_post_meta( $index->get( 'object_id' ), $data_key );
		} else {
			$return = wp_delete_post( $index->get( 'object_id' ), true );
            $this->delete_object_index( $index->get( 'unique_id' ) );
		}
		return $return;
	}

	/**
	 * Hide stored data or object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to access the post object bucket.
	 */
	public function hide_data( $object_key ) {
		$index = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return;
		}
		$index->set( 'is_hidden', true );
		$register = $this->get_register();
		$register[ $index->get( 'unique_id' ) ] = $index;
		update_option( 'pp-register', $register, false );
	}

	/**
	 * Create a new post object to save data (if not already created).
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key   Key to create/access the post object bucket.
	 * @param string $object_title Object Title.
	 */
	public function maybe_add_new_object( $object_key, $object_title = '' ) {
        // Check if object already exists.
        $index = $this->get_object_index( $object_key );
        if ( $index instanceof StorageRegister ) {
			$is_hidden = $index->get( 'is_hidden' );
			if ( $is_hidden ) {
				$index->set( 'is_hidden', false );
				$register = $this->get_register();
				$register[ $index->get( 'unique_id' ) ] = $index;
				update_option( 'pp-register', $register, false );
			}
            return $index->get( 'object_id' );
        }

        // Create custom post object to save feed data.
		$object_id = wp_insert_post(
			array(
				'post_type'   => 'podcast_player',
				'post_status' => 'publish',
			)
		);
		if ( is_wp_error( $object_id ) ) {
			return $object_id;
		}

		// Create Object Index.
		return $this->create_object_index( $object_key, $object_id, $object_title );
	}

	/**
	 * Create Object Index.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key   Key to create/access the post object bucket.
     * @param string $object_id    Object ID.
     * @param string $object_title Object Title.
	 */
	private function create_object_index( $object_key, $object_id, $object_title ) {
		$register    = $this->get_register();
		$object_keys = array( $object_key );
		$unique_id   = md5( $object_key );
		$index_obj   = new StorageRegister();
		$index_obj->set( 'unique_id', $unique_id );
		$index_obj->set( 'title', $object_title );
		$index_obj->set( 'feed_url', $object_keys );
		$index_obj->set( 'object_keys', array_map( 'md5', $object_keys ) );
		$index_obj->set( 'object_id', $object_id );
		$register[ $unique_id ] = $index_obj;
		update_option( 'pp-register', $register, false );
		return true;
	}

	/**
	 * Add Alias to the podcast register.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key       Key to create/access the post object bucket.
	 * @param string $object_key_alias Alias to the object key.
	 */
	private function add_alias_to_object_key( $object_key, $object_key_alias ) {
		$index  = $this->get_object_index( $object_key );
		if ( ! $index instanceof StorageRegister ) {
			return;
		}

		$urls   = $index->get( 'feed_url' );
		$id     = $index->get( 'unique_id' );
        $urls[] = (string) $object_key_alias;
		$index->set( 'feed_url', array_unique( $urls ) );
		$index->set( 'object_keys', array_map( 'md5', array_unique( $urls ) ) );
		$register = $this->get_register();
		$register[ $id ] = $index;
		update_option( 'pp-register', $register, false );
		return true;
	}

	/**
	 * Get stored object index object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to create/access the post object bucket.
	 */
	public function get_object_index( $object_key = '' ) {
		$object_key = apply_filters( 'podcast_player_index', $object_key );
		$register   = $this->get_register();

		if ( empty( $object_key ) ) {
			// Filter out hidden objects.
			$register = array_filter( $register, function( $sr ) {
				return $sr instanceof StorageRegister && ! $sr->get( 'is_hidden' );
			} );
			return $register;
		}

		// If object key is provided instead of the URL.
		if ( isset( $register[ $object_key ] ) ) {
			return $register[ $object_key ];
		}

		// Get object key from the URL.
		$unique_id = md5( $object_key );

		// Check and get podcast by unique ID.
		if ( isset( $register[ $unique_id ] ) ) {
			return $register[ $unique_id ];
		}

		// Deep search for the required podcast in the index.
		foreach ( $register as $podcast ) {
			if ( $podcast->lookup( $object_key ) ) {
				return $podcast;
			}
		}
		return false;
	}

    /**
	 * Remove an object from the Index.
	 *
	 * @since 1.0.0
	 *
	 * @param string $object_key Key to create/access the post object bucket.
	 */
	private function delete_object_index( $object_key ) {
		$register = $this->get_register();
		if ( ! $object_key || ! isset( $register[ $object_key ] ) ) {
			return false;
		}
		unset( $register[ $object_key ] );
		update_option( 'pp-register', $register, false );
	}

	/**
	 * Get database register.
	 *
	 * @since 1.0.0
	 */
	private function get_register() {
		$register = get_option( 'pp-register' );
		return false !== $register ? $register : array();
	}

	/**
	 * Get custom data (compatibility method for previous pro version).
	 * 
	 * Method will be removed in 7.6.0
	 *
	 * @since 7.4.0
	 *
	 * @param string $feed Podcast unique ID or feed URL.
	 */
	public function get_custom_data( $feed ) {
		$custom_feed_data = $this->get_data( $feed, 'modified_feed_data' );
		if ( $custom_feed_data && $custom_feed_data instanceof FeedData ) {
			$items = $custom_feed_data->get( 'items' );
			$items = array_filter( array_map( function( $item ) {
				if ( $item instanceof ItemData ) {
					return $item->retrieve();
				}
				return false;
			}, $items ) );
			return $items;
		}

		$custom_feed_data = $this->get_data( $feed, 'custom_feed_data' );
		if ( is_array( $custom_feed_data ) ) {
			return $custom_feed_data;
		}

		return array();
	}

	/**
	 * Update custom data (compatibility method for previous pro version).
	 * 
	 * Method will be removed in 7.6.0
	 *
	 * @since 7.4.0
	 *
	 * @param string $feed Podcast unique ID or feed URL.
	 * @param array  $custom_data Podcast custom data.
	 */
	public function update_custom_data( $feed, $custom_data ) {
		$custom_feed_data = $this->get_data( $feed, 'modified_feed_data' );
		if ( ! $custom_feed_data || ! $custom_feed_data instanceof FeedData ) {
			$custom_feed_data = new FeedData();
		}

		if ( is_array( $custom_data ) ) {
			$items = array_map(
				function ( $item ) {
					$item_data = new ItemData();
					$item_data->set( $item, false, 'none' );
					return $item_data;
				},
				$custom_data
			);

			$custom_feed_data->set( 'items', $items );
			$this->update_data( $custom_feed_data, $feed, 'modified_feed_data' );
		}
	}
}
