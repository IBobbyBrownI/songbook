/* <stage-reader> — липкая панель «режима чтения» для сцены.
   Владеет ТОЛЬКО своим тулбаром (Lit render чист, без обёртки чужого
   контента). Управляет целью по data-target (id) либо nextElementSibling:
   масштаб шрифта через --chord-scale + автоскролл. */

import { LitElement, html } from 'lit';

const SCALE_KEY = 'songbook-stage-scale';
const MIN = 0.8, MAX = 2.4;

const ICON_PLAY = html`<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l12-7.5z"/></svg>`;
const ICON_PAUSE = html`<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>`;

class StageReader extends LitElement {
    static properties = { scale: { state: true }, scrolling: { state: true } };

    createRenderRoot() { return this; }

    constructor() {
        super();
        this.scale = 1;
        this.scrolling = false;
        this._raf = 0;
        this._last = 0;
        this._target = null;
        this._wakeLock = null;
    }

    connectedCallback() {
        super.connectedCallback();
        const saved = parseFloat(localStorage.getItem(SCALE_KEY) ?? '');
        if (!Number.isNaN(saved)) this.scale = Math.min(MAX, Math.max(MIN, saved));
        this._applyScale();
    }

    disconnectedCallback() {
        super.disconnectedCallback();
        this._stop();
    }

    get target() {
        if (this._target && this._target.isConnected) return this._target;
        const id = this.dataset.target;
        this._target = id ? document.getElementById(id) : this.nextElementSibling;
        return this._target;
    }

    _applyScale() {
        if (this.target) this.target.style.setProperty('--chord-scale', String(this.scale));
        try { localStorage.setItem(SCALE_KEY, String(this.scale)); } catch (e) { /* noop */ }
    }

    _bump(delta) {
        this.scale = Math.min(MAX, Math.max(MIN, Math.round((this.scale + delta) * 100) / 100));
        this._applyScale();
    }

    _toggleScroll() { this.scrolling ? this._stop() : this._start(); }

    _start() {
        this.scrolling = true;
        this._last = 0;
        const step = (ts) => {
            if (!this.scrolling) return;
            if (this._last) window.scrollBy(0, (ts - this._last) * 0.03); // ~px/ms
            this._last = ts;
            this._raf = requestAnimationFrame(step);
        };
        this._raf = requestAnimationFrame(step);
        this._requestWake();
    }

    _stop() {
        this.scrolling = false;
        if (this._raf) cancelAnimationFrame(this._raf);
        this._raf = 0;
        this._releaseWake();
    }

    async _requestWake() {
        try { if ('wakeLock' in navigator) this._wakeLock = await navigator.wakeLock.request('screen'); }
        catch (e) { /* not supported / denied */ }
    }

    _releaseWake() {
        try { this._wakeLock?.release?.(); } catch (e) { /* noop */ }
        this._wakeLock = null;
    }

    render() {
        return html`
            <div class="toolbar" role="group" aria-label="Режим чтения">
                <button class="btn btn--ghost btn--sm" @click=${() => this._bump(-0.1)} aria-label="Меньше шрифт">A−</button>
                <span class="stage-scale-value">${Math.round(this.scale * 100)}%</span>
                <button class="btn btn--ghost btn--sm" @click=${() => this._bump(0.1)} aria-label="Больше шрифт">A+</button>
                <button class="btn ${this.scrolling ? 'btn--primary' : 'btn--ghost'} btn--sm"
                        @click=${() => this._toggleScroll()} aria-pressed=${this.scrolling}>
                    ${this.scrolling ? ICON_PAUSE : ICON_PLAY}<span>${this.scrolling ? 'Стоп' : 'Автоскролл'}</span>
                </button>
            </div>`;
    }
}

customElements.define('stage-reader', StageReader);
