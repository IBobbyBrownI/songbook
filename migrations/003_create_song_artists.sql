CREATE TABLE IF NOT EXISTS song_artists (
    song_id INT NOT NULL,
    artist_id INT NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'performer',
    PRIMARY KEY (song_id, artist_id, role),
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE
);