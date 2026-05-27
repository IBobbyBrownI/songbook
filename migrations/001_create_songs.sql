CREATE TABLE IF NOT EXISTS songs (
    id INT AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    lyrics_chordpro TEXT NOT NULL,
    key_original VARCHAR(8) NOT NULL,
    source VARCHAR(32) NOT NULL DEFAULT 'self',
    license VARCHAR(32) NOT NULL DEFAULT 'unknown',
    fingerprint CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_songs_key (key_original),
    INDEX idx_songs_license (license),
    INDEX idx_songs_key_license (key_original, license)
);