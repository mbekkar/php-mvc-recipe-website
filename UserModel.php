<?php
/**
 * UserModel.php
 * --------------
 * User registration, login, profile.
 * Passwords hashed with bcrypt via password_hash().
 *
 * Author : Mounir Bekkar
 */

require_once __DIR__ . '/../Database.php';

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── FIND ──────────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, username, email, created_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        return $stmt->fetch() ?: null;
    }

    // ── CREATE ────────────────────────────────────────────────────────────────

    /**
     * Register a new user.
     * Returns the new user's ID, or throws on duplicate email/username.
     */
    public function create(string $username, string $email, string $password): int
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password, created_at)
            VALUES (:username, :email, :password, NOW())
        ");
        $stmt->execute([
            ':username' => $username,
            ':email'    => $email,
            ':password' => $hash,
        ]);

        return (int) $this->db->lastInsertId();
    }

    // ── AUTHENTICATE ──────────────────────────────────────────────────────────

    /**
     * Verify email + password. Returns the user row (without password) or null.
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        // Never return the hash to the calling code
        unset($user['password']);
        return $user;
    }

    // ── VALIDATION HELPERS ────────────────────────────────────────────────────

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
