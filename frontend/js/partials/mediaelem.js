import { audioPlayer } from "../templates/audioplayer";
import { _$ } from "./pplib";

class MediaElem {
    /**
	 * Wrapper Element ID.
	 */
	id = null;

    /**
	 * Wrapper Element.
	 */
	wrapper = null;

    /**
     * Audio Element Object.
     */
    media = null;

    /**
     * Controls Element.
     */
    controls = null;

    /**
     * Static property to keep track of the currently playing instance.
     */
    static currentlyPlayingInstance = null;

    /**
     * Create custom media element for audio files.
     *
     * @since 6.7.0
     */
    /**
     * Create custom media element for audio files.
     *
     * @since 6.7.0
     */
    constructor(id) {
        this.id = id;
        this.media = document.querySelector(`#${id}`);
        if (!this.media || !this.isValidMediaElement(this.media)) return false;
        
        this.wrapper = this.media.parentElement;
        this.createAudioMarkup();
        this.attachEvents();
    }

    isValidMediaElement(mediaElement) {
        if (mediaElement.tagName === 'AUDIO') return true;
        if (mediaElement.tagName === 'MEDIAELEMENTWRAPPER') {
            this.removeMeJs();
            this.media = document.querySelector(`#${this.id}`);
            return !!this.media;
        }
        return false;
    }

    attachEvents() {
        const seekSlider = this.controls.querySelector('.ppjs__seek-slider');
        const currentTimeContainer = this.controls.querySelector('.ppjs__currenttime');
        const updateCurrentTime = () => {
            if (this.media.readyState > 0) {
                currentTimeContainer.textContent = this.formatTime(seekSlider.value);
            }
        };
        const seekToTime = () => {
            if (this.media.readyState > 0) {
                this.media.currentTime = seekSlider.value;
            }
        };
        const setCurrentlyPlayingInstance = () => {
            if (MediaElem.currentlyPlayingInstance && MediaElem.currentlyPlayingInstance !== this) {
                MediaElem.currentlyPlayingInstance.media.pause();
            }
            MediaElem.currentlyPlayingInstance = this;
        };
        const updateSlider = () => {
            const { duration, currentTime, buffered } = this.media;
            const percentPlayed = (currentTime / duration) * 100;

            seekSlider.value = Math.floor(currentTime);
            currentTimeContainer.textContent = this.formatTime(currentTime);
            seekSlider.style.setProperty('--pp-progress-value', `${percentPlayed}%`);

            if (duration > 0 && buffered.length) {
                const percentBuffered = (buffered.end(buffered.length - 1) / duration) * 100;
                seekSlider.style.setProperty('--buffered-width', `${percentBuffered}%`);
            }
        };

        if (this.media.readyState > 0) {
            this.displayDuration();
            this.setSliderMax();
        } else {
            this.media.addEventListener('loadedmetadata', () => {
                this.displayDuration();
                this.setSliderMax();
            });
        }

        seekSlider.addEventListener('input', updateCurrentTime);
        seekSlider.addEventListener('change', seekToTime);
        this.media.addEventListener('play', setCurrentlyPlayingInstance);
        this.media.addEventListener('timeupdate', updateSlider);
    }

    /**
     * Create audio markup.
     */
    createAudioMarkup() {
        const markup = _$.template( audioPlayer(), { id: this.id } );
        this.wrapper.insertAdjacentHTML('beforeend', markup);
        const mediaContainer = this.wrapper.querySelector(`.ppjs__mediaelement`);
        mediaContainer.appendChild(this.media);
        this.controls = this.wrapper.querySelector('.ppjs__controls');
    }

    /**
     * Set the source URL for the audio element.
     * @param {string} url - The URL of the audio file.
     */
    setSrc(url) {
        this.media.src = url;
    }

    /**
     * Get media src.
     */
    getSrc() {
        return this.media.src;
    }

    /**
     * Load the audio.
     */
    load() {
        this.media.load();
    }

    formatTime(secs) {
        if (!secs || isNaN(secs)) return '00:00';
        const hours = Math.floor(secs / 3600);
        const minutes = Math.floor((secs % 3600) / 60);
        const seconds = Math.floor(secs % 60);

        return (hours ? `${hours}:` : '') + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    };

    displayDuration() {
        const durationContainer = this.controls.querySelector('.ppjs__duration');
        if (durationContainer) {
            durationContainer.textContent = this.formatTime(this.media.duration);
        }
    };
    
    setSliderMax() {
        const secs = this.media.duration;
        if (isNaN(secs)) return;
        const seekSlider = this.controls.querySelector('.ppjs__seek-slider');
        seekSlider.max = Math.floor(secs);
    }


    removeMeJs() {
        const mejs = this.media.closest('.mejs__container');
        if (mejs && mejs.id && window.mejs?.players?.[mejs.id]?.remove) {
            window.mejs.players[mejs.id].remove();
        }
    }
}
export default MediaElem;
