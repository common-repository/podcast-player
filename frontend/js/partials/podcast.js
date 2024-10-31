import props from './variables';
import PodcastHeader from './header';
import MediaElements from './media';
import PlayEpisode from './play';
import LoadMore from './load';
import SearchEpisodes from './search';

class Podcast {

	/**
	 * Podcast player width.
	 */
	width;

	/**
	 * Screen resize timeout.
	 */
	resizeTimeout = null;

	/**
	 * The constructor function.
	 *
	 * @since 1.3
	 *
	 * @param {string} id Podcast player ID.
	 */
	constructor(id) {

		// Define variables.
		this.id = id;

		// Player style update on first load.
		this.playerStyleUpdate();

		// Podcast player header elements.
		this.managerHeader();

		// Podcast mediaelement display
		this.mediaElements();
		
		// Podcast play episode.
		this.entryEpisodes();

		// Load more episodes.
		this.loadMore();

		// Search Episodes.
		this.searchEpisodes();

		// Register events.
		this.events();
	}

	/**
	 * Podcast events handling.
	 * 
	 * @since 1.3
	 */
	events() {

		clearTimeout(this.resizeTimeout);
		jQuery(window).on('resize', () => {
			this.resizeTimeout = setTimeout(this.playerStyleUpdate.bind(this), 100);
		});
	}

	/**
	 * Update player styles on load and screen resize.
	 *
	 * @since 1.3
	 */
	playerStyleUpdate() {

		const podcast = props[this.id].podcast;
		const width = podcast.width();
		const widthClass = this.getWidthClass(width);

		props.isLrgScrn = !window.matchMedia("(max-width: 640px)").matches;
		props[this.id].isWide = width > 720;

		podcast.removeClass( 'wider-player wide-player narrow-player medium-player' )
		       .addClass(widthClass);
	}

	/**
	 * Get width class based on player width.
	 *
	 * @since 1.3
	 *
	 * @param {number} width - Player width.
	 * @return {string} Width class.
	 */
	getWidthClass(width) {
		if (width > 720) {
			return 'wider-player medium-player wide-player';
		} else if (width > 640) {
			return 'medium-player wide-player';
		} else if (width > 450) {
			return 'wide-player';
		} else if (width > 280) {
			return 'narrow-player';
		}
		return '';
	}

	/**
	 * Manage podcast player header elements.
	 * 
	 * @since 1.3
	 */
	managerHeader() {

		new PodcastHeader(this.id);
	}

	/**
	 * Manage Media elements functionality.
	 * 
	 * @since 1.3
	 */
	mediaElements() {

		new MediaElements(this.id);
	}

	/**
	 * Play episode on click of a list item.
	 * 
	 * @since 1.3
	 */
	entryEpisodes() {

		new PlayEpisode(this.id);
	}

	/**
	 * Ajax load more episodes on button click.
	 * 
	 * @since 1.3
	 */
	loadMore() {

		props[this.id].loadMore = new LoadMore(this.id);
	}

	/**
	 * Ajax live search episodes.
	 */
	searchEpisodes() {

		new SearchEpisodes(this.id);
	}
}

export default Podcast;
