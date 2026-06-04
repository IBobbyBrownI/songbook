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