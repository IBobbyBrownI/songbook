<?php

declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";

use Symfony\Component\Dotenv\Dotenv;
use Cocur\Slugify\Slugify;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . "/../.env");
$slugify = new Slugify();

$dsn = sprintf(
    "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
    $_ENV["DB_HOST"],
    $_ENV["DB_PORT"],
    $_ENV["DB_NAME"],
);

$pdo = new PDO(
    $dsn,
    $_ENV["DB_USER"],
    $_ENV["DB_PASSWORD"],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
);

echo "Connected successfully";

$pdo->exec("DELETE FROM song_artists");
$pdo->exec("DELETE FROM songs");
$pdo->exec("DELETE FROM artists");

$artists = [
    ['name' => 'Cory Asbury', 'bio' => 'Biography about Cory Asbury'],
    ['name' => 'Brandon Lake', 'bio' => 'Biography about Brandon Lake'],
    ['name' => 'Vladik Krivobrodski', 'bio' => 'Biography about Vladik Krivobrodski'],
];

$artistIds = [];
$stmt = $pdo->prepare('INSERT INTO artists (name, slug, bio) VALUES (?, ?, ?)');
foreach ($artists as $artist) {
    $slug = $slugify->slugify($artist['name']);
    $stmt->execute([$artist['name'], $slug, $artist['bio']]);
    $artistIds[$artist['name']] = $pdo->lastInsertId();
    echo "  Artist: {$artist['name']} (slug: $slug)\n";
}

$songs = [
    [
        'title' => 'Reckless Love',
        'artist_name' => 'Cory Asbury',
        'lyrics_chordpro' => "{title: Reckless Love}\n{key: A}\n\n{c: verse 1}\n[C#M]Before I spoke a wo[B]rd, You were si[A]nging over me",
        'first_lyrics_line' => 'Before I spoke a word, You were singing over me',
        'key_original' => 'A',
        'license' => 'public_domain',
        'artists'
            => [
                ['name' => 'Cory Asbury', 'role' => 'author'],
                ['name' => 'Vladik Krivobrodski', 'role' => 'performer'],
            ],
    ],
    [
        'title' => 'Count em',
        'artist_name' => 'Brandon Lake',
        'lyrics_chordpro' => "{title: Count em}\n{key: C}\n\n{c: verse 1}\n[Cm]You got thunder in Your vocal You got flames in Your [Cm] eyes",
        'first_lyrics_line' => 'You got thunder in Your vocal You got flames in Your eyes',
        'key_original' => 'C',
        'license' => 'ugc',
        'artists'
            => [
                ['name' => 'Brandon Lake', 'role' => 'author'],
                ['name' => 'Vladik Krivobrodski', 'role' => 'performer'],
            ],
    ],
    [
        'title' => 'Amazing grace',
        'artist_name' => 'Vladik Krivobrodski',
        'lyrics_chordpro' => "{title: Amazing grace}\n{key: D}\n\n{c: verse 1}\n[D]Amazing [D7]grace how s[G]weet the so[D]und",
        'first_lyrics_line' => 'Amazing grace how sweet the sound',
        'key_original' => 'D',
        'license' => 'ugc',
        'artists'
            => [
                ['name' => 'Vladik Krivobrodski', 'role' => 'author'],
                ['name' => 'Vladik Krivobrodski', 'role' => 'performer'],
            ],
    ],
    [
        'title' => 'Hevens praise',
        'artist_name' => 'Vladik Krivobrodski',
        'lyrics_chordpro' => "{title: Hevens praise}\n{key: A}\n\n{c: verse 1}\nThe heavens praise Go[G]d t[A]o Y[Hm]ou, endlessly repe[G]ati[A]ng Pr[Hm]aise",
        'first_lyrics_line' => 'The heavens praise God to You, endlessly repeating Praise',
        'key_original' => 'A',
        'license' => 'ugc',
        'artists'
            => [
                ['name' => 'Vladik Krivobrodski', 'role' => 'author'],
                ['name' => 'Vladik Krivobrodski', 'role' => 'performer'],
            ],
    ],
    [
        'title' => 'Praise',
        'artist_name' => 'Brandon Lake',
        'lyrics_chordpro' => "{title: Praise}\n{key: A}\n\n{c: verse 1}\n[F#m]Pra-[D]ise the [A]Lord oh my[E] soul",
        'first_lyrics_line' => 'Praise the Lord oh my soul',
        'key_original' => 'A',
        'license' => 'ugc',
        'artists'
            => [
                ['name' => 'Brandon Lake', 'role' => 'author'],
                ['name' => 'Vladik Krivobrodski', 'role' => 'performer'],
            ],
    ],
];

$songStmt = $pdo->prepare(
    'INSERT INTO songs (title, slug, lyrics_chordpro, key_original, source, license, fingerprint, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
);

$songIds = [];
foreach ($songs as $song) {
    $slug = $slugify->slugify($song['title']);
    $fingerprint = hash('sha256', $song['title'] . "\n" . $song['first_lyrics_line'] . "\n" . $song['artist_name']);

    $songStmt->execute([$song['title'], $slug, $song['lyrics_chordpro'], $song['key_original'], 'self', $song['license'], $fingerprint,
    ]);

    $songIds[$song['title']] = $pdo->lastInsertId();
    echo "Song: {$song['title']} (slug: $slug, key: {$song['key_original']}, license: {$song['license']})\n";
}

$linkStmt = $pdo->prepare('INSERT INTO song_artists (song_id, artist_id, role) VALUES (?, ?, ?)');

foreach ($songs as $song) {
    $songId = $songIds[$song['title']];
    foreach ($song['artists'] as $artist) {
        $artistId = $artistIds[$artist['name']];
        $linkStmt->execute([$songId, $artistId, $artist['role']]);
        echo "    Link: {$song['title']} ← {$artist['name']} ({$artist['role']})\n";
    }
}
