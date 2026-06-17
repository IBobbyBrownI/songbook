/* <theme-toggle> — переключатель светлой/тёмной темы.
   Light DOM (чтобы применялись токен-стили .btn). Персист в localStorage.
   Стартовое значение темы выставляет inline-скрипт в <head> (no-FOUC). */

import { LitElement, html } from 'lit';

const KEY = 'songbook-theme';

const ICON_SUN = html`<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="4"/>
    <path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.4 1.4M17.6 17.6 19 19M19 5l-1.4 1.4M6.4 17.6 5 19"/>
</svg>`;
const ICON_MOON = html`<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3 6.6 6.6 0 0 0 21 12.8z"/>
</svg>`;

function effectiveTheme() {
    const explicit = document.documentElement.dataset.theme;
    if (explicit) return explicit;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

class ThemeToggle extends LitElement {
    static properties = { theme: { state: true } };

    createRenderRoot() { return this; }

    constructor() {
        super();
        this.theme = effectiveTheme();
    }

    _apply(theme) {
        this.theme = theme;
        document.documentElement.dataset.theme = theme;
        try { localStorage.setItem(KEY, theme); } catch (e) { /* приватный режим */ }
    }

    _toggle() {
        this._apply(this.theme === 'dark' ? 'light' : 'dark');
    }

    render() {
        const dark = this.theme === 'dark';
        return html`
            <button class="btn btn--ghost btn--sm" @click=${this._toggle}
                    aria-pressed=${dark}
                    title="Переключить тему"
                    aria-label=${dark ? 'Включить светлую тему' : 'Включить тёмную тему'}>
                ${dark ? ICON_SUN : ICON_MOON}
            </button>`;
    }
}

customElements.define('theme-toggle', ThemeToggle);
