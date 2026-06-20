<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class ArtistRepository
{
    public function __construct(private PDO $pdo) {}

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM artists WHERE slug = ?');
        $stmt->execute([$slug]);
        $artist = $stmt->fetch();
        return $artist ?: null;
    }

    public function findAll(): array
    {
        $sql = '
            SELECT a.id, a.name, a.slug, COUNT(sa.song_id) AS songs_count
            FROM artists a
            LEFT JOIN song_artists sa ON a.id = sa.artist_id
            GROUP BY a.id
            ORDER BY a.name
        ';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function findSongsByArtistId(int $artistId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT s.title, s.slug, sa.role
            FROM songs s
            JOIN song_artists sa ON s.id = sa.song_id
            WHERE sa.artist_id = ?
            ORDER BY s.title
        ');
        $stmt->execute([$artistId]);
        return $stmt->fetchAll();
    }

    public function save(array $data): string
    {
        return isset($data['id']) ? $this->update($data) : $this->insert($data);
    }

    private function insert(array $data): string
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO artists (name, slug, bio, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())',
        );
        $stmt->execute([$data['name'], $data['slug'], $data['bio'] ?? '']);
        return $data['slug'];
    }

    private function update(array $data): string
    {
        $stmt = $this->pdo->prepare(
            'UPDATE artists SET name = ?, bio = ?, updated_at = NOW() WHERE id = ?',
        );
        $stmt->execute([$data['name'], $data['bio'] ?? '', $data['id']]);
        return $data['slug'];
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM artists WHERE id = ?')->execute([$id]);
    }

    public function songCount(int $artistId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM song_artists WHERE artist_id = ?');
        $stmt->execute([$artistId]);
        return (int) $stmt->fetchColumn();
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM artists WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetchColumn() > 0;
    }
}
