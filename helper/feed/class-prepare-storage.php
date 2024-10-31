<?php
/**
 * Fetch Feed Data from Feed XML file.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Feed;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\ItemData;

/**
 * Fetch Feed Data from Feed XML file.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@vedathemes.com>
 */
class Prepare_Storage {

	/**
	 * Holds old feed data.
	 *
	 * @since  6.4.3
	 * @access private
	 * @var    array
	 */
	private $new_data = array();

	/**
	 * Holds old feed data.
	 *
	 * @since  6.4.3
	 * @access private
	 * @var    array
	 */
	private $old_data = array();

	/**
	 * Holds new episodes ID.
	 *
	 * @since  7.4.0
	 * @access private
	 * @var    array
	 */
	private $new_episodes = array();

	/**
	 * Constructor method.
	 *
	 * @since  6.4.3
	 *
	 * @param object $new_data Newly fetched podcast feed data.
	 * @param string $old_data Previously stored podcast feed data.
	 */
	public function __construct( $new_data, $old_data ) {
		$this->new_data = $new_data;
		$this->old_data = $old_data;
	}

	/**
	 * Prepare feed data for storage.
	 *
	 * @since  6.4.3
	 */
	public function init() {
		// If old data is not available, prepare new data for storage.
		if ( ! $this->old_data instanceof FeedData ) {
			return $this->new_podcast();
		}

		// Compare new data with old stored data for changes.
		return $this->get_changes();
	}

	/**
	 * Prepare freshly fetched data for storage, no old data is available.
	 *
	 * @since 7.4.0
	 */
	private function new_podcast() {
		$episode_ids = array_keys( $this->new_data->get( 'items' ) );
		return array( $this->new_data, $episode_ids );
	}

	/**
	 * Compare new data with old stored data for changes.
	 *
	 * @since  7.4.0
	 */
	private function get_changes() {
		list( $new_items, $deleted_items, $added_items ) = $this->get_items_list();

		$keep_old = Get_Fn::get_plugin_option( 'keep_old' );
		if ( $keep_old ) {
			$new_items = array_merge( $new_items, $deleted_items );
		}

		$this->new_data->set( 'items', $new_items );
		return array( $this->new_data, array_keys( $added_items ) );
	}

	/**
	 * Get list of new and old items for the podcast.
	 *
	 * @since  7.4.0
	 */
	private function get_items_list() {
		$old_items = $this->old_data->get( 'items' );
		$new_items = $this->new_data->get( 'items' );

		// Return data if no old items have been deleted.
		$deleted_items = array_diff_key( $old_items, $new_items );
		$added_items   = array_diff_key( $new_items, $old_items );
		if ( empty( $deleted_items ) || empty( $added_items ) ) {
			return array( $new_items, $deleted_items, $added_items );
		}

		$deleted_ids     = $this->extract_episode_id( $deleted_items );
		$added_ids       = $this->extract_episode_id( $added_items );
		$flipped_del_ids = array_flip( $deleted_ids );
		$key_pair        = array();
		foreach ( $added_ids as $key => $value ) {
			if ( isset( $flipped_del_ids[ $value ] ) ) {
				$key_pair[ $key ] = $flipped_del_ids[ $value ];
			}
		}

		if ( empty( $key_pair ) ) {
			return array( $new_items, $deleted_items, $added_items );
		}

		$updated_new_items = array();
		foreach( $new_items as $key => $item ) {
			if ( isset( $key_pair[ $key ] ) ) {
				$new_key = $key_pair[ $key ];
				$updated_new_items[ $new_key ] = $item;
			} else {
				$updated_new_items[ $key ] = $item;
			}
		}
		$new_items     = $updated_new_items;
		$deleted_items = array_diff_key( $old_items, $new_items );
		$added_items   = array_diff_key( $new_items, $old_items );

		return array( $new_items, $deleted_items, $added_items );
	}

	/**
	 * Fetch relevant items data.
	 *
	 * @since 7.4.0
	 *
	 * @param array $items Array of items
	 */
	private function extract_episode_id( $items ) {
		return array_map(
			function( $item ) {
				return $item->get( 'episode_id' );
			},
			$items
		);
	}
}
