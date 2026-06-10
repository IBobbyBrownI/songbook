/* ============================================================
   chords.js — framework-free доменное ядро (без зависимостей).
   Переиспользуется островами сейчас и любым будущим фронтом/бэком.

   Соглашения о нотации (важно для walkthrough):
   - Выход всегда в ДИЕЗАХ (C#, не Db) — один канонический набор → предсказуемый сдвиг.
   - Бемоли на входе нормализуются в диезы (Bb → A#).
   - Немецкая 'H' трактуется как английская B (си-бекар); вход 'B' остаётся B
     (английская конвенция, совпадает со списком тональностей приложения: A#, B, …).
   - Суффикс (m, sus4, 7, maj7…) НЕ транспонируется — это качество аккорда, не высота.
   - Невалидный вход возвращается как есть (страница не должна падать).
   ============================================================ */

const NOTES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

const FLAT_TO_SHARP = {
    Cb: 'B', Db: 'C#', Eb: 'D#', Fb: 'E', Gb: 'F#', Ab: 'G#', Bb: 'A#',
};
const SHARP_ENH = { 'E#': 'F', 'B#': 'C' };

/** Нормализует ноту (буква + #/b, в т.ч. 'H') к каноническому диезу или null. */
export function normalizeNote(note) {
    const m = /^([A-Ha-h])([#b]?)/.exec(String(note).trim());
    if (!m) return null;
    let letter = m[1].toUpperCase();
    const acc = m[2] || '';
    if (letter === 'H') letter = 'B';
    let name = letter + acc;
    if (acc === 'b') name = FLAT_TO_SHARP[name] || name;
    if (SHARP_ENH[name]) name = SHARP_ENH[name];
    return NOTES.includes(name) ? name : null;
}

/** Разбирает аккорд на { root, suffix, bass }. null если корень не распознан. */
export function parseChord(raw) {
    if (raw == null) return null;
    const s = String(raw).trim();
    if (!s) return null;
    const m = /^([A-Ha-h][#b]?)/.exec(s);
    if (!m) return null;
    const root = normalizeNote(m[1]);
    if (!root) return null;
    const rest = s.slice(m[0].length);
    const slash = rest.indexOf('/');
    let suffix = rest;
    let bass = null;
    if (slash >= 0) {
        suffix = rest.slice(0, slash);
        bass = normalizeNote(rest.slice(slash + 1));
    }
    return { root, suffix, bass };
}

/** Сдвигает одну ноту на N полутонов (с обёртыванием). Невалидное — без изменений. */
export function transposeNote(note, semitones) {
    const canonical = normalizeNote(note);
    if (canonical === null) return note;
    const idx = NOTES.indexOf(canonical);
    const ni = (((idx + semitones) % 12) + 12) % 12;
    return NOTES[ni];
}

/** Транспонирует аккорд целиком (корень + бас), сохраняя суффикс. */
export function transposeChord(raw, semitones) {
    if (!semitones) return raw;
    const c = parseChord(raw);
    if (!c) return raw;
    const root = transposeNote(c.root, semitones);
    const bass = c.bass ? transposeNote(c.bass, semitones) : null;
    return root + c.suffix + (bass ? '/' + bass : '');
}
