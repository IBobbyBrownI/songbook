/* <song-transposer> — клиентское транспонирование поверх серверного
   chord-sheet. Light DOM (читает серверные .chord спаны-потомки и
   применяет к ним токен-стили).

   Renderer (бэк) не отдаёт data-атрибутов, и трогать его нельзя — поэтому
   оригинал каждого аккорда сохраняется на клиенте в dataset.chordOrig, и
   каждый сдвиг считается ОТ ОРИГИНАЛА (без дрейфа Bb→A#→…).

   Прогрессивное улучшение: до апгрейда серверный лист уже виден; элемент
   добавляет тулбар. Безопасность: правится только textContent аккордов
   (клонированный доверенный DOM), никаких innerHTML-строк. */

import { LitElement, html } from 'lit';
import { transposeChord } from '../music/chords.js';

class SongTransposer extends LitElement {
    static properties = { offset: { state: true } };

    createRenderRoot() { return this; }

    constructor() {
        super();
        this.offset = 0;
        this._fragment = null;
        this._captured = false;
        this._slug = '';
        this._key = '';
    }

    connectedCallback() {
        super.connectedCallback();
        if (this._captured) return;
        this._captured = true;

        this._slug = this.dataset.slug || '';
        this._key = (this.dataset.key || '').trim();

        // Серверный лист → открепленный фрагмент = источник истины.
        this._fragment = document.createDocumentFragment();
        while (this.firstChild) this._fragment.appendChild(this.firstChild);
        this._fragment.querySelectorAll('.chord').forEach((c) => {
            c.dataset.chordOrig = c.textContent;
        });

        const saved = parseInt(localStorage.getItem('songbook-transpose:' + this._slug) ?? '', 10);
        if (!Number.isNaN(saved)) this.offset = (((saved % 12) + 12) % 12);
    }

    _shift(delta) {
        this.offset = ((((this.offset + delta) % 12) + 12) % 12);
        this._persist();
    }

    _reset() { this.offset = 0; this._persist(); }

    _persist() {
        try { localStorage.setItem('songbook-transpose:' + this._slug, String(this.offset)); }
        catch (e) { /* приватный режим */ }
    }

    _displayKey() {
        if (!this._key) return this.offset ? '+' + this.offset : 'ориг.';
        const k = this.offset ? transposeChord(this._key, this.offset) : this._key;
        return this.offset ? `${k} (+${this.offset})` : k;
    }

    render() {
        return html`
            <div class="transpose-bar toolbar" role="group" aria-label="Транспонирование">
                <button class="btn btn--ghost btn--sm" @click=${() => this._shift(-1)} aria-label="Ниже на полутон">−½</button>
                <span class="chip transpose-key" aria-live="polite">${this._displayKey()}</span>
                <button class="btn btn--ghost btn--sm" @click=${() => this._shift(1)} aria-label="Выше на полутон">+½</button>
                <button class="btn btn--ghost btn--sm" @click=${() => this._reset()} ?hidden=${this.offset === 0}>Оригинал</button>
            </div>
            <div class="chord-sheet"></div>`;
    }

    updated() {
        const mount = this.querySelector('.chord-sheet');
        if (!mount || !this._fragment) return;
        const clone = this._fragment.cloneNode(true);
        clone.querySelectorAll('.chord').forEach((c) => {
            const orig = c.dataset.chordOrig ?? c.textContent;
            c.textContent = this.offset === 0 ? orig : transposeChord(orig, this.offset);
        });
        mount.replaceChildren(clone);
    }
}

customElements.define('song-transposer', SongTransposer);
