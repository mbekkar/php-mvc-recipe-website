<?php
/**
 * RecipeModel.php
 * ----------------
 * All database interactions for recipes.
 * Uses PDO prepared statements — safe against SQL injection.
 *
 * Author : Mounir Bekkar
 */

require_once __DIR__ . '/../Database.php';

class RecipeModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── READ ──────────────────────────────────────────────────────────────────

    /**
     * Paginated list of all recipes with author and category info.
     */
    public function getAll(int $limit = 12, int $offset = 0, string $search = ''): array
    {
        $sql = "
            SELECT
                r.id,
                r.title,
                r.description,
                r.prep_time,
                r.cook_time,
                r.servings,
                r.image,
                r.created_at,
                u.username   AS author,
                c.name       AS category
            FROM   recipes r
            JOIN   users      u ON r.user_id      = u.id
            JOIN   categories c ON r.category_id  = c.id
        ";

        $params = [];

        if ($search !== '') {
            $sql .= " WHERE r.title LIKE :search OR r.description LIKE :search2 OR c.name LIKE :search3 ";
            $params[':search']  = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
        }

        $sql .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Total count of recipes (for pagination).
     */
    public function count(string $search = ''): int
    {
        $sql    = "SELECT COUNT(*) FROM recipes r JOIN categories c ON r.category_id = c.id";
        $params = [];

        if ($search !== '') {
            $sql .= " WHERE r.title LIKE :search OR c.name LIKE :search2";
            $params[':search']  = "%{$search}%";
            $params[':search2'] = "%{$search}%";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Find a single recipe by its ID (with author, category, ingredients, steps).
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.*,
                u.username AS author,
                u.id       AS author_id,
                c.name     AS category
            FROM recipes r
            JOIN users      u ON r.user_id     = u.id
            JOIN categories c ON r.category_id = c.id
            WHERE r.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $recipe = $stmt->fetch();

        if (!$recipe) {
            return null;
        }

        // Load ingredients
        $stmt = $this->db->prepare("
            SELECT quantity, unit, name
            FROM   ingredients
            WHERE  recipe_id = :id
            ORDER  BY position
        ");
        $stmt->execute([':id' => $id]);
        $recipe['ingredients'] = $stmt->fetchAll();

        // Load steps
        $stmt = $this->db->prepare("
            SELECT step_number, instruction
            FROM   steps
            WHERE  recipe_id = :id
            ORDER  BY step_number
        ");
        $stmt->execute([':id' => $id]);
        $recipe['steps'] = $stmt->fetchAll();

        return $recipe;
    }

    /**
     * All recipes belonging to a specific user.
     */
    public function findByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id, r.title, r.image, r.prep_time, r.cook_time, r.created_at,
                   c.name AS category
            FROM   recipes r
            JOIN   categories c ON r.category_id = c.id
            WHERE  r.user_id = :user_id
            ORDER  BY r.created_at DESC
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    // ── CREATE ────────────────────────────────────────────────────────────────

    /**
     * Insert a new recipe with its ingredients and steps.
     * All inserts are wrapped in a transaction.
     */
    public function create(array $data): int
    {
        $this->db->beginTransaction();

        try {
            // Main recipe row
            $stmt = $this->db->prepare("
                INSERT INTO recipes
                    (user_id, category_id, title, description,
                     ingredients_text, steps_text,
                     prep_time, cook_time, servings, image, created_at)
                VALUES
                    (:user_id, :category_id, :title, :description,
                     :ingredients_text, :steps_text,
                     :prep_time, :cook_time, :servings, :image, NOW())
            ");
            $stmt->execute([
                ':user_id'           => $data['user_id'],
                ':category_id'       => $data['category_id'],
                ':title'             => $data['title'],
                ':description'       => $data['description'],
                ':ingredients_text'  => $data['ingredients_text'],
                ':steps_text'        => $data['steps_text'],
                ':prep_time'         => $data['prep_time'],
                ':cook_time'         => $data['cook_time'],
                ':servings'          => $data['servings'],
                ':image'             => $data['image'] ?? null,
            ]);

            $recipeId = (int) $this->db->lastInsertId();

            // Structured ingredients
            if (!empty($data['ingredients'])) {
                $ingStmt = $this->db->prepare("
                    INSERT INTO ingredients (recipe_id, position, quantity, unit, name)
                    VALUES (:recipe_id, :pos, :qty, :unit, :name)
                ");
                foreach ($data['ingredients'] as $pos => $ing) {
                    $ingStmt->execute([
                        ':recipe_id' => $recipeId,
                        ':pos'       => $pos + 1,
                        ':qty'       => $ing['quantity'] ?? '',
                        ':unit'      => $ing['unit']     ?? '',
                        ':name'      => $ing['name']     ?? '',
                    ]);
                }
            }

            // Structured steps
            if (!empty($data['steps'])) {
                $stepStmt = $this->db->prepare("
                    INSERT INTO steps (recipe_id, step_number, instruction)
                    VALUES (:recipe_id, :num, :instruction)
                ");
                foreach ($data['steps'] as $num => $instruction) {
                    $stepStmt->execute([
                        ':recipe_id'   => $recipeId,
                        ':num'         => $num + 1,
                        ':instruction' => $instruction,
                    ]);
                }
            }

            $this->db->commit();
            return $recipeId;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────

    public function update(int $id, array $data): bool
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                UPDATE recipes
                SET category_id      = :category_id,
                    title            = :title,
                    description      = :description,
                    ingredients_text = :ingredients_text,
                    steps_text       = :steps_text,
                    prep_time        = :prep_time,
                    cook_time        = :cook_time,
                    servings         = :servings,
                    image            = COALESCE(:image, image),
                    updated_at       = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':id'                => $id,
                ':category_id'       => $data['category_id'],
                ':title'             => $data['title'],
                ':description'       => $data['description'],
                ':ingredients_text'  => $data['ingredients_text'],
                ':steps_text'        => $data['steps_text'],
                ':prep_time'         => $data['prep_time'],
                ':cook_time'         => $data['cook_time'],
                ':servings'          => $data['servings'],
                ':image'             => $data['image'] ?? null,
            ]);

            // Re-insert ingredients and steps
            $this->db->prepare("DELETE FROM ingredients WHERE recipe_id = :id")->execute([':id' => $id]);
            $this->db->prepare("DELETE FROM steps       WHERE recipe_id = :id")->execute([':id' => $id]);

            if (!empty($data['ingredients'])) {
                $ingStmt = $this->db->prepare("
                    INSERT INTO ingredients (recipe_id, position, quantity, unit, name)
                    VALUES (:recipe_id, :pos, :qty, :unit, :name)
                ");
                foreach ($data['ingredients'] as $pos => $ing) {
                    $ingStmt->execute([
                        ':recipe_id' => $id,
                        ':pos'       => $pos + 1,
                        ':qty'       => $ing['quantity'] ?? '',
                        ':unit'      => $ing['unit']     ?? '',
                        ':name'      => $ing['name']     ?? '',
                    ]);
                }
            }

            if (!empty($data['steps'])) {
                $stepStmt = $this->db->prepare("
                    INSERT INTO steps (recipe_id, step_number, instruction)
                    VALUES (:recipe_id, :num, :instruction)
                ");
                foreach ($data['steps'] as $num => $instruction) {
                    $stepStmt->execute([
                        ':recipe_id'   => $id,
                        ':num'         => $num + 1,
                        ':instruction' => $instruction,
                    ]);
                }
            }

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function delete(int $id): bool
    {
        // Cascade deletes ingredients + steps via FK ON DELETE CASCADE
        $stmt = $this->db->prepare("DELETE FROM recipes WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ── OWNERSHIP ─────────────────────────────────────────────────────────────

    public function belongsToUser(int $recipeId, int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM recipes WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $recipeId, ':uid' => $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
