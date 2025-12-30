import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('searchAutocomplete', () => ({
    query: '',
    results: [],
    open: false,
    loading: false,
    highlighted: -1,

    async search() {
        if (this.query.length < 2) {
            this.results = [];
            this.open = false;
            return;
        }

        this.loading = true;

        try {
            const response = await fetch(`/search?q=${encodeURIComponent(this.query)}`);
            this.results = await response.json();
            this.open = this.results.length > 0;
            this.highlighted = -1;
        } catch (error) {
            console.error('Search error:', error);
            this.results = [];
        } finally {
            this.loading = false;
        }
    },

    highlightNext() {
        if (this.highlighted < this.results.length - 1) {
            this.highlighted++;
        }
    },

    highlightPrev() {
        if (this.highlighted > 0) {
            this.highlighted--;
        }
    },

    selectHighlighted() {
        if (this.highlighted >= 0 && this.highlighted < this.results.length) {
            this.selectResult(this.results[this.highlighted]);
        }
    },

    selectResult(result) {
        console.log('Selected:', result);
        this.query = result.title;
        this.open = false;
    }
}));

Alpine.start();
