CREATE TABLE IF NOT EXISTS users (
    id          SERIAL PRIMARY KEY,
    username    VARCHAR(50)  UNIQUE NOT NULL,
    email       VARCHAR(255) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    verified    BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- User de test : login test@test.com / mot de passe "password"
INSERT INTO users (username, email, password, verified)
VALUES ('test', 'test@test.com', '$2y$12$CcUDjoD4Rc47EWfz.MjOrOCdu.BXMvlsd1bKbfYUHgmdn7Q4/KQwK', TRUE)
ON CONFLICT (email) DO NOTHING;
