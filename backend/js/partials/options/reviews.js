class Reviews {

	/**
	 * Manage Podcast Review options.
	 * 
	 * @since 7.3
	 */
	constructor() {

		// Define variables.
		this.data = window.ppjsAdminOpt || {};
		this.container = jQuery('.pp-toolkit-reviews');
		this.feedBack = this.container.find('.pp-toolkit-feedback');
		this.reviewData = false;

		// Run methods.
		this.events();
	}

	// Event handling.
	events() {
		const _this = this;
		const importSelect  = this.container.find('.select-pp-feed-index');
		const refreshButton = this.container.find('.podcast-reviews-refresh');
		const deleteButton  = this.container.find('.podcast-reviews-delete');

		importSelect.on( 'change', function() {
			const $this = jQuery(this);
			const sVal = $this.val();
			const inputVal = '';
			if (sVal) {
				_this.ajaxRequest( $this, sVal );
			}
		});

		refreshButton.on( 'click', function() {
			const $this = jQuery(this);
			_this.refreshReviews( $this );
		});

		deleteButton.on( 'click', function() {
			const $this = jQuery(this);
			_this.deleteReviews( $this );
		});
	}

	/**
	 * Feed editor Ajax.
	 * 
	 * @since 2.0
	 */
	ajaxRequest(aButton, sVal) {
		const reviewForm = this.container.find('.pp-toolkit-review-form');
		this.response(this.data.messages.fetchId, 'pp-running');
		const ajaxConfig = {
			action  : 'pp_fetch_appleid',
			security: this.data.security,
			feedUrl : sVal ? sVal : '',
			rFrom: sVal ? 'indexKey' : '',
		};

		this.updateCheckboxValues( '', [] );
		reviewForm.removeClass('pp-review-success pp-review-error').hide();

		// Let's get next set of episodes.
		jQuery.ajax( {
			url: this.data.ajaxurl,
			data: ajaxConfig,
			type: 'POST',
			timeout: 60000,
			success: response => {
				const details = JSON.parse( response );
				if (!jQuery.isEmptyObject(details)) {
					const status = details.status;
					if ( 'undefined' === typeof details.status ) {
						reviewForm.addClass('pp-review-error').show();
						this.response('', false);
					} else if ( status === 'available' ) {
						const appleId = details.id;
						const ccodes  = details.ccode;
						const message = details.message;
						this.updateCheckboxValues( appleId, ccodes );
						reviewForm.addClass('pp-review-success').show();
						this.response(message, 'pp-success');
					} else if ( status === 'error' ) {
						reviewForm.addClass('pp-review-error').show();
						this.response(details.message, 'pp-error');
					} else if ( status === 'fresh_fetch' ) {
						this.fetchReviews(details.id, details.ccode, sVal);
					}
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				this.response(errorThrown, 'pp-error');
			}
		} );
	}

	fetchReviews(appleId, reviewCountries, podcast) {
		const reviewForm = this.container.find('.pp-toolkit-review-form');
		const countryCodes = Object.keys(reviewCountries);
		if (!countryCodes.length) {
			this.response('', false);
			const appleId = this.reviewData.id;
			const ccodes  = this.reviewData.ccode;
			const message = this.reviewData.message;
			this.updateCheckboxValues( appleId, ccodes );
			reviewForm.addClass('pp-review-success').show();
			this.response(message, 'pp-success');
			return;
		}
		const firstCountry = countryCodes.shift();
		const firstName    = reviewCountries[firstCountry];
		// Delete first country code from the object.
		delete reviewCountries[firstCountry];
		const ajaxConfig = {
			action  : 'pp_fetch_reviews',
			security: this.data.security,
			appleid : appleId,
			ccode   : firstCountry,
			podcast : podcast
		};

		this.response(this.data.messages.fetchReviews + ' ' + firstName + '...', 'pp-running');

		// Let's get next set of episodes.
		jQuery.ajax( {
			url: this.data.ajaxurl,
			data: ajaxConfig,
			type: 'POST',
			timeout: 60000,
			success: response => {
				const details = JSON.parse( response );
				if (!jQuery.isEmptyObject(details)) {
					if ('undefined' !== typeof details.error) {
						this.response(details.error, 'pp-error');
					} else if ('undefined' !== typeof details.data) {
						if ( details.data ) {
							this.reviewData = details.data;
						}
						this.fetchReviews(appleId, reviewCountries, podcast);
					}
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				this.response(errorThrown, 'pp-error');
			}
		} );
	}

	deleteReviews(button) {
		const wrapper    = button.parents('.pp-toolkit-content');
		const podcast    = wrapper.find('.select-pp-feed-index').val();
		const reviewForm = wrapper.find('.pp-toolkit-review-form');

		const ajaxConfig = {
			action  : 'pp_delete_reviews',
			security: this.data.security,
			podcast : podcast
		};

		this.response(this.data.messages.deleteReviews + '...', 'pp-running');

		// Let's get next set of episodes.
		jQuery.ajax( {
			url: this.data.ajaxurl,
			data: ajaxConfig,
			type: 'POST',
			timeout: 60000,
			success: response => {
				const details = JSON.parse( response );
				if (!jQuery.isEmptyObject(details)) {
					if ('undefined' !== typeof details.error) {
						this.response(details.error, 'pp-error');
					} else if ('undefined' !== typeof details.message) {
						this.response(details.message, 'pp-success');
						this.updateCheckboxValues( '', [] );
						reviewForm.hide();
						wrapper.find('.select-pp-feed-index').val('');
					}
				}
			},
			error: (jqXHR, textStatus, errorThrown) => {
				this.response(errorThrown, 'pp-error');
				this.updateCheckboxValues( '', [] );
				reviewForm.hide();
			}
		} );
	}

	/**
	 * Refresh or Fetch fresh reviews.
	 *
	 * @since 7.3.0
	 *
	 * @param object button
	 */
	refreshReviews( button ) {
		const wrapper  = button.parents('.pp-toolkit-content');
		const podcast  = wrapper.find('.select-pp-feed-index').val();
		const appleId  = wrapper.find('.pp-apple-podcast-url-input').val();
		const selectedCheckboxes = wrapper.find('.pp-multicheckbox-container .pp-select-country li input:checked');
		let ccodes = {};

		selectedCheckboxes.each(function() {
			const checkbox = jQuery(this);
			const value = checkbox.val();
			const label = checkbox.closest('label').find('.pp-label').text().trim();
			ccodes[value] = label;
		});

		if ( ! podcast ) {
			this.response('Please Select a Valid Podcast.', 'pp-error');
		}

		if ( ! appleId ) {
			this.response('Please Enter a Valid Apple ID.', 'pp-error');
		}

		if ( ! ccodes.length ) {
			this.response('Please select at least one country.', 'pp-error');
		}
		console.log(appleId, ccodes, podcast);
		this.fetchReviews(appleId, ccodes, podcast);
	}

	/**
	 * Display request feedback.
	 * 
	 * @since 3.3.0
	 * 
	 * @param string message
	 * @param string type
	 */
	response(message = '', type = false) {
		if (type) {
			this.feedBack.removeClass('pp-error pp-success pp-running').addClass(type);
			this.feedBack.find('.pp-feedback').text(message);
		} else {
			this.feedBack.removeClass('pp-error pp-success pp-running');
		}
	}

	/**
	 * Update Form Values.
	 *
	 * @since 7.3.0
	 *
	 * @param string appleId Apple ID
	 * @param array  selectedValues Selected Countries
	 */
	updateCheckboxValues( appleId, selectedValues ) {
		const muCheckboxes = this.container.find( '.pp-multicheckbox-container .pp-select-country' );
		const input        = this.container.find( '.pp-apple-podcast-url-input' );
		
		input.val( appleId );
		muCheckboxes.find('input[type="checkbox"]').prop('checked', false);
		selectedValues.forEach( selectedValue => {
			selectedValue = selectedValue.toUpperCase();
			muCheckboxes.find( `input[value="${ selectedValue }"]` ).prop( 'checked', true );
		});

		// Move selected checkboxes to the top of the list.
		muCheckboxes.find( 'input[type="checkbox"]:checked' ).closest('li').prependTo( muCheckboxes );
	}
}

export default Reviews;
