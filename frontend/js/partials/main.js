import props from './variables';
import Podcast from './podcast';
import Modal from './modal';
import MediaElem from './mediaelem';

( $ => {

	'use strict';

	const podcasts = $( '.pp-podcast' );
	const spodcast = $( '.pp-social-shared' ).first();
	const settings = window.ppmejsSettings || {};
	const modal = settings.isPremium ? new Modal() : '';
	const isMejs = settings.isMeJs;
	let timeOut = false;

	setTimeout(() => {timeOut = true}, 3000);

	podcasts.each( function() {
		const podcast = $(this);
		createPodcast(podcast);
	} );

	document.addEventListener('animationstart', playerAdded, false); // Standard + firefox
	document.addEventListener('MSAnimationStart', playerAdded, false); // IE
	document.addEventListener('webkitAnimationStart', playerAdded, false); // Chrome + Safari

	function playerAdded(e) {
		const podcast = $(e.target);
		if (!podcast.hasClass('pp-podcast') || podcast.hasClass('pp-podcast-added')) {
			return;
		}
		createPodcast(podcast);
	}

	function createPodcast(podcast) {
		if (podcast.hasClass('pp-podcast-added')) return;

		const id = podcast.attr('id');
		const hasParentPodcast = podcast.parents('.pp-podcast').length;
		if (hasParentPodcast) return;

		podcast.find('.pp-podcast').remove();

		if (isMejs && 'undefined' === typeof MediaElementPlayer) {
			if (!timeOut) setTimeout(() => createPodcast(podcast), 200);
			return;
		}

		const idPlayer = id + '-player';
		const mediaObj = isMejs ? new MediaElementPlayer(idPlayer, settings) : new MediaElem(idPlayer);
		if (!mediaObj) return;

		const list = podcast.find('.pod-content__list');
		const episode = podcast.find('.pod-content__episode');
		const episodes = list.find('.episode-list__wrapper');
		const single = episode.find('.episode-single__wrapper');
		const singleWrap = podcast.find('.pp-podcast__single').first();
		const player = podcast.find('.pp-podcast__player');
		const amsg = podcast.find('.pp-player__amsg');
		const fetched = false;
		const msgMediaObj = amsg.length ? (isMejs ? new MediaElementPlayer(id + '-amsg-player', settings) : new MediaElem(id + '-amsg-player')) : false;

		if ('undefined' === typeof props.podcastPlayerData[id]) {
			const pdata = podcast.data("ppsdata");
			if (pdata) props.podcastPlayerData[id] = pdata;
			else return;
		}

		const instance = id.replace('pp-podcast-', '');
		props[id] = {podcast, mediaObj, settings, list, episode, msgMediaObj, amsg, episodes, single, player, modal, singleWrap, fetched, instance};
		podcast.addClass('pp-podcast-added');
		new Podcast(id);
	}

	if ( spodcast.length ) $( 'html, body' ).animate({ scrollTop: spodcast.offset().top - 200 }, 400 );
	if ( settings.isPremium && settings.isSticky ) $(window).on('scroll', props.stickyonScroll.bind(props));
})(jQuery);
