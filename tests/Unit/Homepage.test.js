import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/homepage.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '').replaceAll('export function', 'function');

function setup({ reduced = false, rejected = false, empty = false } = {}) {
    const target = (extra = {}) => ({
        listeners: {},
        addEventListener(name, handler) { this.listeners[name] = handler; },
        removeEventListener(name) { delete this.listeners[name]; },
        setAttribute(name, value) { this[name] = value; },
        ...extra,
    });
    const button = target();
    const motion = target({ matches: reduced });
    const video = target({
        dataset: { src: '/library/worship.mp4', type: 'video/mp4' },
        paused: true, playCount: 0, sources: [],
        pause() { this.paused = true; }, load() {},
        play() { this.playCount++; this.paused = false; return rejected ? Promise.reject(new Error('blocked')) : Promise.resolve(); },
        append(source) { this.sources.push(source); source.remove = () => { this.sources = this.sources.filter(item => item !== source); }; },
        querySelector() { return this.sources[0]; },
        closest() { return { querySelector() { return button; } }; },
    });
    const slider = { closest() { return { querySelector() { return {}; } }; } };
    const instances = [];
    const document = target({
        hidden: false,
        createElement() { return {}; },
        querySelectorAll(selector) { return empty ? [] : selector === '[data-messages-swiper]' ? [slider] : [video]; },
    });
    class Swiper {
        constructor(element, options) { this.options = options; instances.push(this); }
        destroy() { this.destroyed = true; }
    }
    const context = vm.createContext({ Swiper, A11y: {}, Keyboard: {}, Navigation: {}, document, window: { matchMedia() { return motion; } } });
    vm.runInContext(source, context);
    context.initializeHomepage();
    return { context, instances, document, video, button, motion };
}

test('navigation and DOM ready do not duplicate message sliders or video playback', () => {
    const { context, instances, video, document } = setup();
    document.listeners['DOMContentLoaded']();
    document.listeners['livewire:navigated']();
    assert.equal(instances.length, 1);
    assert.equal(video.playCount, 1);
    assert.equal(video.sources.length, 1);
    document.listeners['livewire:navigating']();
    assert.equal(instances[0].destroyed, true);
    assert.equal(video.paused, true);
    assert.equal(document.listeners.visibilitychange, undefined);
    context.initializeHomepage();
    assert.equal(instances.length, 2);
});

test('message slider has responsive touch and keyboard controls', () => {
    const { instances } = setup();
    const options = instances[0].options;
    assert.equal(options.slidesPerView, 1.08);
    assert.equal(options.breakpoints[640].slidesPerView, 2);
    assert.equal(options.breakpoints[1024].slidesPerView, 3);
    assert.equal(options.keyboard.enabled, true);
    assert.equal(options.a11y.enabled, true);
    assert.ok(options.navigation.nextEl);
});

test('reduced motion defers the video source until explicitly played', () => {
    const { video, button, motion, instances } = setup({ reduced: true });
    assert.equal(video.sources.length, 0);
    assert.equal(video.playCount, 0);
    assert.equal(instances[0].options.speed, 0);
    assert.equal(button['aria-pressed'], 'true');
    button.listeners.click();
    assert.equal(video.sources[0].src, '/library/worship.mp4');
    assert.equal(video.muted, true);
    motion.listeners.change();
    assert.equal(video.paused, true);
});

test('blocked playback keeps the poster and offers an explicit play action', async () => {
    const { button, video } = setup({ rejected: true });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(button.textContent, 'Play video');
    assert.equal(video.paused, true);
});

test('failed media reveals the fallback and hides the unusable control', () => {
    const { video, button } = setup();
    video.listeners.error();
    assert.equal(video.hidden, true);
    assert.equal(button.hidden, true);
    assert.equal(video.paused, true);
});

test('returning after a media failure restores the control and retries a fresh source', () => {
    const { context, video, button, document } = setup();
    video.listeners.error();
    document.listeners['livewire:navigating']();
    assert.equal(video.sources.length, 0);
    context.initializeHomepage();
    assert.equal(video.hidden, false);
    assert.equal(button.hidden, false);
    assert.equal(video.sources.length, 1);
    assert.equal(video.playCount, 2);
});

test('hidden documents pause and leaving the homepage removes listeners', () => {
    const { video, document, motion } = setup();
    document.hidden = true;
    document.listeners.visibilitychange();
    assert.equal(video.paused, true);
    document.listeners['livewire:navigating']();
    assert.equal(motion.listeners.change, undefined);
    assert.equal(video.listeners.error, undefined);
});

test('pages without homepage elements initialize safely', () => {
    const { instances } = setup({ empty: true });
    assert.equal(instances.length, 0);
});
