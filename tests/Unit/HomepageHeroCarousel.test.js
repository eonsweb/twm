import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/hero-carousel.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '').replace('export function', 'function');

function setup({ count = 2, videoAt = -1, reduced = false, blocked = false } = {}) {
    const target = (extra = {}) => ({
        listeners: {},
        addEventListener(name, handler) { this.listeners[name] = handler; },
        removeEventListener(name) { delete this.listeners[name]; },
        setAttribute(name, value) { this[name] = value; },
        ...extra,
    });
    const video = {
        dataset: { src: '/library/video.mp4' }, src: '', readyState: 1, currentTime: 0,
        playCount: 0, paused: true,
        pause() { this.paused = true; }, load() {},
        play() { this.playCount++; this.paused = false; return blocked ? Promise.reject(new Error('blocked')) : Promise.resolve(); },
        closest() { return { dataset: { heroMedia: 'all' } }; },
    };
    const slides = Array.from({ length: count }, (_, index) => target({
        classList: { toggle() {} },
        querySelectorAll: () => index === videoAt ? [video] : [],
    }));
    const button = target();
    const element = target({
        querySelectorAll: (selector) => selector === '.swiper-slide' ? slides : videoAt >= 0 ? [video] : [],
        querySelector: (selector) => selector === '[data-hero-motion]' ? button : selector === 'video' ? videoAt >= 0 ? video : null : {},
        contains: () => false,
    });
    const document = target({ hidden: false, querySelectorAll: () => [element] });
    const queries = {};
    const timers = new Map();
    let timerId = 0;
    let swiper;
    class FakeSwiper {
        constructor(el, params) {
            swiper = this;
            this.params = params; this.slides = slides; this.activeIndex = 0; this.events = {};
            this.autoplay = { running: false, start() { this.running = true; }, stop() { this.running = false; } };
        }
        on(name, fn) { this.events[name] = fn; }
        init() { this.events.init(); }
        slideNext() { this.activeIndex = (this.activeIndex + 1) % count; this.events.slideChange(); }
        destroy() { this.destroyed = true; }
    }
    const context = vm.createContext({
        Swiper: FakeSwiper, A11y: {}, Autoplay: {}, EffectFade: {}, Keyboard: {}, Pagination: {}, document,
        clearTimeout: (id) => timers.delete(id),
        window: {
            matchMedia: (query) => queries[query] ??= target({ matches: query.includes('reduced') && reduced }),
            setTimeout: (fn, delay) => { timers.set(++timerId, { fn, delay }); return timerId; },
        },
    });
    vm.runInContext(source + '\ninitializeHeroCarousels();', context);
    return { swiper, slides, video, element, document, button, timers, queries };
}

test('single image disables loop autoplay and pagination', () => {
    const { swiper, button } = setup({ count: 1 });
    assert.equal(swiper.params.loop, false);
    assert.equal(swiper.params.autoplay, false);
    assert.equal(swiper.params.pagination, false);
    assert.equal(swiper.autoplay.running, false);
    assert.equal(button.hidden, true);
});

test('reduced motion disables automatic slide and video playback', () => {
    const { swiper, video, button } = setup({ videoAt: 0, reduced: true });
    assert.equal(swiper.params.speed, 0);
    assert.equal(swiper.autoplay.running, false);
    assert.equal(video.playCount, 0);
    assert.equal(button['aria-pressed'], 'true');
});

test('videos load only on activation and finish before advancing', async () => {
    const { swiper, video, slides, element } = setup({ videoAt: 1 });
    assert.equal(video.src, '');
    assert.equal(swiper.autoplay.running, true);
    swiper.slideNext();
    await Promise.resolve();
    assert.equal(video.src, '/library/video.mp4');
    assert.equal(video.preload, 'metadata');
    assert.equal(video.muted, true);
    assert.equal(video.loop, false);
    assert.equal(swiper.autoplay.running, false);
    assert.equal(slides[0].inert, true);
    element.listeners.ended({ target: video });
    assert.equal(swiper.activeIndex, 0);
    assert.equal(video.paused, true);
    assert.equal(swiper.autoplay.running, true);
});

test('blocked playback advances with a fallback timer', async () => {
    const { timers, swiper } = setup({ videoAt: 0, blocked: true });
    await new Promise(setImmediate);
    const fallback = [...timers.values()].find((timer) => timer.delay === 6500);
    assert.ok(fallback);
    fallback.fn();
    assert.equal(swiper.activeIndex, 1);
});

test('hover focus and navigation stop motion and clean up listeners', async () => {
    const { element, document, swiper, video } = setup({ videoAt: 0 });
    await Promise.resolve();
    element.listeners.mouseenter();
    assert.equal(video.paused, true);
    element.listeners.mouseleave();
    await Promise.resolve();
    assert.equal(video.paused, false);
    element.listeners.focusin();
    assert.equal(video.paused, true);
    document.listeners['livewire:navigating']();
    assert.equal(swiper.destroyed, true);
    assert.equal(document.listeners.visibilitychange, undefined);
});
