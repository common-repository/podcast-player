<?php
/**
 * Podcast player dashboard widget.
 *
 * @link       https://www.vedathemes.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 */

namespace Podcast_Player\Backend\Inc;

use Podcast_Player\Helper\Core\Singleton;

/**
 * Display podcast player dashboard widget.
 *
 * @package    Podcast_Player
 * @author     vedathemes <contact@vedathemes.com>
 */
class Dashboard_Widget extends Singleton {

	/**
	 * Initiate adding dashboard widget.
	 *
	 * @since 7.3.0
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget( 'pp_dashboard_widget', 'Podcast Player Updates & Resources', array( $this, 'render_dashboard_widget' ), null, null, 'normal', 'default' );
	}

	/**
	 * Render dashboard widget.
	 *
	 * @since 7.3.0
	 */
	public function render_dashboard_widget() {
		$url      = $this->get_plugin_author_uri( PODCAST_PLAYER_DIR . '/podcast-player.php' );
		$feed_url = $url ? $url . '/feed' : false;
		if ( ! $feed_url ) {
			return;
		}
		$feed_items = $this->get_feed_items( $feed_url );
		if ( ! $feed_items ) {
			return;
		}

		$this->dashboard_widget_markup( $feed_items );
	}

	/**
	 * Fetch the Author URI from the plugin header information
	 *
	 * @since 7.3.0
	 *
	 * @param string $plugin_file The path to the plugin file.
	 */
	private function get_plugin_author_uri($plugin_file) {
		// Define the headers we want to retrieve
		$plugin_data = get_file_data($plugin_file, array('AuthorURI' => 'Author URI'));

		// Return the Author URI
		return isset($plugin_data['AuthorURI']) ? $plugin_data['AuthorURI'] : '';
	}

	/**
	 * Get news and updates from the easypodcastpro feed.
	 *
	 * @since 7.3.0
	 *
	 * @param string $feed_url Feed URL.
	 */
	private function get_feed_items( $feed_url ) {
		include_once( ABSPATH . WPINC . '/feed.php' );
		$rss = fetch_feed( $feed_url );

		if ( is_wp_error( $rss ) ) {
			return false;
		}

		return $rss->get_items( 0, $rss->get_item_quantity( 10 ) );
	}

	/**
	 * Render dashboard widget markup.
	 *
	 * @since 7.3.0
	 *
	 * @param array $feed_items Feed items.
	 */
	private function dashboard_widget_markup( $feed_items ) {
		$item_keys    = array_rand( $feed_items, 3 );
		$random_items = array_map( function( $key ) use ( $feed_items ) {
			return $feed_items[ $key ];
		}, $item_keys );
		?>
		<div class="pp-dashboard-widget">
			<h3 class="pp-dash-heading"><?php esc_html_e( 'Resources & Articles', 'podcast-player' ); ?></h3>
			<ul class="pp-dash-list">
				<?php
				foreach ( $random_items as $item ) {
					?>
					<li class="pp-dash-list-item">
						<a target="_blank" class="pp-dash-item-link" href="<?php echo esc_url( $item->get_permalink() ); ?>">
							<?php echo esc_html( $item->get_title() ); ?>
						</a>
						<p class="pp-dash-item-desc">
							<?php echo esc_html( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $item->get_description() ) ), 15 ) ); ?>
						</p>
					</li>
					<?php
				}
				?>
			</ul>
		</div>
		<?php
	}
}
