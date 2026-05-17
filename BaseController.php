<?php
/**
 * BaseController.php
 * -------------------
 * Shared helpers: view rendering, redirects, auth guards, CSRF, flash messages.
 *
 * Author : Mounir Bekkar
 */

require_once __DIR__ . '/../Database.php';

abstract class BaseController
{
    // ── View ──────────────────────────────────────────────────────────────────

    /**
     * Render a view file with optional data.
     * Views live in app/Views/  (e.g. 'recipes/index' → app/Views/recipes/index.php)
     */
    protected function view(string $name, array $data = []): void
    {
        extract($data);
        $viewFile = __DIR__ . "/../Views/{$name}.php";

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$name}");
        }

        require $viewFile;
    }

    // ── Redirect ──────────────────────────────────────────────────────────────

    protected function redirect(string $path): void
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        header("Location: {$base}{$path}");
        exit;
    }

    // ── Auth Guards ───────────────────────────────────────────────────────────

    protected function requireAuth(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->flash('error', 'Veuillez vous connecter pour accéder à cette page.');
            $this->redirect('/login');
        }
    }

    protected function requireGuest(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/');
        }
    }

    protected function currentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        static $user = null;
        if ($user === null) {
            $db   = Database::getInstance();
            $stmt = $db->prepare('SELECT id, username, email FROM users WHERE id = :id');
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
        return $user;
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────

    protected function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function verifyCsrfToken(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('CSRF token mismatch. Please go back and try again.');
        }
        // Regenerate after each verified POST
        unset($_SESSION['csrf_token']);
    }

    // ── Flash Messages ────────────────────────────────────────────────────────

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type][] = $message;
    }

    protected function getFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    // ── Input Helpers ─────────────────────────────────────────────────────────

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function old(string $key): string
    {
        return htmlspecialchars($_SESSION['old'][$key] ?? '', ENT_QUOTES);
    }

    protected function keepOld(array $keys): void
    {
        foreach ($keys as $key) {
            $_SESSION['old'][$key] = $_POST[$key] ?? '';
        }
    }

    protected function clearOld(): void
    {
        unset($_SESSION['old']);
    }

    // ── JSON response ─────────────────────────────────────────────────────────

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
