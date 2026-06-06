<?php

namespace App\Controller;

use App\ChordPro\Parser;
use App\ChordPro\Renderer;
use App\ChordPro\Fingerprint;
use App\Repository\SongRepository;
use App\Repository\ArtistRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Twig\Environment;
use Cocur\Slugify\Slugify;

class SongController
{
    public function __construct(
        private SongRepository $songs,
        private ArtistRepository $artists,
        private Environment $twig,
        private Parser $parser,
        private Renderer $renderer
    ) {}

    public function list(Request $request): Response
    {
        $q = $request->query->get('q', '');
        $key = $request->query->get('key', '');
        $license = $request->query->get('license', '');

        $allowedLicenses = ['public_domain', 'ugc', 'unknown', 'restricted'];
        if ($license !== '' && !in_array($license, $allowedLicenses, true)) {
            $license = '';
        }

        if ($key !== '') {
            $availableKeys = $this->songs->getAvailableKeys();
            if (!in_array($key, $availableKeys, true)) {
                $key = '';
            }
        }

        $songs = $this->songs->findAll($q !== '' ? $q : null, $key !== '' ? $key : null, $license !== '' ? $license : null);
        $availableKeys = $this->songs->getAvailableKeys();

        $html = $this->twig->render('songs/list.html.twig', [
            'songs' => $songs,
            'filters' => ['q' => $q, 'key' => $key, 'license' => $license],
            'available_keys' => $availableKeys,
        ]);

        return new Response($html);
    }

    public function show(Request $request, string $slug): Response
    {
        $song = $this->songs->findBySlug($slug);

        if (!$song) {
            return new Response('Песня не найдена', Response::HTTP_NOT_FOUND);
        }

        $artists = $this->songs->findArtistsBySongId($song['id']);

        $ast = $this->parser->parse($song['lyrics_chordpro']);
        $chordproHtml = $this->renderer->render($ast);

        $html = $this->twig->render('songs/show.html.twig', [
            'song' => $song,
            'artists' => $artists,
            'chordpro_html' => $chordproHtml,
        ]);

        return new Response($html);
    }

    public function new(Request $request): Response
    {
        $allArtists = $this->artists->findAll();
        $keys = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B',
            'Am', 'A#m', 'Bm', 'Cm', 'C#m', 'Dm', 'D#m', 'Em', 'Fm', 'F#m', 'Gm', 'G#m'];

        $html = $this->twig->render('songs/form.html.twig', [
            'mode' => 'new',
            'song' => ['title' => '', 'lyrics_chordpro' => '', 'key_original' => 'C', 'license' => 'unknown', 'slug' => ''],
            'all_artists' => $allArtists,
            'selected_artists' => [],
            'keys' => $keys,
            'errors' => [],
        ]);

        return new Response($html);
    }

    public function edit(Request $request, string $slug): Response
    {
        $song = $this->songs->findBySlug($slug);

        if (!$song) {
            return new Response('Песня не найдена', Response::HTTP_NOT_FOUND);
        }

        $allArtists = $this->artists->findAll();
        $selectedArtists = [];
        foreach ($this->songs->findArtistsBySongId($song['id']) as $row) {
            $selectedArtists[$row['artist_id']] = ['id' => $row['artist_id'], 'role' => $row['role']];
        }

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

    public function create(Request $request): Response
    {
        $data = $request->request->all();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            return $this->renderForm($data, $errors);
        }

        $slugify = new Slugify();
        $slug = $slugify->slugify($data['title']);
        $originalSlug = $slug;
        $counter = 2;

        while ($this->songs->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $firstArtistId = array_key_first($data['artists']);
        $firstArtistName = $this->artists->findBySlug($firstArtistId)['name'] ?? '';
        $fingerprint = Fingerprint::compute($data['title'], $data['lyrics_chordpro'], $firstArtistName);

        try {
            $slug = $this->songs->save([
                'title' => $data['title'],
                'slug' => $slug,
                'lyrics_chordpro' => $data['lyrics_chordpro'],
                'key_original' => $data['key_original'],
                'license' => $data['license'],
                'fingerprint' => $fingerprint,
            ], $data['artists']);
        } catch (\Throwable $e) {
            $errors['general'] = 'Ошибка при сохранении';
            return $this->renderForm($data, $errors);
        }

        return new RedirectResponse('/songs/' . $slug);
    }

    public function update(Request $request, string $slug): Response
    {
        $song = $this->songs->findBySlug($slug);

        if (!$song) {
            return new Response('Песня не найдена', Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            return $this->renderEditForm($data, $slug, $errors);
        }

        $firstArtistId = array_key_first($data['artists']);
        $firstArtistName = $this->artists->findBySlug($firstArtistId)['name'] ?? '';
        $fingerprint = Fingerprint::compute($data['title'], $data['lyrics_chordpro'], $firstArtistName);

        try {
            $this->songs->save([
                'id' => $song['id'],
                'title' => $data['title'],
                'slug' => $slug,
                'lyrics_chordpro' => $data['lyrics_chordpro'],
                'key_original' => $data['key_original'],
                'license' => $data['license'],
                'fingerprint' => $fingerprint,
            ], $data['artists']);
        } catch (\Throwable $e) {
            $errors['general'] = 'Ошибка при сохранении';
            return $this->renderEditForm($data, $slug, $errors);
        }

        return new RedirectResponse('/songs/' . $slug);
    }

    public function delete(Request $request, string $slug): Response
    {
        $song = $this->songs->findBySlug($slug);

        if (!$song) {
            return new Response('Песня не найдена', Response::HTTP_NOT_FOUND);
        }

        $this->songs->delete($slug);

        return new RedirectResponse('/');
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
        $allArtists = $this->artists->findAll();
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
        $allArtists = $this->artists->findAll();
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
}