<?php

namespace App\Repository;

use PDO;

class SongRepository
{
    public function __construct(private PDO $pdo) {}

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM songs WHERE slug = ?');
        $stmt->execute([$slug]);
        $song = $stmt->fetch();
        return $song ?: null;
    }

    //Список песен с фильтрами
    public function findAll(?string $q = null, ?string $key = null, ?string $license = null): array
    {
        $sql = '
            SELECT s.id, s.title, s.slug, s.key_original,
                   GROUP_CONCAT(CONCAT(a.name, \' (\', sa.role, \')\') SEPARATOR \', \') AS artists_info
            FROM songs s
            JOIN song_artists sa ON s.id = sa.song_id
            JOIN artists a ON sa.artist_id = a.id
        ';

        $where = [];
        $params = [];

        if ($q !== null && $q !== '') {
            $where[] = '(s.title LIKE :q OR a.name LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($key !== null && $key !== '') {
            $where[] = 's.key_original = :key';
            $params[':key'] = $key;
        }
        if ($license !== null && $license !== '') {
            $where[] = 's.license = :license';
            $params[':license'] = $license;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY s.id ORDER BY s.title';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    //артисты песни
    public function findArtistsBySongId(int $songId): array
    {
        $stmt = $this->pdo->prepare('
        SELECT a.name, a.slug AS artist_slug, sa.role, sa.artist_id
        FROM artists a
        JOIN song_artists sa ON a.id = sa.artist_id
        WHERE sa.song_id = ?
        ORDER BY FIELD(sa.role, \'author\', \'composer\', \'translator\', \'performer\')
    ');
        $stmt->execute([$songId]);
        return $stmt->fetchAll();
    }

    //upsert-метод, если передается id, значит песня уже существует, тогда update иначе insert
    public function save(array $songData, array $artists): string
    {
        return isset($songData['id'])
            ? $this->update($songData, $artists)
            : $this->insert($songData, $artists);
    }

    //Создание песни
    private function insert(array $data, array $artists): string
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'INSERT INTO songs (title, slug, lyrics_chordpro, key_original, source, license, fingerprint, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $data['title'], $data['slug'], $data['lyrics_chordpro'],
                $data['key_original'], 'self', $data['license'], $data['fingerprint']
            ]);
            $songId = $this->pdo->lastInsertId();

            $linkStmt = $this->pdo->prepare('INSERT INTO song_artists (song_id, artist_id, role) VALUES (?, ?, ?)');
            foreach ($artists as $artistId => $artist) {
                if (empty($artist['id'])) continue;
                $linkStmt->execute([$songId, $artistId, $artist['role']]);
            }

            $this->pdo->commit();
            return $data['slug'];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function update(array $data, array $artists): string
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'UPDATE songs SET title = ?, lyrics_chordpro = ?, key_original = ?, license = ?, fingerprint = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([
                $data['title'], $data['lyrics_chordpro'],
                $data['key_original'], $data['license'], $data['fingerprint'],
                $data['id']
            ]);

            $this->pdo->prepare('DELETE FROM song_artists WHERE song_id = ?')->execute([$data['id']]);

            $linkStmt = $this->pdo->prepare('INSERT INTO song_artists (song_id, artist_id, role) VALUES (?, ?, ?)');
            foreach ($artists as $artistId => $artist) {
                if (empty($artist['id'])) continue;
                $linkStmt->execute([$data['id'], $artistId, $artist['role']]);
            }

            $this->pdo->commit();
            return $data['slug'];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(string $slug): void
    {
        $this->pdo->prepare('DELETE FROM songs WHERE slug = ?')->execute([$slug]);
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM songs WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetchColumn() > 0;
    }

    public function getAvailableKeys(): array
    {
        return $this->pdo->query('SELECT DISTINCT key_original FROM songs ORDER BY key_original')->fetchAll(PDO::FETCH_COLUMN);
    }
}