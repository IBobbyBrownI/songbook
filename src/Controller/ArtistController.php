<?php

namespace App\Controller;

use PDO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Cocur\Slugify\Slugify;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Twig\Environment;

class ArtistController
{
    private PDO $pdo;
    private Environment $twig;

    public function __construct(PDO $pdo, Environment $twig)
    {
        $this->pdo = $pdo;
        $this->twig = $twig;
    }

    public function list(Request $request): Response
    {
        $sql = '
            SELECT a.id, a.name, a.slug, COUNT(sa.song_id) AS songs_count
            FROM artists a
            LEFT JOIN song_artists sa ON a.id = sa.artist_id
            GROUP BY a.id
            ORDER BY a.name
        ';

        $artists = $this->pdo->query($sql)->fetchAll();

        $html = $this->twig->render('artists/list.html.twig', [
            'artists' => $artists,
        ]);

        return new Response($html);
    }

    public function new(Request $request): Response
    {
        $html = $this->twig->render('artists/form.html.twig', [
            'mode' => 'new',
            'artist' => ['name' => '', 'bio' => '', 'slug' => ''],
            'errors' => [],
        ]);

        return new Response($html);
    }

    public function show(Request $request, string $slug): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM artists WHERE slug = ?');
        $stmt->execute([$slug]);
        $artist = $stmt->fetch();

        if (!$artist) {
            return new Response('Артист не найден', Response::HTTP_NOT_FOUND);
        }

        // Песни артиста с ролью
        $stmt = $this->pdo->prepare('
        SELECT s.title, s.slug, sa.role
        FROM songs s
        JOIN song_artists sa ON s.id = sa.song_id
        WHERE sa.artist_id = ?
        ORDER BY s.title
    ');
        $stmt->execute([$artist['id']]);
        $songs = $stmt->fetchAll();

        $html = $this->twig->render('artists/show.html.twig', [
            'artist' => $artist,
            'songs' => $songs,
        ]);

        return new Response($html);
    }

    public function edit(Request $request, string $slug): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM artists WHERE slug = ?');
        $stmt->execute([$slug]);
        $artist = $stmt->fetch();

        if (!$artist) {
            return new Response('Артист не найден', Response::HTTP_NOT_FOUND);
        }

        $html = $this->twig->render('artists/form.html.twig', [
            'mode' => 'edit',
            'artist' => $artist,
            'errors' => [],
        ]);

        return new Response($html);
    }

    public function update(Request $request, string $slug): Response
    {
        $stmt = $this->pdo->prepare('SELECT * FROM artists WHERE slug = ?');
        $stmt->execute([$slug]);
        $artist = $stmt->fetch();

        if (!$artist) {
            return new Response('Артист не найден', Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        $errors = $this->validateArtist($data);

        if (!empty($errors)) {
            return $this->renderArtistForm($data, $errors, 'edit', $slug);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE artists SET name = ?, bio = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$data['name'], $data['bio'] ?? '', $artist['id']]);

        return new RedirectResponse('/artists/' . $slug);
    }

    public function create(Request $request): Response
    {
        $data = $request->request->all();
        $errors = $this->validateArtist($data);

        if (!empty($errors)) {
            return $this->renderArtistForm($data, $errors, 'new');
        }

        // Генерация slug
        $slugify = new Slugify();
        $slug = $slugify->slugify($data['name']);
        $originalSlug = $slug;
        $counter = 2;

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM artists WHERE slug = ?');
        while (true) {
            $stmt->execute([$slug]);
            if (!$stmt->fetchColumn()) break;
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO artists (name, slug, bio, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$data['name'], $slug, $data['bio'] ?? '']);

        return new RedirectResponse('/artists/' . $slug);
    }

    public function delete(Request $request, string $slug): Response
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM artists WHERE slug = ?');
        $stmt->execute([$slug]);
        $artist = $stmt->fetch();

        if (!$artist) {
            return new Response('Артист не найден', Response::HTTP_NOT_FOUND);
        }

        // Проверка: есть ли песни у артиста
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM song_artists WHERE artist_id = ?');
        $stmt->execute([$artist['id']]);
        $songsCount = $stmt->fetchColumn();

        if ($songsCount > 0) {
            return new Response(
                'Нельзя удалить артиста «' . $artist['name'] . '»: у него ' . $songsCount . ' песен. Сначала отвяжите их.',
                Response::HTTP_CONFLICT
            );
        }

        $this->pdo->prepare('DELETE FROM artists WHERE id = ?')->execute([$artist['id']]);

        return new RedirectResponse('/artists');
    }

    private function validateArtist(array $data): array
    {
        $errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = 'Имя обязательно';
        } elseif (mb_strlen($data['name']) > 255) {
            $errors['name'] = 'Имя не должно превышать 255 символов';
        }

        return $errors;
    }

    private function renderArtistForm(array $data, array $errors, string $mode, string $slug = ''): Response
    {
        $html = $this->twig->render('artists/form.html.twig', [
            'mode' => $mode,
            'artist' => [
                'name' => $data['name'] ?? '',
                'bio' => $data['bio'] ?? '',
                'slug' => $slug,
            ],
            'errors' => $errors,
        ]);

        return new Response($html);
    }
}