<?php
declare(strict_types=1);

class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $dbPath = __DIR__ . '/../data/auth.sqlite';
            self::$pdo = new PDO("sqlite:$dbPath", null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::migrate();
        }
        return self::$pdo;
    }

    private static function migrate(): void
    {
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT    NOT NULL UNIQUE,
                email         TEXT    NOT NULL UNIQUE,
                password_hash TEXT    NOT NULL,
                verified      INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
            )
        ");
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                token      TEXT    NOT NULL UNIQUE,
                expires_at TEXT    NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS email_verifications (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL,
                token         TEXT    NOT NULL UNIQUE,
                expires_at    TEXT    NOT NULL,
                pending_email TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        // Migration: add pending_email on existing databases
        try {
            self::$pdo->exec('ALTER TABLE email_verifications ADD COLUMN pending_email TEXT');
        } catch (\Exception) { /* colonne déjà présente */ }
    }
}
