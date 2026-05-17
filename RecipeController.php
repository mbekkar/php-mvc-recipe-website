<?php
/**
 * RecipeController.php
 * ---------------------
 * Handles: list, show, create, edit, delete recipes.
 * All write operations require authentication and CSRF verification.
 *
 * Author : Mounir Bekkar
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/RecipeModel.php';
require_once __DIR__ . '/../Models/CategoryModel.php';

class RecipeController extends BaseController
{
    private RecipeModel   $recipes;
    private CategoryModel $categories;

    public function __construct()
    {
        $this->recipes    = new RecipeModel();
        $this->categories = new CategoryModel();
    }

    // ── GET /  ────────────────────────────────────────────────────────────────

    public function index(array $params): void
    {
        $perPage = 12;
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $search  = trim($_GET['search'] ?? '');
        $offset  = ($page - 1) * $perPage;

        $recipes    = $this->recipes->getAll($perPage, $offset, $search);
        $total      = $this->recipes->count($search);
        $totalPages = (int) ceil($total / $perPage);

        $this->view('recipes/index', [
            'recipes'     => $recipes,
            'search'      => $search,
            'page'        => $page,
            'totalPages'  => $totalPages,
            'total'       => $total,
            'currentUser' => $this->currentUser(),
            'flash'       => $this->getFlash(),
        ]);
    }

    // ── GET /recipes/:id ──────────────────────────────────────────────────────

    public function show(array $params): void
    {
        $recipe = $this->recipes->findById((int) $params['id']);

        if (!$recipe) {
            http_response_code(404);
            $this->view('404');
            return;
        }

        $this->view('recipes/show', [
            'recipe'      => $recipe,
            'currentUser' => $this->currentUser(),
            'flash'       => $this->getFlash(),
        ]);
    }

    // ── GET /recipes/create ───────────────────────────────────────────────────

    public function create(array $params): void
    {
        $this->requireAuth();

        $this->view('recipes/create', [
            'categories'  => $this->categories->getAll(),
            'currentUser' => $this->currentUser(),
            'csrf'        => $this->generateCsrfToken(),
            'flash'       => $this->getFlash(),
        ]);
    }

    // ── POST /recipes ─────────────────────────────────────────────────────────

    public function store(array $params): void
    {
        $this->requireAuth();
        $this->verifyCsrfToken();

        $errors = $this->validateRecipe($_POST);

        if (!empty($errors)) {
            $this->keepOld(['title', 'description', 'ingredients_text', 'steps_text',
                            'prep_time', 'cook_time', 'servings', 'category_id']);
            foreach ($errors as $e) {
                $this->flash('error', $e);
            }
            $this->redirect('/recipes/create');
            return;
        }

        $image = $this->handleImageUpload();

        // Parse ingredients (one per line: "200 g flour")
        $ingredientLines = array_filter(
            array_map('trim', explode("\n", $_POST['ingredients_text']))
        );
        $ingredients = array_map(function (string $line) {
            $parts = preg_split('/\s+/', $line, 3);
            return [
                'quantity' => $parts[0] ?? '',
                'unit'     => $parts[1] ?? '',
                'name'     => $parts[2] ?? $line,
            ];
        }, $ingredientLines);

        // Parse steps (one per line)
        $steps = array_values(array_filter(
            array_map('trim', explode("\n", $_POST['steps_text']))
        ));

        $id = $this->recipes->create([
            'user_id'          => $_SESSION['user_id'],
            'category_id'      => (int) $_POST['category_id'],
            'title'            => htmlspecialchars(trim($_POST['title'])),
            'description'      => htmlspecialchars(trim($_POST['description'])),
            'ingredients_text' => trim($_POST['ingredients_text']),
            'steps_text'       => trim($_POST['steps_text']),
            'prep_time'        => (int) $_POST['prep_time'],
            'cook_time'        => (int) $_POST['cook_time'],
            'servings'         => (int) $_POST['servings'],
            'image'            => $image,
            'ingredients'      => $ingredients,
            'steps'            => $steps,
        ]);

        $this->clearOld();
        $this->flash('success', 'Recette créée avec succès !');
        $this->redirect("/recipes/{$id}");
    }

    // ── GET /recipes/:id/edit ─────────────────────────────────────────────────

    public function edit(array $params): void
    {
        $this->requireAuth();

        $recipe = $this->recipes->findById((int) $params['id']);

        if (!$recipe || !$this->recipes->belongsToUser($recipe['id'], $_SESSION['user_id'])) {
            $this->flash('error', 'Accès non autorisé.');
            $this->redirect('/');
            return;
        }

        $this->view('recipes/edit', [
            'recipe'      => $recipe,
            'categories'  => $this->categories->getAll(),
            'currentUser' => $this->currentUser(),
            'csrf'        => $this->generateCsrfToken(),
            'flash'       => $this->getFlash(),
        ]);
    }

    // ── POST /recipes/:id/update ──────────────────────────────────────────────

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->verifyCsrfToken();

        $id     = (int) $params['id'];
        $recipe = $this->recipes->findById($id);

        if (!$recipe || !$this->recipes->belongsToUser($id, $_SESSION['user_id'])) {
            $this->flash('error', 'Accès non autorisé.');
            $this->redirect('/');
            return;
        }

        $errors = $this->validateRecipe($_POST);

        if (!empty($errors)) {
            foreach ($errors as $e) {
                $this->flash('error', $e);
            }
            $this->redirect("/recipes/{$id}/edit");
            return;
        }

        $image = $this->handleImageUpload() ?? $recipe['image'];

        $ingredientLines = array_filter(array_map('trim', explode("\n", $_POST['ingredients_text'])));
        $ingredients     = array_map(function (string $line) {
            $parts = preg_split('/\s+/', $line, 3);
            return ['quantity' => $parts[0] ?? '', 'unit' => $parts[1] ?? '', 'name' => $parts[2] ?? $line];
        }, $ingredientLines);

        $steps = array_values(array_filter(array_map('trim', explode("\n", $_POST['steps_text']))));

        $this->recipes->update($id, [
            'category_id'      => (int) $_POST['category_id'],
            'title'            => htmlspecialchars(trim($_POST['title'])),
            'description'      => htmlspecialchars(trim($_POST['description'])),
            'ingredients_text' => trim($_POST['ingredients_text']),
            'steps_text'       => trim($_POST['steps_text']),
            'prep_time'        => (int) $_POST['prep_time'],
            'cook_time'        => (int) $_POST['cook_time'],
            'servings'         => (int) $_POST['servings'],
            'image'            => $image,
            'ingredients'      => $ingredients,
            'steps'            => $steps,
        ]);

        $this->flash('success', 'Recette mise à jour !');
        $this->redirect("/recipes/{$id}");
    }

    // ── POST /recipes/:id/delete ──────────────────────────────────────────────

    public function destroy(array $params): void
    {
        $this->requireAuth();
        $this->verifyCsrfToken();

        $id     = (int) $params['id'];
        $recipe = $this->recipes->findById($id);

        if (!$recipe || !$this->recipes->belongsToUser($id, $_SESSION['user_id'])) {
            $this->flash('error', 'Accès non autorisé.');
            $this->redirect('/');
            return;
        }

        // Delete image file if it exists
        if ($recipe['image']) {
            $imgPath = __DIR__ . '/../../public/uploads/' . $recipe['image'];
            if (file_exists($imgPath)) {
                unlink($imgPath);
            }
        }

        $this->recipes->delete($id);
        $this->flash('success', 'Recette supprimée.');
        $this->redirect('/');
    }

    // ── GET /my-recipes ───────────────────────────────────────────────────────

    public function myRecipes(array $params): void
    {
        $this->requireAuth();

        $recipes = $this->recipes->findByUser($_SESSION['user_id']);

        $this->view('recipes/my-recipes', [
            'recipes'     => $recipes,
            'currentUser' => $this->currentUser(),
            'flash'       => $this->getFlash(),
        ]);
    }

    // ── PRIVATE HELPERS ───────────────────────────────────────────────────────

    private function validateRecipe(array $data): array
    {
        $errors = [];

        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Le titre est obligatoire.';
        } elseif (mb_strlen($data['title']) > 200) {
            $errors[] = 'Le titre ne doit pas dépasser 200 caractères.';
        }

        if (empty(trim($data['description'] ?? ''))) {
            $errors[] = 'La description est obligatoire.';
        }

        if (empty(trim($data['ingredients_text'] ?? ''))) {
            $errors[] = 'Les ingrédients sont obligatoires.';
        }

        if (empty(trim($data['steps_text'] ?? ''))) {
            $errors[] = 'Les étapes sont obligatoires.';
        }

        if (!isset($data['category_id']) || (int) $data['category_id'] <= 0) {
            $errors[] = 'Veuillez sélectionner une catégorie.';
        }

        if (!isset($data['prep_time']) || (int) $data['prep_time'] < 1) {
            $errors[] = 'Le temps de préparation doit être supérieur à 0.';
        }

        if (!isset($data['servings']) || (int) $data['servings'] < 1) {
            $errors[] = 'Le nombre de portions doit être supérieur à 0.';
        }

        return $errors;
    }

    private function handleImageUpload(): ?string
    {
        if (empty($_FILES['image']['name'])) {
            return null;
        }

        $allowed   = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize   = 5 * 1024 * 1024; // 5 MB
        $file      = $_FILES['image'];

        if (!in_array($file['type'], $allowed, true)) {
            $this->flash('error', 'Format d\'image non supporté (JPG, PNG, WebP uniquement).');
            return null;
        }

        if ($file['size'] > $maxSize) {
            $this->flash('error', 'L\'image ne doit pas dépasser 5 Mo.');
            return null;
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('recipe_', true) . '.' . strtolower($ext);
        $dest     = __DIR__ . '/../../public/uploads/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->flash('error', 'Erreur lors de l\'upload de l\'image.');
            return null;
        }

        return $filename;
    }
}
