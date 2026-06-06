<?php

namespace App\Controller;

use App\ChordPro\Parser;
use App\ChordPro\Renderer;
use App\ChordPro\Fingerprint;
use PDO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Cocur\Slugify\Slugify;
use Symfony\Component\HttpFoundation\RedirectResponse;


class SongController
{
    private PDO $pdo;
    private Environment $twig;
    private Parser $parser;
    private Renderer $renderer;

    public function __construct(PDO $pdo, Environment $twig, Parser $parser, Renderer $renderer)
    {
        $this->pdo = $pdo;
        $this->twig = $twig;
        $this->parser = $parser;
        $this->renderer = $renderer;
    }

    //Список песен из бд
    public function list(Request $request): Response
    {
        $q = $request->query->get('q', '');
        $key = $request->query->get('key', '');
        $license = $request->query->get('license', '');

        // Whitelist для license
        $allowedLicenses = ['public_domain', 'ugc', 'unknown', 'restricted'];
        if ($license !== '' && !in_array($license, $allowedLicenses, true)) {
            $license = '';
        }

        // Whitelist для key — только из доступных в БД
        if ($key !== '') {
            $availableKeys = $this->pdo->query('SELECT DISTINCT key_original FROM songs ORDER BY key_original')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array($key, $availableKeys, true)) {
                $key = '';
            }
        }

        $sql = '
        SELECT s.id, s.title, s.slug, s.key_original,
               GROUP_CONCAT(CONCAT(a.name, \' (\', sa.role, \')\') SEPARATOR \', \') AS artists_info
        FROM songs s
        LEFT JOIN song_artists sa ON s.id = sa.song_id
        LEFT JOIN artists a ON sa.artist_id = a.id
    ';

        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(s.title LIKE :q OR a.name LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        if ($key !== '') {
            $where[] = 's.key_original = :key';
            $params[':key'] = $key;
        }

        if ($license !== '') {
            $where[] = 's.license = :license';
            $params[':license'] = $license;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY s.id ORDER BY s.title';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $songs = $stmt->fetchAll();

        // Список тональностей для фильтра
        $availableKeys = $this->pdo->query('SELECT DISTINCT key_original FROM songs ORDER BY key_original')->fetchAll(PDO::FETCH_COLUMN);

        $html = $this->twig->render('songs/list.html.twig', [
            'songs' => $songs,
            'filters' => ['q' => $q, 'key' => $key, 'license' => $license],
            'available_keys' => $availableKeys,
        ]);

        return new Response($html);
    }

    //Страница песни
    public function show(Request $request, string $slug): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM songs WHERE slug = ?');
        $stmt->execute([$slug]);
        $song = $stmt->fetch();

        if (!$song) {
            return new Response('Песня не найдена', Response::HTTP_NOT_FOUND);
        }

        $stmt = $this->pdo->prepare('
            SELECT a.name, a.slug AS artist_slug, sa.role
            FROM artists a
            JOIN song_artists sa ON a.id = sa.artist_id
            WHERE sa.song_id = ?
            ORDER BY FIELD(sa.role, \'author\', \'composer\', \'translator\', \'performer\')
        ');
        $stmt->execute([$song['id']]);
        $artists = $stmt->fetchAll();

        $ast = $this->parser->parse($song['lyrics_chordpro']);
        $chordproHtml = $this->renderer->render($ast);

        $html = $this->twig->render('songs/show.html.twig', [
            'song' => $song,
            'artists' => $artists,
            'chordpro_html' => $chordproHtml,
        ]);

        return new Response($html);
    }

    //Форма добавления (страница создания песни)
    public function new(Request $request): Response
    {
        $stmt = $this->pdo->query('SELECT id, name, slug FROM artists ORDER BY name');
        $allArtists = $stmt->fetchAll();

        $keys = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B',
            'Am', 'A#m', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Em', 'Fm', 'F#m', 'Gm', 'G#m'];

        $song = [
            'title' => '',
            'lyrics_chordpro' => '',
            'key_original' => 'C',
            'license' => 'unknown',
        ];

        $html = $this->twig->render('songs/form.html.twig', [
            'mode' => 'new',
            'song' => $song,
            'all_artists' => $allArtists,
            'selected_artists' => [],
            'keys' => $keys,
            'errors' => [],
        ]);

        return new Response($html);
    }

    public function edit(Request $request, string $slug): Response
    {
        // Найти песню по slug
        $stmt = $this->pdo->prepare('SELECT * FROM songs WHERE slug = ?');
        $stmt->execute([$slug]);
        $song = $stmt->fetch();

        if (!$song) {
            return new Response('Песня не найдена', Response::HTTP_NOT_FOUND);
        }

        $allArtists = $this->pdo->query('SELECT id, name, slug FROM artists ORDER BY name')->fetchAll();

        // Связи текущей песни: [artist_id => role]
        $stmt = $this->pdo->prepare('SELECT artist_id, role FROM song_artists WHERE song_id = ?');
        $stmt->execute([$song['id']]);
        $selectedArtists = [];
        foreach ($stmt->fetchAll() as $row) {
            $selectedArtists[$row['artist_id']] = ['id' => $row['artist_id'], 'role' => $row['role']];
        }

        // Тональности
        $keys = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B',
            'Am', 'A#m', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Em', 'Fm', 'F#m', 'Gm', 'G#m'];

        $html = $this->twig->render('songs/form.html.twig', [
            'mode' => 'edit',
            'song' => $song,
            'all_artists' => $allArtists,
            'selected_artists' => $selectedArtists,
            'keys' => $keys,
            'errors' => [],
        ]);

        return new Response($html);
    }

    //Сохранение новоц песни (при нажатии на кнопку создать в форме new)
    public function create(Request $request): Response
    {
        $data = $request->request->all();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            return $this->renderForm($data, $errors);
        }

        // Генерация slug для проверки на уникальность, если есть такой же, slug-2 и т.д счетчик++
        $slugify = new Slugify();
        $slug = $slugify->slugify($data['title']);
        $originalSlug = $slug;
        $counter = 2;

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM songs WHERE slug = ?');
        while (true) {
            $stmt->execute([$slug]);
            if (!$stmt->fetchColumn()) break;
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Fingerprint (подключить use)
        $firstArtistId = array_key_first($data['artists']);
        $stmt = $this->pdo->prepare('SELECT name FROM artists WHERE id = ?');
        $stmt->execute([$firstArtistId]);
        $firstArtistName = $stmt->fetchColumn();
        $fingerprint = Fingerprint::compute($data['title'], $data['lyrics_chordpro'], $firstArtistName);

        // Транзакция
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO songs (title, slug, lyrics_chordpro, key_original, source, license, fingerprint, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $data['title'], $slug, $data['lyrics_chordpro'],
                $data['key_original'], 'self', $data['license'], $fingerprint
            ]);
            $songId = $this->pdo->lastInsertId();

            $linkStmt = $this->pdo->prepare('INSERT INTO song_artists (song_id, artist_id, role) VALUES (?, ?, ?)');
            foreach ($data['artists'] as $artistId => $artist) {
                if (empty($artist['id'])) continue;  // пропускаем неотмеченные
                $linkStmt->execute([$songId, $artistId, $artist['role']]);
            }

            $this->pdo->commit();

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $errors['general'] = 'Ошибка при сохранении';
            return $this->renderForm($data, $errors);
        }

        return new RedirectResponse('/songs/' . $slug);
    }

    public function update(Request $request, string $slug): Response
    {
        // Найти песню
        $stmt = $this->pdo->prepare('SELECT * FROM songs WHERE slug = ?');
        $stmt->execute([$slug]);
        $song = $stmt->fetch();

        if (!$song) {
            return new Response('Песня не найдена', Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            return $this->renderEditForm($data, $slug, $errors);
        }

        // Fingerprint
        $firstArtistId = array_key_first($data['artists']);
        $stmt = $this->pdo->prepare('SELECT name FROM artists WHERE id = ?');
        $stmt->execute([$firstArtistId]);
        $firstArtistName = $stmt->fetchColumn();
        $fingerprint = Fingerprint::compute($data['title'], $data['lyrics_chordpro'], $firstArtistName);

        //Транзакция
        try {
            $this->pdo->beginTransaction();

            // UPDATE (slug НЕ меняется)
            $stmt = $this->pdo->prepare(
                'UPDATE songs SET title = ?, lyrics_chordpro = ?, key_original = ?, license = ?, fingerprint = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([
                $data['title'], $data['lyrics_chordpro'],
                $data['key_original'], $data['license'], $fingerprint,
                $song['id']
            ]);

            // Удаляем старые связи
            $this->pdo->prepare('DELETE FROM song_artists WHERE song_id = ?')->execute([$song['id']]);

            // Вставляем новые
            $linkStmt = $this->pdo->prepare('INSERT INTO song_artists (song_id, artist_id, role) VALUES (?, ?, ?)');
            foreach ($data['artists'] as $artistId => $artist) {
                if (empty($artist['id'])) continue;
                $linkStmt->execute([$song['id'], $artistId, $artist['role']]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $errors['general'] = 'Ошибка при сохранении';
            return $this->renderEditForm($data, $slug, $errors);
        }

        return new RedirectResponse('/songs/' . $slug);
    }

    private function validate(array $data): array
    {
        $errors = [];

        if (empty(trim($data['title'] ?? ''))) {
            $errors['title'] = 'Название обязательно';
        } elseif (mb_strlen($data['title']) > 255) {
            $errors['title'] = 'Название не должно превышать 255 символов';
        }

        if (empty(trim($data['lyrics_chordpro'] ?? ''))) {
            $errors['lyrics_chordpro'] = 'Текст песни обязателен';
        } else {
            // Проверка синтаксиса: все ли квадратные скобки закрыты
            $open = substr_count($data['lyrics_chordpro'], '[');
            $close = substr_count($data['lyrics_chordpro'], ']');
            if ($open !== $close) {
                $errors['lyrics_chordpro'] = 'Ошибка в синтаксисе: не все квадратные скобки закрыты';
            }
        }

        $allowedKeys = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B',
            'Am', 'A#m', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Em', 'Fm', 'F#m', 'Gm', 'G#m'];


        if (!in_array($data['key_original'] ?? '', $allowedKeys)) {
            $errors['key_original'] = 'Недопустимая тональность';
        }


        $allowedLicenses = ['public_domain', 'ugc', 'unknown', 'restricted'];
        if (!in_array($data['license'] ?? '', $allowedLicenses)) {
            $errors['license'] = 'Недопустимая лицензия';
        }


        $selectedCount = 0;
        foreach ($data['artists'] ?? [] as $artist) {
            if (!empty($artist['id'])) $selectedCount++;
        }
        if ($selectedCount === 0) {
            $errors['artists'] = 'Выберите хотя бы одного артиста';
        }

        return $errors;
    }


    private function filterSelectedArtists(array $artists): array
    {
        $selected = [];
        foreach ($artists as $id => $artist) {
            if (!empty($artist['id'])) {
                $selected[$id] = $artist;
            }
        }
        return $selected;
    }


    private function renderForm(array $data, array $errors): Response
    {
        $allArtists = $this->pdo->query('SELECT id, name, slug FROM artists ORDER BY name')->fetchAll();

        $keys = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B',
            'Am', 'A#m', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Em', 'Fm', 'F#m', 'Gm', 'G#m'];

        $html = $this->twig->render('songs/form.html.twig', [
            'mode' => 'new',
            'song' => [
                'title' => $data['title'] ?? '',
                'lyrics_chordpro' => $data['lyrics_chordpro'] ?? '',
                'key_original' => $data['key_original'] ?? 'C',
                'license' => $data['license'] ?? 'unknown',
                'slug' => '',
            ],
            'all_artists' => $allArtists,
            'selected_artists' => $this->filterSelectedArtists($data['artists'] ?? []),
            'keys' => $keys,
            'errors' => $errors,
        ]);

        return new Response($html);
    }

    private function renderEditForm(array $data, string $slug, array $errors): Response
    {
        $allArtists = $this->pdo->query('SELECT id, name, slug FROM artists ORDER BY name')->fetchAll();
        $keys = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B',
            'Am', 'A#m', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Em', 'Fm', 'F#m', 'Gm', 'G#m'];

        $html = $this->twig->render('songs/form.html.twig', [
            'mode' => 'edit',
            'song' => [
                'title' => $data['title'] ?? '',
                'lyrics_chordpro' => $data['lyrics_chordpro'] ?? '',
                'key_original' => $data['key_original'] ?? 'C',
                'license' => $data['license'] ?? 'unknown',
                'slug' => $slug,
            ],
            'all_artists' => $allArtists,
            'selected_artists' => $this->filterSelectedArtists($data['artists'] ?? []),
            'keys' => $keys,
            'errors' => $errors,
        ]);

        return new Response($html);
    }

    public function delete(Request $request, string $slug): Response
    {
        $stmt = $this->pdo->prepare('SELECT id FROM songs WHERE slug = ?');
        $stmt->execute([$slug]);
        $song = $stmt->fetch();

        if (!$song) {
            return new Response('Песня не найдена', Response::HTTP_NOT_FOUND);
        }

        // Связи удалятся автоматически через ON DELETE CASCADE
        $this->pdo->prepare('DELETE FROM songs WHERE id = ?')->execute([$song['id']]);

        return new RedirectResponse('/');
    }
}