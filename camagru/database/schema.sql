CREATE TABLE IF NOT EXISTS users (
    id          SERIAL PRIMARY KEY,
    username    VARCHAR(50)  UNIQUE NOT NULL,
    email       VARCHAR(255) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    avatar      VARCHAR(255),
    modele      BOOLEAN      NOT NULL DEFAULT TRUE,
    verified    BOOLEAN      NOT NULL DEFAULT FALSE,
    verification_token VARCHAR(64),
    verification_expires_at TIMESTAMPTZ,
    notify_comment         BOOLEAN NOT NULL DEFAULT TRUE,
    notify_friend_request  BOOLEAN NOT NULL DEFAULT TRUE,
    notify_friend_accepted BOOLEAN NOT NULL DEFAULT TRUE,
    notify_friend_removed  BOOLEAN NOT NULL DEFAULT TRUE,
    suspended   BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- kept live, not commented out: they carry an existing database over
ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_comment        BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_friend_request BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_friend_accepted BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_friend_removed BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended             BOOLEAN NOT NULL DEFAULT FALSE;

-- ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255);
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS modele BOOLEAN NOT NULL DEFAULT TRUE;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS verified BOOLEAN NOT NULL DEFAULT FALSE;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64);
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_expires_at TIMESTAMPTZ;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ NOT NULL DEFAULT now();

CREATE TABLE IF NOT EXISTS password_resets (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  CHAR(64)     NOT NULL UNIQUE,
    expires_at  TIMESTAMPTZ  NOT NULL,
    used_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS password_resets_user_idx ON password_resets (user_id);

CREATE TABLE IF NOT EXISTS admins (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS images (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    filename    VARCHAR(255) NOT NULL,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS images_user_idx ON images (user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS images_recent_idx ON images (created_at DESC);

CREATE TABLE IF NOT EXISTS comments (
    id          SERIAL PRIMARY KEY,
    image_id    INTEGER REFERENCES images(id) ON DELETE CASCADE,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    comment     TEXT         NOT NULL,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS comments_image_idx ON comments (image_id, created_at);

CREATE TABLE IF NOT EXISTS likes (
    id          SERIAL PRIMARY KEY,
    image_id    INTEGER REFERENCES images(id) ON DELETE CASCADE,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    UNIQUE (image_id, user_id)
);

CREATE TABLE IF NOT EXISTS reports (
    id         SERIAL PRIMARY KEY,
    image_id   INTEGER      NOT NULL REFERENCES images(id) ON DELETE CASCADE,
    user_id    INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT now(),
    UNIQUE (image_id, user_id)
);

CREATE INDEX IF NOT EXISTS reports_image_idx ON reports (image_id);

CREATE TABLE IF NOT EXISTS friendships (
    id           SERIAL PRIMARY KEY,
    requester_id INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    addressee_id INTEGER      NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    accepted_at  TIMESTAMPTZ,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CHECK (requester_id <> addressee_id)
);

-- one row per pair, whichever way round the request went
CREATE UNIQUE INDEX IF NOT EXISTS friendships_pair_idx
    ON friendships (least(requester_id, addressee_id), greatest(requester_id, addressee_id));

CREATE INDEX IF NOT EXISTS friendships_inbox_idx
    ON friendships (addressee_id) WHERE accepted_at IS NULL;

-- Admin : login admin@aaa.com / password 123
INSERT INTO users (username, email, password, avatar, modele, verified)
VALUES ('admin', 'admin@aaa.com', '$2y$12$nddWL9YzNldOv8jK7H96YeAIW8zARa5gQr2Yj6oTPODHc3XTLrWZa', 'generique.png', TRUE, TRUE)
ON CONFLICT (email) DO NOTHING;

INSERT INTO users (username, email, password, avatar, modele, verified)
VALUES ('aaa', 'aaa@aaa.com', '$2y$12$R7Q02juSA8cbyQB92NBdiumeddFfNUuUx0L8PcLvM0XiQphBs1ygW', 'generique.png', TRUE, TRUE)
ON CONFLICT (email) DO NOTHING;

INSERT INTO admins (user_id)
SELECT id
FROM users
WHERE email = 'admin@aaa.com' AND username = 'admin'
AND NOT EXISTS (
    SELECT 1
    FROM admins
    WHERE admins.user_id = users.id
);
