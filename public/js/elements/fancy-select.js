/* <fancy-select> — доступный кастомный селект поверх нативного <select>.

   Progressive enhancement: реальный <select> остаётся в light DOM источником
   правды (сабмит формы, работа без JS, fallback при сбое апгрейда). Компонент
   прячет его и рисует свой триггер + listbox в shadow DOM. Тема — через
   наследуемые --color-* токены (custom properties проходят сквозь shadow).

   Паттерн ARIA: «select-only combobox» (APG) — фокус остаётся на кнопке-триггере,
   активная опция отмечается через aria-activedescendant. Клавиатура: ↑/↓/Home/End,
   Enter/Space — выбрать, Esc — отмена, type-ahead. */

import { LitElement, html, css, nothing } from 'lit';

const CHEVRON = html`<svg class="chev" width="16" height="16" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M6 9l6 6 6-6"/></svg>`;
const CHECK = html`<svg class="check" width="16" height="16" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M20 6L9 17l-5-5"/></svg>`;

let _uid = 0;

class FancySelect extends LitElement {
    static properties = {
        open: { state: true },
        value: { state: true },
        _active: { state: true },
        _options: { state: true },
        _dropUp: { state: true },
    };

    constructor() {
        super();
        this.open = false;
        this.value = '';
        this._active = -1;
        this._options = [];
        this._dropUp = false;
        this._uid = `fs-${++_uid}`;
        this._typeBuf = '';
        this._typeAt = 0;
        this._native = null;
        this._onDocPointer = (e) => {
            if (!this.open) return;
            if (e.composedPath().includes(this)) return;
            this._close(false);
        };
    }

    connectedCallback() {
        super.connectedCallback();
        this._native = this.querySelector('select');
        if (!this._native) return;
        this._sync();
        // визуально убираем нативный, но он остаётся в DOM (сабмит/a11y-fallback)
        this._native.setAttribute('tabindex', '-1');
        this._native.setAttribute('aria-hidden', 'true');
        this._native.style.display = 'none';
        // если value поменяли извне (например, сброс формы) — подхватываем
        this._native.addEventListener('change', () => this._sync());
        document.addEventListener('pointerdown', this._onDocPointer, true);
    }

    disconnectedCallback() {
        super.disconnectedCallback();
        document.removeEventListener('pointerdown', this._onDocPointer, true);
    }

    _sync() {
        const n = this._native;
        this._options = Array.from(n.options).map((o) => ({
            value: o.value, label: o.text, disabled: o.disabled,
        }));
        this.value = n.value;
        this.disabled = n.disabled;
    }

    get _selectedIndex() {
        return this._options.findIndex((o) => o.value === this.value);
    }

    _label() {
        const o = this._options[this._selectedIndex];
        return o ? o.label : '';
    }

    _toggle() { this.open ? this._close(false) : this._openList(); }

    _openList() {
        if (this.disabled) return;
        this.open = true;
        this._active = this._selectedIndex >= 0 ? this._selectedIndex : 0;
        // флип вверх, если снизу мало места
        requestAnimationFrame(() => {
            const r = this.getBoundingClientRect();
            this._dropUp = (window.innerHeight - r.bottom) < 260 && r.top > 260;
            this._scrollActiveIntoView();
        });
    }

    _close(restoreFocus = true) {
        this.open = false;
        if (restoreFocus) this._trigger?.focus();
    }

    get _trigger() { return this.renderRoot?.querySelector('.trigger'); }

    _commit(i) {
        const o = this._options[i];
        if (!o || o.disabled) return;
        if (o.value !== this.value) {
            this.value = o.value;
            this._native.value = o.value;
            this._native.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this._close(true);
    }

    _move(delta) {
        let i = this._active;
        const n = this._options.length;
        for (let step = 0; step < n; step++) {
            i = (i + delta + n) % n;
            if (!this._options[i].disabled) break;
        }
        this._active = i;
        this._scrollActiveIntoView();
    }

    _scrollActiveIntoView() {
        const el = this.renderRoot?.querySelector(`#${this._uid}-opt-${this._active}`);
        el?.scrollIntoView({ block: 'nearest' });
    }

    _typeAhead(ch) {
        const now = Date.now();
        this._typeBuf = (now - this._typeAt > 600 ? '' : this._typeBuf) + ch.toLowerCase();
        this._typeAt = now;
        const i = this._options.findIndex((o) => o.label.toLowerCase().startsWith(this._typeBuf));
        if (i < 0) return;
        if (this.open) { this._active = i; this._scrollActiveIntoView(); }
        else this._commit(i);
    }

    _onKey(e) {
        const k = e.key;
        if (!this.open) {
            if (k === 'ArrowDown' || k === 'ArrowUp' || k === 'Enter' || k === ' ' || k === 'Spacebar') {
                e.preventDefault(); this._openList(); return;
            }
        } else {
            switch (k) {
                case 'ArrowDown': e.preventDefault(); this._move(1); return;
                case 'ArrowUp': e.preventDefault(); this._move(-1); return;
                case 'Home': e.preventDefault(); this._active = this._firstEnabled(1); this._scrollActiveIntoView(); return;
                case 'End': e.preventDefault(); this._active = this._firstEnabled(-1); this._scrollActiveIntoView(); return;
                case 'Enter': case ' ': case 'Spacebar': e.preventDefault(); this._commit(this._active); return;
                case 'Escape': e.preventDefault(); this._close(true); return;
                case 'Tab': this._commit(this._active); return;
            }
        }
        if (k.length === 1 && !e.metaKey && !e.ctrlKey && !e.altKey) {
            e.preventDefault();
            this._typeAhead(k);
        }
    }

    _firstEnabled(dir) {
        const n = this._options.length;
        let i = dir > 0 ? 0 : n - 1;
        while (i >= 0 && i < n && this._options[i].disabled) i += dir;
        return Math.max(0, Math.min(n - 1, i));
    }

    render() {
        if (!this._native) return nothing;
        const listId = `${this._uid}-list`;
        const activeId = this.open && this._active >= 0 ? `${this._uid}-opt-${this._active}` : nothing;
        return html`
            <button class="trigger" type="button"
                    aria-haspopup="listbox" aria-expanded=${this.open ? 'true' : 'false'}
                    aria-controls=${listId}
                    aria-activedescendant=${activeId}
                    aria-label=${this._native.getAttribute('aria-label') || nothing}
                    ?disabled=${this.disabled}
                    @click=${this._toggle} @keydown=${this._onKey}>
                <span class="label">${this._label()}</span>
                ${CHEVRON}
            </button>
            <ul class="panel ${this._dropUp ? 'up' : ''}" id=${listId} role="listbox"
                ?hidden=${!this.open}>
                ${this._options.map((o, i) => html`
                    <li id=${`${this._uid}-opt-${i}`} role="option"
                        class="opt ${i === this._active ? 'active' : ''}"
                        aria-selected=${o.value === this.value ? 'true' : 'false'}
                        aria-disabled=${o.disabled ? 'true' : nothing}
                        @click=${() => this._commit(i)}
                        @pointermove=${() => { this._active = i; }}>
                        <span class="opt-check">${o.value === this.value ? CHECK : nothing}</span>
                        <span class="opt-label">${o.label}</span>
                    </li>`)}
            </ul>`;
    }

    static styles = css`
        :host {
            display: block;
            width: 100%;
            position: relative;
            font-family: var(--font-sans);
            font-size: var(--fs-300, 1rem);
        }
        :host([compact]) { width: auto; min-width: 9rem; }

        .trigger {
            display: flex;
            align-items: center;
            gap: var(--space-2, 0.5rem);
            width: 100%;
            min-height: 2.75rem;            /* ≥44px touch */
            padding: var(--space-2, 0.5rem) var(--space-3, 0.75rem);
            background: var(--color-surface);
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: var(--radius, 10px);
            font: inherit;
            text-align: start;
            cursor: pointer;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }
        .trigger:hover { border-color: color-mix(in srgb, var(--color-primary) 35%, var(--color-border)); }
        .trigger:focus-visible {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-focus);
        }
        .trigger[aria-expanded="true"] {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-focus);
        }
        .trigger:disabled { opacity: 0.5; cursor: not-allowed; }
        .label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .chev { flex: 0 0 auto; color: var(--color-muted); transition: transform 160ms ease; }
        .trigger[aria-expanded="true"] .chev { transform: rotate(180deg); color: var(--color-primary); }

        .panel {
            position: absolute;
            left: 0;
            top: calc(100% + 6px);
            z-index: 60;
            min-width: 100%;
            max-height: 16rem;
            overflow-y: auto;
            margin: 0;
            padding: var(--space-1, 0.25rem);
            list-style: none;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg, 16px);
            box-shadow: var(--shadow-md);
            transform-origin: top;
            animation: fs-in 140ms ease;
        }
        .panel.up { top: auto; bottom: calc(100% + 6px); transform-origin: bottom; }
        .panel[hidden] { display: none; }

        @keyframes fs-in {
            from { opacity: 0; transform: translateY(-4px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        @media (prefers-reduced-motion: reduce) {
            .panel { animation: none; }
            .trigger, .chev { transition: none; }
        }

        .opt {
            display: flex;
            align-items: center;
            gap: var(--space-2, 0.5rem);
            min-height: 2.5rem;             /* комфортный touch */
            padding: var(--space-2, 0.5rem) var(--space-2, 0.5rem);
            border-radius: var(--radius-sm, 6px);
            color: var(--color-text);
            cursor: pointer;
        }
        .opt.active { background: var(--color-surface-2); }
        .opt[aria-selected="true"] { color: var(--color-primary); font-weight: 600; }
        .opt[aria-disabled="true"] { opacity: 0.45; cursor: not-allowed; }
        .opt-check { flex: 0 0 auto; width: 16px; height: 16px; color: var(--color-primary); }
        .opt-label { flex: 1; min-width: 0; }
    `;
}

customElements.define('fancy-select', FancySelect);
