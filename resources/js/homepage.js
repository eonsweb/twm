import Swiper from 'swiper';
import { A11y, Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css/a11y';

const cleanups = new Set();
export function destroyHomepage() {
    cleanups.forEach((cleanup) => cleanup());
    cleanups.clear();
}
export function initializeHomepage() {
    destroyHomepage();
    document.querySelectorAll('[data-messages-swiper]').forEach((element) => {
        const section = element.closest('[data-message-section]');
        const swiper = new Swiper(element, {
            modules: [A11y, Keyboard, Navigation],
            slidesPerView: 1.08,
            spaceBetween: 16,
            watchOverflow: true,
            loop: false,
            grabCursor: true,
            speed: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 450,
            keyboard: { enabled: true, onlyInViewport: true },
            a11y: { enabled: true },
            navigation: {
                prevEl: section.querySelector('[data-messages-prev]'),
                nextEl: section.querySelector('[data-messages-next]'),
            },
            breakpoints: { 640: { slidesPerView: 2 }, 1024: { slidesPerView: 3, spaceBetween: 24 } },
        });
        cleanups.add(() => swiper.destroy(true, true));
    });
    document.querySelectorAll('[data-background-video]').forEach((video) => {
        const button = video.closest('[data-cinematic-hero]').querySelector('[data-video-toggle]');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let paused = motion.matches;
        let destroyed = false;
        const update = () => {
            button.textContent = paused ? 'Play video' : 'Pause video';
            button.setAttribute('aria-pressed', String(paused));
            if (paused || document.hidden || destroyed) {
                video.pause();
                return;
            }
            if (!video.querySelector('source')) {
                const source = document.createElement('source');
                source.src = video.dataset.src;
                source.type = video.dataset.type;
                video.append(source);
                video.load();
            }
            video.muted = true;
            video.play().then(() => {
                if (paused || destroyed || document.hidden) video.pause();
            }).catch(() => {
                if (!destroyed) { paused = true; update(); }
            });
        };
        const toggle = () => { paused = !paused; update(); };
        const preference = () => { paused = motion.matches; update(); };
        const failed = () => { video.hidden = true; button.hidden = true; video.pause(); };
        button.addEventListener('click', toggle);
        motion.addEventListener('change', preference);
        document.addEventListener('visibilitychange', update);
        video.addEventListener('error', failed, true);
        update();
        cleanups.add(() => {
            destroyed = true;
            video.pause();
            button.removeEventListener('click', toggle);
            motion.removeEventListener('change', preference);
            document.removeEventListener('visibilitychange', update);
            video.removeEventListener('error', failed, true);
        });
    });
}
document.addEventListener('DOMContentLoaded', initializeHomepage);
document.addEventListener('livewire:navigated', initializeHomepage);
document.addEventListener('livewire:navigating', destroyHomepage);
