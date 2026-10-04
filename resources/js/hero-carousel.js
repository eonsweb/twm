import Swiper from 'swiper';
import { A11y, Autoplay, EffectFade, Keyboard, Pagination } from 'swiper/modules';
import 'swiper/css/effect-fade';
import 'swiper/css/pagination';

const cleanups = new Set();

export function initializeHeroCarousels() {
    cleanups.forEach((cleanup) => cleanup());
    cleanups.clear();

    document.querySelectorAll('[data-homepage-hero]').forEach((element) => {
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const mobile = window.matchMedia('(max-width: 767px)');
        const multiple = element.querySelectorAll('.swiper-slide').length > 1;
        const button = element.querySelector('[data-hero-motion]');
        let paused = motion.matches;
        let hovered = false;
        let focused = false;
        let currentVideo = null;
        let fallbackTimer;
        let generation = 0;
        let destroyed = false;
        const canPlay = () => !paused && !hovered && !focused && !document.hidden;
        const swiper = new Swiper(element, {
            init: false,
            modules: [A11y, Autoplay, EffectFade, Keyboard, Pagination],
            effect: 'fade',
            fadeEffect: { crossFade: true },
            loop: multiple,
            speed: motion.matches ? 0 : 900,
            autoplay: multiple ? { delay: 6500, disableOnInteraction: false } : false,
            pagination: multiple ? { el: element.querySelector('[data-hero-pagination]'), clickable: true, bulletElement: 'button' } : false,
            keyboard: { enabled: multiple, onlyInViewport: true },
            a11y: { enabled: true, paginationBulletMessage: 'Go to slide {{index}}' },
        });

        const stop = () => {
            clearTimeout(fallbackTimer);
            swiper.autoplay?.stop();
            element.querySelectorAll('video').forEach((video) => video.pause());
        };
        const fallback = () => {
            if (multiple && canPlay()) {
                fallbackTimer = window.setTimeout(() => swiper.slideNext(), 6500);
            }
        };
        const play = () => {
            stop();
            const activation = ++generation;
            if (!canPlay()) return;
            if (!currentVideo) {
                if (multiple) swiper.autoplay.start();
                return;
            }
            const video = currentVideo;
            if (!video.src) {
                video.preload = 'metadata';
                video.src = video.dataset.src;
                video.load();
            }
            video.muted = true;
            video.loop = !multiple;
            // Failed or stalled media must not trap the carousel on a slide.
            fallbackTimer = window.setTimeout(fallback, 15000);
            video.play().then(() => {
                if (destroyed || activation !== generation || !canPlay()) {
                    if (destroyed || video !== currentVideo || !canPlay()) video.pause();
                    return;
                }
                clearTimeout(fallbackTimer);
            }).catch(() => {
                if (!destroyed && activation === generation) {
                    clearTimeout(fallbackTimer);
                    fallback();
                }
            });
        };
        const activate = () => {
            stop();
            element.querySelectorAll('.swiper-slide').forEach((slide) => {
                const active = slide === swiper.slides[swiper.activeIndex];
                slide.inert = !active;
                slide.setAttribute('aria-hidden', String(!active));
                slide.classList.toggle('hero-slide-active', active);
                slide.querySelectorAll('video').forEach((video) => {
                    if (video.readyState) video.currentTime = 0;
                });
            });
            currentVideo = Array.from(swiper.slides[swiper.activeIndex]?.querySelectorAll('video') ?? []).find((video) => {
                const viewport = video.closest('[data-hero-media]').dataset.heroMedia;
                return viewport === 'all' || viewport === (mobile.matches ? 'mobile' : 'desktop');
            });
            play();
        };
        const videoEnded = (event) => {
            if (event.target === currentVideo && multiple && canPlay()) swiper.slideNext();
        };
        const videoError = (event) => {
            if (event.target === currentVideo) {
                clearTimeout(fallbackTimer);
                fallback();
            }
        };
        const updateButton = () => {
            button.hidden = !multiple && !element.querySelector('video');
            button.textContent = paused ? 'Resume motion' : 'Pause motion';
            button.setAttribute('aria-pressed', String(paused));
        };
        const toggle = () => { paused = !paused; updateButton(); play(); };
        const motionChanged = () => { paused = motion.matches; swiper.params.speed = motion.matches ? 0 : 900; updateButton(); play(); };
        const enter = () => { hovered = true; play(); };
        const leave = () => { hovered = false; play(); };
        const focusIn = () => { focused = true; play(); };
        const focusOut = (event) => { if (!element.contains(event.relatedTarget)) { focused = false; play(); } };
        swiper.on('init', activate);
        swiper.on('slideChange', activate);
        swiper.init();
        updateButton();
        button.addEventListener('click', toggle);
        element.addEventListener('mouseenter', enter);
        element.addEventListener('mouseleave', leave);
        element.addEventListener('focusin', focusIn);
        element.addEventListener('focusout', focusOut);
        element.addEventListener('ended', videoEnded, true);
        element.addEventListener('error', videoError, true);
        element.addEventListener('stalled', videoError, true);
        document.addEventListener('visibilitychange', play);
        mobile.addEventListener('change', activate);
        motion.addEventListener('change', motionChanged);
        cleanups.add(() => {
            destroyed = true;
            generation++;
            stop();
            swiper.destroy(true, true);
            button.removeEventListener('click', toggle);
            element.removeEventListener('mouseenter', enter);
            element.removeEventListener('mouseleave', leave);
            element.removeEventListener('focusin', focusIn);
            element.removeEventListener('focusout', focusOut);
            element.removeEventListener('ended', videoEnded, true);
            element.removeEventListener('error', videoError, true);
            element.removeEventListener('stalled', videoError, true);
            document.removeEventListener('visibilitychange', play);
            mobile.removeEventListener('change', activate);
            motion.removeEventListener('change', motionChanged);
        });
    });
}

document.addEventListener('livewire:navigating', () => {
    cleanups.forEach((cleanup) => cleanup());
    cleanups.clear();
});
document.addEventListener('livewire:navigated', initializeHeroCarousels);
document.addEventListener('DOMContentLoaded', initializeHeroCarousels);
