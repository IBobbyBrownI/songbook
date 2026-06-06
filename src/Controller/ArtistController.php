<?php

namespace App\Controller;

use App\Repository\ArtistRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Twig\Environment;
use Cocur\Slugify\Slugify;

class ArtistController
{
    public function __construct(
        private ArtistRepository $artists,
        private Environment $twig
    ) {}

    public function list(Request $request): Response
    {
        $artists = $this->artists->findAll();

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
        $artist = $this->artists->findBySlug($slug);

        if (!$artist) {
            return new Response('Артист не найден', Response::HTTP_NOT_FOUND);
        }

        $songs = $this->artists->findSongsByArtistId($artist['id']);

        $html = $this->twig->render('artists/show.html.twig', [
            'artist' => $artist,
            'songs' => $songs,
        ]);

        return new Response($html);
    }

    public function edit(Request $request, string $slug): Response
    {
        $artist = $this->artists->findBySlug($slug);

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
        $artist = $this->artists->findBySlug($slug);

        if (!$artist) {
            return new Response('Артист не найден', Response::HTTP_NOT_FOUND);
        }

        $data = $request->request->all();
        $errors = $this->validateArtist($data);

        if (!empty($errors)) {
            return $this->renderArtistForm($data, $errors, 'edit', $slug);
        }

        $this->artists->save([
            'id' => $artist['id'],
            'name' => $data['name'],
            'bio' => $data['bio'] ?? '',
            'slug' => $slug,
        ]);

        return new RedirectResponse('/artists/' . $slug);
    }

    public function create(Request $request): Response
    {
        $data = $request->request->all();
        $errors = $this->validateArtist($data);

        if (!empty($errors)) {
            return $this->renderArtistForm($data, $errors, 'new');
        }

        $slugify = new Slugify();
        $slug = $slugify->slugify($data['name']);
        $originalSlug = $slug;
        $counter = 2;

        while ($this->artists->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $this->artists->save([
            'name' => $data['name'],
            'bio' => $data['bio'] ?? '',
            'slug' => $slug,
        ]);

        return new RedirectResponse('/artists/' . $slug);
    }

    public function delete(Request $request, string $slug): Response
    {
        $artist = $this->artists->findBySlug($slug);

        if (!$artist) {
            return new Response('Артист не найден', Response::HTTP_NOT_FOUND);
        }

        $songsCount = $this->artists->songCount($artist['id']);

        if ($songsCount > 0) {
            return new Response(
                'Нельзя удалить артиста «' . $artist['name'] . '»: у него ' . $songsCount . ' песен. Сначала отвяжите их.',
                Response::HTTP_CONFLICT
            );
        }

        $this->artists->delete($artist['id']);

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