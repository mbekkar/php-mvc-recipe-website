<?php
/**
 * AuthController.php
 * -------------------
 * Handles: register, login, logout.
 * Passwords hashed with bcrypt. Sessions regenerated on login.
 *
 * Author : Mounir Bekkar
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/UserModel.php';

class AuthController extends BaseController
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    // ── GET /register ─────────────────────────────────────────────────────────

    public function showRegister(array $params): void
    {
        $this->requireGuest();
        $this->view('auth/register', [
            'csrf'  => $this->generateCsrfToken(),
            'flash' => $this->getFlash(),
        ]);
    }

    // ── POST /register ────────────────────────────────────────────────────────

    public function register(array $params): void
    {
        $this->requireGuest();
        $this->verifyCsrfToken();

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $confirm  = $_POST['confirm']       ?? '';

        $errors = $this->validateRegistration($username, $email, $password, $confirm);

        if (!empty($errors)) {
            $this->keepOld(['username', 'email']);
            foreach ($errors as $e) {
                $this->flash('error', $e);
            }
            $this->redirect('/register');
            return;
        }

        $userId = $this->users->create($username, $email, $password);

        // Auto-login after registration
        session_regenerate_id(true);
        $_SESSION['user_id']  = $userId;
        $_SESSION['username'] = $username;

        $this->clearOld();
        $this->flash('success', "Bienvenue {$username} ! Votre compte a été créé.");
        $this->redirect('/');
    }

    // ── GET /login ────────────────────────────────────────────────────────────

    public function showLogin(array $params): void
    {
        $this->requireGuest();
        $this->view('auth/login', [
            'csrf'  => $this->generateCsrfToken(),
            'flash' => $this->getFlash(),
        ]);
    }

    // ── POST /login ───────────────────────────────────────────────────────────

    public function login(array $params): void
    {
        $this->requireGuest();
        $this->verifyCsrfToken();

        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';

        if (empty($email) || empty($password)) {
            $this->flash('error', 'Email et mot de passe requis.');
            $this->redirect('/login');
            return;
        }

        $user = $this->users->authenticate($email, $password);

        if (!$user) {
            // Intentionally vague message (don't reveal which field is wrong)
            $this->flash('error', 'Email ou mot de passe incorrect.');
            $this->keepOld(['email']);
            $this->redirect('/login');
            return;
        }

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];

        $this->clearOld();
        $this->flash('success', "Bon retour, {$user['username']} !");
        $this->redirect('/');
    }

    // ── POST /logout ──────────────────────────────────────────────────────────

    public function logout(array $params): void
    {
        $this->verifyCsrfToken();

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();

        $this->redirect('/login');
    }

    // ── VALIDATION ────────────────────────────────────────────────────────────

    private function validateRegistration(
        string $username,
        string $email,
        string $password,
        string $confirm
    ): array {
        $errors = [];

        // Username
        if (empty($username)) {
            $errors[] = 'Le nom d\'utilisateur est obligatoire.';
        } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 30) {
            $errors[] = 'Le nom d\'utilisateur doit contenir entre 3 et 30 caractères.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres et _.';
        } elseif ($this->users->usernameExists($username)) {
            $errors[] = 'Ce nom d\'utilisateur est déjà pris.';
        }

        // Email
        if (empty($email)) {
            $errors[] = 'L\'email est obligatoire.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        } elseif ($this->users->emailExists($email)) {
            $errors[] = 'Un compte existe déjà avec cet email.';
        }

        // Password
        if (empty($password)) {
            $errors[] = 'Le mot de passe est obligatoire.';
        } elseif (mb_strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }

        return $errors;
    }
}
