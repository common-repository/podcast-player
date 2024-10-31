<?php
/**
 * Object to store podcast feed data.
 *
 * Object will save channel level data and itemdata objects.
 *
 * @link       https://easypodcastpro.com
 * @since      1.0.0
 *
 * @package    Podcast_Player
 * @subpackage Podcast_Player/Helper
 */

namespace Podcast_Player\Helper\Store;

/**
 * Store podcast feed level data.
 *
 * @package Podcast_Player
 */
class FeedData extends StoreBase {

	/**
	 * Holds podcast store object ID.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $post_id = 0;

	/**
	 * Holds podcast title.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $title;

	/**
	 * Holds podcast description.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $desc;

	/**
	 * Holds podcast website link.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $link;

	/**
	 * Holds podcast cover image.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $image;

	/**
	 * Holds podcast cover image ID.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $cover_id = 0;

	/**
	 * Holds podcast feed url.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $furl;

	/**
	 * Holds podcast podcast unique key.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $fkey;

	/**
	 * Holds podcast copyright information.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $copyright;

	/**
	 * Holds podcast author.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $author;

	/**
	 * Holds podcast title.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $podcats;

	/**
	 * Holds podcast lastbuild date.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $lastbuild;

	/**
	 * Holds podcast etag data.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $etag;

	/**
	 * Holds podcast owner name and email ID.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $owner;

	/**
	 * Holds array of podcast episode objects.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $items;

	/**
	 * Holds array of all podcast seasons.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $seasons;

	/**
	 * Holds array of all podcast episode categories.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $categories;

	/**
	 * Holds total number of podcast episodes.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $total;

	/**
	 * Holds donation or funding link.
	 *
	 * @since  7.4.0
	 * @access protected
	 * @var    array
	 */
	protected $funding;

	/**
	 * Holds feed data cache duration.
	 *
	 * @since  7.4.0
	 * @access protected
	 * @var    int
	 */
	protected $cache_duration = 86400;

	/**
	 * Holds information about activeness of the podcast.
	 *
	 * @since  7.4.0
	 * @access protected
	 * @var    bool
	 */
	protected $is_active = true;

	/**
	 * Holds information podcast release cycle.
	 *
	 * @since  7.4.0
	 * @access protected
	 * @var    int
	 */
	protected $release_cycle = 7;

	/**
	 * Holds information about days since last episode release.
	 *
	 * @since  7.4.0
	 * @access protected
	 * @var    int
	 */
	protected $last_released;

	/**
	 * Get escape functions.
	 *
	 * @since 1.0.0
	 */
	protected function typeDeclaration() {
		// Data type declaration for safe and proper data output.
		return array(
			'post_id'        => 'int',
			'title'          => 'title',
			'desc'           => 'desc',
			'link'           => 'url',
			'image'          => 'url',
			'cover_id'       => 'int',
			'furl'           => 'url',
			'fkey'           => 'string',
			'copyright'      => 'string',
			'author'         => 'string',
			'podcats'        => 'podcats',
			'lastbuild'      => 'string',
			'etag'           => 'string',
			'owner'          => 'owner',
			'items'          => 'none',
			'seasons'        => 'arrString',
			'categories'     => 'arrString',
			'total'          => 'int',
			'funding'        => 'arrUrlStr',
			'cache_duration' => 'int',
			'is_active'      => 'bool',
			'release_cycle'  => 'int',
			'last_released'  => 'int',
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
}
