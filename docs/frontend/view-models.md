# View-model контракт фронта (proto-API)

Каждая страница потребляет **явный, минимальный** набор данных, который передаёт контроллер
(`src/Controller/*`). Фронт его не меняет — фиксирует. При вынесении фронта в отдельный репо (H3)
этот контракт становится формой JSON-ответа API; партиалы подключаются как
`{% include '…' with { … } only %}`, т.е. их пропсы = будущие пропсы компонента = поля API.

> ⚠️ Бэк (контроллеры/Renderer/seed) в Milestone 1 не трогается. Таблица описывает текущие данные.

## Страницы

| Маршрут | Шаблон | Переменные (контракт) |
|---|---|---|
| `GET /` | `songs/list.html.twig` | `songs[] { slug, title, artists_info, key_original }`, `filters { q, key, license }`, `available_keys[]` |
| `GET /songs/{slug}` | `songs/show.html.twig` | `song { slug, title, key_original, license }`, `artists[] { artist_slug, name, role }`, `chordpro_html` (готовый HTML из Renderer) |
| `GET /songs/new`, `/songs/{slug}/edit` | `songs/form.html.twig` | `mode`, `song { … }`, `all_artists[] { id, name }`, `selected_artists { id: role }`, `keys[]`, `errors {}` |
| `GET /artists` | `artists/list.html.twig` | `artists[] { slug, name, songs_count }` |
| `GET /artists/{slug}` | `artists/show.html.twig` | `artist { slug, name, bio }`, `songs[] { slug, title, role }` |
| `GET /artists/new`, `/artists/{slug}/edit` | `artists/form.html.twig` | `mode`, `artist { … }`, `errors {}` |

## Компоненты (явные пропсы)

| Партиал | Пропсы |
|---|---|
| `components/_song_card` | `{ song }` |
| `components/_filter_bar` | `{ filters, available_keys }` |
| `components/_badge` | `{ license }` |
| `components/_chord_sheet` | `{ song, chordpro_html }` |
| `components/_form_field` (макросы) | `text(name,label,value,error,attrs)`, `textarea(...)` |
| `components/_licenses` (макросы) | `label(key)`, `options(selected)` |
| `components/_pluralize` (макрос) | `songs(count)` |

## Острова (Web Components) и их data-контракт из DOM

| Элемент | Атрибуты | Зависит от |
|---|---|---|
| `<theme-toggle>` | — | `localStorage['songbook-theme']` |
| `<song-transposer>` | `data-slug`, `data-key` + дочерний `chordpro_html` | `js/music/chords.js`; `localStorage['songbook-transpose:{slug}']` |
| `<stage-reader>` | `data-target` (id цели) | `--chord-scale` на цели; `localStorage['songbook-stage-scale']` |

## H3-подготовка (по go-ahead на бэк)

- Добавить в `Renderer` `data-root/-suffix/-bass` к `.chord` → та же структура едет в JSON API и
  избавляет `<song-transposer>` от парсинга текста.
- Сделать контроллеры format-aware (HTML-фрагмент / JSON) — формализация таблицы выше.
