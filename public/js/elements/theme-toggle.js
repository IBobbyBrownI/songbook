/* <theme-toggle> — переключатель светлой/тёмной темы.
   Light DOM (чтобы применялись токен-стили .btn). Персист в localStorage.
   Стартовое значение темы выставляет inline-скрипт в <head> (no-FOUC). */

import { LitElement, html } from 'lit';

const KEY = 'songbook-theme';

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
                ${dark ? '☀︎' : '☾'}
            </button>`;
    }
}

customElements.define('theme-toggle', ThemeToggle);
