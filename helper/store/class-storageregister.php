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

use Podcast_Player\Helper\Store\StoreBase;

/**
 * Storage Register
 *
 * @since 1.0.0
 */
class StorageRegister extends StoreBase {
	/**
	 * Holds podcast custom post object ID
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    int
	 */
	protected $object_id = 0;

	/**
	 * Holds podcast title.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	protected $title = '';

	/**
	 * Holds podcast unique ID from feed.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	protected $unique_id = '';

	/**
	 * Holds podcast feed URLs.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	protected $feed_url = array();

	/**
	 * Holds object keys (new version of feed_url).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	protected $object_keys = array();

	/**
	 * Holds visibility status of the podcast.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    bool
	 */
	protected $is_hidden = false;

	/**
	 * Get escape functions.
	 *
	 * @since 1.0.0
	 */
	protected function typeDeclaration() {
		// Data type declaration for safe and proper data output.
		return array(
			'object_id'   => 'int',
			'title'       => 'title',
			'feed_url'    => 'arrUrl',
			'object_keys' => 'arrString',
			'unique_id'   => 'string',
			'is_hidden'   => 'bool',
		);
	}

	/**
	 * Retrieve podcast feed data as an array.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context Retrieve Context.
	 */
	public function retrieve( $context = 'echo' ) {
		return $this->get( array_keys( $this->typeDeclaration() ), $context );
	}

	/**
	 * Set magic method.
	 *
	 * Do not allow adding any new properties to this object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  Name of property.
	 * @param mixed  $value Value of property.
	 *
	 * @throws Exception If property is not allowed.
	 */
	public function __set( $name, $value ) {
		throw new Exception( esc_html( "Cannot add new property \$$name to instance of " ) . __CLASS__ );
	}

	/**
	 * Query to get specific object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name   Property Name.
	 * @param string $needle Check if string contained by prop value.
	 */
	public function query( $name, $needle ) {
		if ( $name && $needle && property_exists( $this, $name ) ) {
			if ( is_array( $this->$name ) ) {
				$haystack = join( '', $this->$name );
			} else {
				$haystack = (string) $this->$name;
			}

			if ( false !== strpos( (string) $haystack, (string) $needle ) ) {
				return $this;
			}
		}
		return false;
	}

	/**
	 * Podcast Lookup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $needle Check if string contained by prop value.
	 */
	public function lookup( $needle ) {
		if ( $this->query( 'object_keys', $needle ) ) {
			return $this;
		} elseif ( $this->query( 'feed_url', $needle ) ) {
			return $this;
		} elseif ( $this->query( 'object_id', $needle ) ) {
			return $this;
		} elseif ( $this->query( 'unique_id', $needle ) ) {
			return $this;
		}
		return false;
	}
}
