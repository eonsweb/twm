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
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-text]');

    if (!button) {
        return;
    }

    await navigator.clipboard.writeText(button.dataset.copyText);
    button.textContent = 'Copied';
});
