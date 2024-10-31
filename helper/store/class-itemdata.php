<?php
/**
 * Object to store podcast Episode feed data.
 *
 * Object will save episode level data.
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
class ItemData extends StoreBase {

	/**
	 * Holds item title.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $title;

	/**
	 * Holds item description.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $description;

	/**
	 * Holds item description.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $author;

	/**
	 * Holds item release date with offset.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $date;

	/**
	 * Holds item link.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $link;

	/**
	 * Holds item audio src.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $src;

	/**
	 * Holds item featured image url.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $featured;

	/**
	 * Holds item featured image ID.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $featured_id = 0;

	/**
	 * Holds item media type.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $mediatype;

	/**
	 * Holds item iTunes episode number.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $episode;

	/**
	 * Holds item iTunes season number.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $season;

	/**
	 * Holds item categories.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $categories;

	/**
	 * Holds item unique ID.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $episode_id;

	/**
	 * Holds item play duration.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    int
	 */
	protected $duration;

	/**
	 * Holds item episode type.
	 *
	 * @since  7.3.0
	 * @access protected
	 * @var    string
	 */
	protected $episodetype;

	/**
	 * Holds episode import post ID (If imported).
	 *
	 * @since  7.4.0
	 * @access protected
	 * @var    int
	 */
	protected $post_id;

	/**
	 * Holds episode transcript urls (Podcasting 2.0 format).
	 *
	 * @since  7.4.0
	 * @access protected
	 * @var    array
	 */
	protected $transcript;
	
	/**
	 * Holds episode chapters (Podcasting 2.0 format).
	 *
	 * @since  7.4.0
	 * @access protected
	 * @var    array
	 */
	protected $chapters;

	/**
	 * Get escape functions.
	 *
	 * @since 1.0.0
	 */
	protected function typeDeclaration() {
		// Data type declaration for safe and proper data output.
		return array(
			'title'       => 'title',
			'description' => 'desc',
			'author'      => 'string',
			'date'        => 'date',
			'link'        => 'url',
			'src'         => 'url',
			'featured'    => 'url',
			'featured_id' => 'int',
			'mediatype'   => 'string',
			'episode'     => 'string',
			'season'      => 'int',
			'categories'  => 'arrString',
			'episode_id'  => 'episodeid',
			'duration'    => 'dur',
			'episodetype' => 'string',
			'post_id'     => 'int',
			'transcript'  => 'transcript',
			'chapters'    => 'arrUrlStr',
		);
	}

	/**
	 * Retrieve podcast feed data as an array.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context Retrieve Context.
	 * @param array  $fields  Fields to retrieve.
	 */
	public function retrieve( $context = 'echo', $fields = array() ) {
		$all_fields = $this->typeDeclaration();
		if ( ! empty( $fields ) ) {
			$all_fields = array_intersect_key( $all_fields, array_flip( $fields ) );
		}
		$field_keys = array_keys( $all_fields );
		return $this->get( $field_keys, $context );
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
