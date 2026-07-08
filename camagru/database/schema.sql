CREATE TABLE IF NOT EXISTS users (
    id          SERIAL PRIMARY KEY,
    username    VARCHAR(50)  UNIQUE NOT NULL,
    email       VARCHAR(255) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    avatar      VARCHAR(255),
    modele      BOOLEAN      NOT NULL DEFAULT TRUE,
    verified    BOOLEAN      NOT NULL DEFAULT FALSE,
    verification_token VARCHAR(64),
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS modele BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verified BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64);
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ NOT NULL DEFAULT now();

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

CREATE TABLE IF NOT EXISTS comments (
    id          SERIAL PRIMARY KEY,
    image_id    INTEGER REFERENCES images(id) ON DELETE CASCADE,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    comment     TEXT         NOT NULL,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS likes (
    id          SERIAL PRIMARY KEY,
    image_id    INTEGER REFERENCES images(id) ON DELETE CASCADE,
    user_id     INTEGER REFERENCES users(id) ON DELETE CASCADE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    UNIQUE (image_id, user_id)
);

-- Admin : login admin@aaa.com / password 123
INSERT INTO users (username, email, password, modele, verified)
VALUES ('admin', 'admin@aaa.com', '$2y$12$nddWL9YzNldOv8jK7H96YeAIW8zARa5gQr2Yj6oTPODHc3XTLrWZa', TRUE, TRUE)
ON CONFLICT (email) DO NOTHING;

INSERT INTO users (username, email, password, modele, verified)
VALUES ('aaa', 'aaa@aaa.com', '$2y$12$R7Q02juSA8cbyQB92NBdiumeddFfNUuUx0L8PcLvM0XiQphBs1ygW', TRUE, TRUE)
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
