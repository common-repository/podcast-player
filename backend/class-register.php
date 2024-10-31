<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Backend
 */

namespace Podcast_Player\Backend;

use Podcast_Player\Backend\Inc\Loader;
use Podcast_Player\Backend\Admin\Options;
use Podcast_Player\Backend\Inc\Shortcode;
use Podcast_Player\Backend\Inc\Block;
use Podcast_Player\Backend\Inc\Misc;
use Podcast_Player\Backend\Inc\Background_Tasks;
use Podcast_Player\Helper\Core\Background_Jobs;
use Podcast_Player\Backend\Inc\Dashboard_Widget as Dash;

/**
 * The admin-specific functionality of the plugin.
 *
 * Register custom widget and custom shortcode functionality. Enqueue admin area
 * scripts and styles.
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Backend
 * @author     vedathemes <contact@vedathemes.com>
 */
class Register {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 3.3.0
	 */
	public function __construct() {}

	/**
	 * Register hooked functions.
	 *
	 * @since 3.3.0
	 */
	public static function init() {

		// Load podcast player resources on admin screens.
		$loader = Loader::get_instance();

		self::load_resources( $loader );

		// Initiate podcast player admin notices.
		self::admin_notices( $loader );

		// Add Elementor edit screen support.
		self::elementor_support( $loader );

		// Register podcast player widget.
		self::register_widget();

		// Register podcast player block.
		self::register_block();

		// Add action links.
		self::action_links();

		// Adds a new dashboard widget for podcast player.
		// self::add_dashboard_widget();

		// Register miscellaneous actions.
		self::misc_actions();

		// Register podcast player shortcode display method.
		self::register_shortcode();

		// Register admin options.
		$options = Options::get_instance();
		$options->init();

		// Running background jobs.
		self::hook_background_tasks();

		// Hook background jobs ajax actions to WordPress.
		Background_Jobs::init();
	}

	/**
	 * Load podcast player resources on admin screens.
	 *
	 * @since 3.3.0
	 *
	 * @param object $instance PP admin loader instance.
	 */
	public static function load_resources( $instance ) {
		add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $instance, 'enqueue_editor_scripts' ) );

		/*
		 * This script must be loaded before mediaelement-migrate.js to work.
		 * admin_enqueue_scripts hook is very late for that. As migrate script added
		 * by script handle 'wp-edit-post' at very top of 'edit-form-blocks.php'.
		 */
		add_action( 'admin_init', array( $instance, 'mediaelement_migrate_error_fix' ), 0 );
	}

	/**
	 * Initiate podcast player admin notices.
	 *
	 * @since 3.3.0
	 *
	 * @param object $instance PP admin loader instance.
	 */
	public static function admin_notices( $instance ) {
		add_action( 'admin_head', array( $instance, 'dismiss_notices' ) );
		add_action( 'admin_notices', array( $instance, 'admin_notices' ) );
	}

	/**
	 * Add Elementor edit screen support.
	 *
	 * @since 3.3.0
	 *
	 * @param object $instance PP admin loader instance.
	 */
	public static function elementor_support( $instance ) {
		add_action(
			'elementor/editor/before_enqueue_scripts',
			array( $instance, 'enqueue_styles' )
		);
		add_action(
			'elementor/editor/before_enqueue_scripts',
			array( $instance, 'enqueue_scripts' )
		);
	}

	/**
	 * Register the custom Widget.
	 *
	 * @since 3.3.0
	 */
	public static function register_widget() {
		add_action(
			'widgets_init',
			function () {
				register_widget( 'Podcast_Player\Backend\Inc\Widget' );
			}
		);
	}

	/**
	 * Register podcast player shortcode.
	 *
	 * @since 3.3.0
	 */
	public static function register_shortcode() {
		$shortcode = Shortcode::get_instance();
		add_shortcode( 'podcastplayer', array( $shortcode, 'render' ) );
	}

	/**
	 * Register podcast player editor block.
	 *
	 * @since 3.3.0
	 */
	public static function register_block() {
		$block = Block::get_instance();
		add_action( 'init', array( $block, 'register' ) );
	}

	/**
	 * Register the plugin's miscellaneous actions.
	 *
	 * @since 3.3.0
	 */
	public static function misc_actions() {
		$misc = Misc::get_instance();

		// TODO: To be removed in the 7.5.0
		add_action( 'pp_save_images_locally', array( $misc, 'save_images_locally' ) );

		// TODO: Instead of cron updates, should use Background Jobs.
		add_action( 'pp_auto_update_podcast', array( $misc, 'auto_update_podcast' ) );
		add_action( 'rest_api_init', array( $misc, 'register_routes' ) );
		add_action( 'init', array( $misc, 'init_storage' ) );
	}

	/**
	 * Register the plugin's miscellaneous actions.
	 *
	 * @since 3.3.0
	 */
	public static function action_links() {
		$misc = Misc::get_instance();
		add_action( 'plugin_action_links_' . PODCAST_PLAYER_BASENAME, array( $misc, 'action_links' ) );
	}

	/**
	 * Add a dashboard widget for the podcast player.
	 *
	 * @since 7.3.0
	 */
	public static function add_dashboard_widget() {
		$dash = Dash::get_instance();
		add_action( 'wp_dashboard_setup', array( $dash, 'add_dashboard_widget' ) );
	}

	/**
	 * Run background jobs.
	 *
	 * @since 7.4.0
	 */
	public static function hook_background_tasks() {
		$inst = Background_Tasks::get_instance();
		add_filter( 'podcast_player_bg_task_download_image', array( $inst, 'download_images' ), 10, 2 );
		add_filter( 'podcast_player_bg_task_import_episodes', array( $inst, 'import_episodes' ), 10, 2 );
		add_filter( 'podcast_player_bg_task_update_podcast_data', array( $inst, 'update_podcast_data' ), 10, 2 );
	}
}
