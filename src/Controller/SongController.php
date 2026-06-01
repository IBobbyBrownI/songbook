<?php

namespace App\Controller;

use App\ChordPro\Parser;
use App\ChordPro\Renderer;
use PDO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

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

    public function list(Request $request): Response
    {
        $sql = '
            SELECT s.title, s.slug, s.key_original, 
            MIN(a.name) AS first_artist_name
            FROM songs s
            LEFT JOIN song_artists sa ON s.id = sa.song_id
            LEFT JOIN artists a ON sa.artist_id = a.id
            GROUP BY s.id
            ORDER BY s.title
        ';

        $stmt = $this->pdo->query($sql);
        $songs = $stmt->fetchAll();

        $html = $this->twig->render('songs/list.html.twig', [
            'songs' => $songs,
        ]);

        return new Response($html);
    }

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
}