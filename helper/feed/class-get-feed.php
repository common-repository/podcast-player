<?php
/**
 * Get Feed Data from Database OR from Feed XML file.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Feed;

use Podcast_Player\Helper\Functions\Getters as Get_Fn;
use Podcast_Player\Helper\Functions\Utility as Utility_Fn;
use Podcast_Player\Helper\Functions\Validation as Validation_Fn;
use Podcast_Player\Helper\Store\ItemData;
use Podcast_Player\Helper\Store\FeedData;
use Podcast_Player\Helper\Store\StoreManager;
use Podcast_Player\Helper\Core\Background_Jobs;

/**
 * Get Feed Data from Database OR from Feed XML file.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 * @author     vedathemes <contact@vedathemes.com>
 */
class Get_Feed {

	/**
	 * Holds feed url for current instance.
	 *
	 * @since  3.3.0
	 * @access private
	 * @var    string
	 */
	private $feed_url = '';

	/**
	 * Holds feed filter and sort args.
	 *
	 * @since  3.3.0
	 * @access private
	 * @var    array
	 */
	private $mods = array();

	/**
	 * Holds required episodes field keys.
	 *
	 * @since  3.3.0
	 * @access private
	 * @var    array
	 */
	private $fields = array();

	/**
	 * Holds feed key prefix.
	 *
	 * @since  3.3.0
	 * @access private
	 * @var    string
	 */
	private $prefix = 'pp_feed';

	/**
	 * Check if podcast player pro is available.
	 *
	 * @since  5.8.0
	 * @access private
	 * @var    bool
	 */
	private $is_pro = false;

	/**
	 * Constructor method.
	 *
	 * @since  3.3.0
	 *
	 * @param string $feedurl Feed URL.
	 * @param array  $mods    Feed episode filter args.
	 * @param array  $fields  Required episode field keys.
	 */
	public function __construct( $feedurl = '', $mods = array(), $fields = array() ) {
		// Set Object Properties.
		$this->mods     = $mods;
		$this->fields   = $fields;
		$this->feed_url = $feedurl;

		// Check if pro is available.
		$this->is_pro = apply_filters( 'podcast_player_is_premium', false );
	}

	/**
	 * Init method.
	 *
	 * @since  3.3.0
	 */
	public function init() {

		// Get feed data from DB or from feed url.
		$fdata = $this->get_feed_data();
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

		// Apply sort, filter and other customizations.
		$fdata = $this->modify_feed_data( $fdata );
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

		// Check and use custom data for feed items.
		$fdata = $this->override_customizations( $fdata );
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

		// Prepare data for frontend.
		$fdata = $this->prepare_data( $fdata );
		if ( is_wp_error( $fdata ) ) {
			return $fdata;
		}

		return $fdata;
	}

	/**
	 * Get podcast feed data from storage or fetch from feed URL.
	 *
	 * @since  3.3.0
	 */
	public function get_feed_data() {
		$store_manager = StoreManager::get_instance();
        $podcast_data  = $store_manager->get_data( $this->feed_url );
		$podcast_data  = $podcast_data instanceof FeedData ? $podcast_data : false;
		if ( $this->is_fetch_required( $podcast_data ) ) {
			return $this->fetch_podcast_data( $podcast_data );
		}
        return $podcast_data->retrieve();
	}

	/**
     * Fetch Podcast Data from the feed.
     *
     * @since  7.4.0
	 *
	 * @param Object|false $old_podcast_data Old Podcast Feed Data.
     */
    public function fetch_podcast_data( $old_podcast_data ) {
		$store_manager = StoreManager::get_instance();
		$last_checked  = $store_manager->get_data( $this->feed_url, 'last_checked' );
		if ( ! empty( $last_checked ) ) {
			$etag          = $old_podcast_data ? $old_podcast_data->get( 'etag', 'sanitize' ) : false;
			$last_modified = $old_podcast_data ? $old_podcast_data->get( 'lastbuild', 'sanitize' ) : false;
		} else {
			$etag          = false;
			$last_modified = false;
		}
        $obj           = new Fetch_Feed( $this->feed_url, $etag, $last_modified );
		$raw_data      = $obj->get_feed_data();
		if ( is_wp_error( $raw_data ) ) {
			return $raw_data;
		}

		// Return old data if no update required.
		if ( '304_use_cache' === $raw_data ) {
			$data         = $old_podcast_data;
			$new_episodes = array();
		} else {
			list( $data, $new_episodes ) = $this->prepare_storage_data( $raw_data, $old_podcast_data );
		}

		$url   = $data->get( 'furl' );
		$alias = $url !== $this->feed_url ? $this->feed_url : false;

		if ( ! $old_podcast_data ) {
			$title = $data->get( 'title' );
			$store_manager->maybe_add_new_object( $url, $title );
		}
		if ( '304_use_cache' !== $raw_data ) {
			$store_manager->update_data( $data, $url, 'feed_data', $alias );
		}
		$store_manager->update_data( time(), $url, 'last_checked' );

		// Fecilitate new episode import.
		if ( $this->is_pro && ! empty( $new_episodes ) ) {
			// If opted, queue new episodes for import.
			$import_settings = Get_Fn::get_feed_import_settings( $this->feed_url );
			if ( $import_settings['is_auto'] ) {
				Background_Jobs::add_task( $this->feed_url, 'import_episodes', $new_episodes );
			}
		}

        return $data->retrieve();
    }

	/**
	 * Prepare data for storage.
	 *
	 * @since  7.4.0
	 *
	 * @param  Object       $new_data Raw podcast feed data.
	 * @param  Object|false $old_data Old podcast feed data.
	 */
	private function prepare_storage_data( $new_data, $old_data ) {
		$obj = new Prepare_Storage( $new_data, $old_data );
		return $obj->init();
	}

	/**
	 * Save fetched data.
	 *
	 * @since  3.3.0
	 *
	 * @param array $data Apply sort and filters on fetched data.
	 */
	private function modify_feed_data( $data ) {
		$obj                   = Modify_Feed_Data::get_instance();
		list( $total, $items ) = $obj->init( $data, $this->mods, $this->fields );

		if ( empty( $items ) ) {
			return new \WP_Error(
				'no-filtered-items',
				esc_html__( 'No feed items for your specific filters.', 'podcast-player' )
			);
		}

		$data['items'] = $items;
		$data['total'] = $total;

		return $data;
	}

	/**
	 * Check and use custom data for feed items..
	 *
	 * @since  3.3.0
	 *
	 * @param array $data Feed data to be overridden by customizations.
	 */
	private function override_customizations( $data ) {
		$custom_data = Get_Fn::get_modified_feed_data( $this->feed_url );
		if ( ! $custom_data instanceof FeedData ) {
			return $data;
		}

		$custom_data       = $custom_data->retrieve();
		$custom_data_items = isset( $custom_data['items'] ) ? $custom_data['items'] : array();
		$items             = isset( $data['items'] ) ? $data['items'] : array();
		// Exclude deleted and filtered items from the custom data.
		$custom_data_items = array_intersect_key( $custom_data_items, $items );

		// Remove date and duration fields from the required items.
		$fields = array_diff( $this->fields, array( 'date', 'dur' ) );
		$custom_data_items = array_filter( array_map(
			function ( $item ) use ( $fields ) {
				if ( ! $item instanceof ItemData ) {
					return false;
				}
				return array_filter( $item->retrieve( 'echo', $fields ) );
			},
			$custom_data_items
		) );
		$custom_data['items'] = $custom_data_items;
		$custom_data = array_filter( $custom_data );

		/**
		 * Custom data for the feed to override original data.
		 *
		 * @since 3.3.0
		 *
		 * @param array  $custom_data    Feed items custom data.
		 * @param string $this->feed_url Feed URL.
		 */
		$custom_data = apply_filters( 'podcast_player_custom_data', $custom_data, $this->feed_url );

		// Return if custom data do not exist.
		if ( ! $custom_data || ! is_array( $custom_data ) ) {
			return $data;
		}

		$data  = array_replace_recursive( $data, $custom_data );
		$items = $data['items'];

		// Get cumulative array of all available seasons.
		$seasons = array_values( array_filter( array_unique( array_column( $items, 'season' ) ) ) );

		// Get cumulative array of all available categories.
		$cats = array_column( $items, 'categories' );
		$cats = array_unique( call_user_func_array( 'array_merge', $cats ) );

		$data['seasons']    = $seasons;
		$data['categories'] = $cats;
		return $data;
	}

	/**
	 * Save fetched data.
	 *
	 * @since  3.3.0
	 *
	 * @param array $data Apply sort and filters on fetched data.
	 */
	private function prepare_data( $data ) {
		$obj = Prepare_Front_New::get_instance();
		return $obj->init( $data );
	}

	/**
     * Check if feed data is required or not.
     *
     * @since 7.4.0
     *
     * @param  Object|false $podcast_data
     */
    private function is_fetch_required( $podcast_data ) {
		if ( ! $podcast_data ) {
			return true;
		}

		$store_manager    = StoreManager::get_instance();
		$last_checked     = $store_manager->get_data( $this->feed_url, 'last_checked' );
		$last_checked     = $last_checked ? $last_checked : 0;
		$refresh_interval = absint( Get_Fn::get_plugin_option( 'refresh_interval' ) );
		$cache_duration   = min( $podcast_data->get( 'cache_duration' ), $refresh_interval * 60 );
		if ( ( $last_checked + $cache_duration ) < time() ) {
			return true;
		}

		return false;
    }
}
