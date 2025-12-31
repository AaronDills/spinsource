import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('searchAutocomplete', () => ({
    query: '',
    results: [],
    open: false,
    highlighted: -1,
    loading: false,

    async search() {
        if (this.query.length < 2) {
            this.results = [];
            this.open = false;
            return;
        }

        this.loading = true;
        try {
            const response = await axios.get('/search', {
                params: { q: this.query }
            });
            this.results = response.data;
            this.open = this.results.length > 0;
            this.highlighted = -1;
        } catch (error) {
            console.error('Search failed:', error);
            this.results = [];
        } finally {
            this.loading = false;
        }
    },

    selectResult(result) {
        if (result.type === 'album') {
            window.location.href = `/album/${result.id}`;
        } else if (result.type === 'artist') {
            window.location.href = `/artist/${result.id}`;
        }
        this.open = false;
    },

    highlightNext() {
        if (this.results.length === 0) return;
        this.highlighted = (this.highlighted + 1) % this.results.length;
    },

    highlightPrev() {
        if (this.results.length === 0) return;
        this.highlighted = this.highlighted <= 0
            ? this.results.length - 1
            : this.highlighted - 1;
    },

    selectHighlighted() {
        if (this.highlighted >= 0 && this.highlighted < this.results.length) {
            this.selectResult(this.results[this.highlighted]);
        }
    }
}));

Alpine.start();
