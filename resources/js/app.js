document.addEventListener('alpine:init', () => {
    window.Alpine.data('navbar', () => ({
        open: false,
        isScrolled: false,
        scrollHandler: null,

        init() {
            this.updateNavbar();
            this.scrollHandler = () => this.updateNavbar();

            window.addEventListener('scroll', this.scrollHandler, { passive: true });
        },

        destroy() {
            window.removeEventListener('scroll', this.scrollHandler);
        },

        updateNavbar() {
            this.isScrolled = window.scrollY > 20;
        },
    }));

    window.Alpine.data('heroParallax', () => ({
        frame: null,
        scrollHandler: null,
        motionQuery: null,

        init() {
            this.motionQuery = window.matchMedia('(prefers-reduced-motion: reduce), (max-width: 767px)');
            this.scrollHandler = () => this.scheduleUpdate();

            window.addEventListener('scroll', this.scrollHandler, { passive: true });
            window.addEventListener('resize', this.scrollHandler, { passive: true });
            this.motionQuery.addEventListener('change', this.scrollHandler);
            this.scheduleUpdate();
        },

        destroy() {
            window.removeEventListener('scroll', this.scrollHandler);
            window.removeEventListener('resize', this.scrollHandler);
            this.motionQuery?.removeEventListener('change', this.scrollHandler);

            if (this.frame !== null) {
                window.cancelAnimationFrame(this.frame);
            }
        },

        scheduleUpdate() {
            if (this.frame !== null) {
                return;
            }

            this.frame = window.requestAnimationFrame(() => {
                this.frame = null;
                this.updateImage();
            });
        },

        updateImage() {
            const image = this.$refs.image;

            if (! image || this.motionQuery.matches) {
                if (image) {
                    image.style.transform = 'translate3d(0, 0, 0)';
                }

                return;
            }

            const hero = this.$el.getBoundingClientRect();
            const movement = Math.max(0, Math.min(-hero.top * 0.12, hero.height * 0.05));

            image.style.transform = `translate3d(0, ${movement}px, 0)`;
        },
    }));
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-text]');

    if (!button) {
        return;
    }

    await navigator.clipboard.writeText(button.dataset.copyText);
    button.textContent = 'Copied';
});
