-- ============================================================
-- RecipeSite — Database Schema
-- ============================================================
-- Author : Mounir Bekkar
-- Run with: mysql -u root -p recipe_db < database/schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Database ─────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS recipe_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE recipe_db;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username   VARCHAR(30)     NOT NULL UNIQUE,
    email      VARCHAR(255)    NOT NULL UNIQUE,
    password   VARCHAR(255)    NOT NULL,           -- bcrypt hash
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME        NULL     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Categories ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS categories (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Recipes ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS recipes (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED    NOT NULL,
    category_id      INT UNSIGNED    NOT NULL,
    title            VARCHAR(200)    NOT NULL,
    description      TEXT            NOT NULL,
    ingredients_text TEXT            NOT NULL,     -- raw text (display)
    steps_text       TEXT            NOT NULL,     -- raw text (display)
    prep_time        SMALLINT        NOT NULL DEFAULT 0,  -- minutes
    cook_time        SMALLINT        NOT NULL DEFAULT 0,  -- minutes
    servings         TINYINT         NOT NULL DEFAULT 4,
    image            VARCHAR(255)    NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NULL     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user     (user_id),
    INDEX idx_category (category_id),
    INDEX idx_created  (created_at),
    FULLTEXT INDEX ft_search (title, description),
    CONSTRAINT fk_recipe_user     FOREIGN KEY (user_id)     REFERENCES users(id)       ON DELETE CASCADE,
    CONSTRAINT fk_recipe_category FOREIGN KEY (category_id) REFERENCES categories(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Ingredients (structured) ─────────────────────────────────
CREATE TABLE IF NOT EXISTS ingredients (
    id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED  NOT NULL,
    position  TINYINT       NOT NULL DEFAULT 1,
    quantity  VARCHAR(30)   NOT NULL DEFAULT '',
    unit      VARCHAR(30)   NOT NULL DEFAULT '',
    name      VARCHAR(200)  NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_recipe (recipe_id),
    CONSTRAINT fk_ingredient_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Steps (structured) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS steps (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id   INT UNSIGNED NOT NULL,
    step_number TINYINT      NOT NULL,
    instruction TEXT         NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_recipe (recipe_id),
    CONSTRAINT fk_step_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Seed Data ────────────────────────────────────────────────
INSERT IGNORE INTO categories (name) VALUES
    ('Entrées'),
    ('Plats principaux'),
    ('Desserts'),
    ('Salades'),
    ('Soupes'),
    ('Pâtes'),
    ('Pizza'),
    ('Végétarien'),
    ('Vegan'),
    ('Rapide (< 30 min)');

-- Demo user: password = "password123"
INSERT IGNORE INTO users (username, email, password) VALUES (
    'demo',
    'demo@recipesite.fr',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);

-- Demo recipe
INSERT IGNORE INTO recipes
    (user_id, category_id, title, description, ingredients_text, steps_text, prep_time, cook_time, servings)
VALUES (
    1, 6,
    'Pasta Carbonara',
    'La vraie recette italienne de la pasta carbonara : crémeuse, savoureuse, sans crème !',
    '400 g spaghetti
200 g lardons fumés
4 jaunes d''oeuf
100 g pecorino romano râpé
Poivre noir
Sel',
    'Cuire les pâtes al dente dans de l''eau salée.
Faire revenir les lardons sans matière grasse jusqu''à ce qu''ils soient croustillants.
Mélanger jaunes d''oeuf et pecorino dans un bol. Poivrer généreusement.
Égoutter les pâtes en gardant un peu d''eau de cuisson.
Hors du feu, mélanger pâtes + lardons + mélange oeuf/fromage.
Ajouter un peu d''eau de cuisson pour une sauce crémeuse. Servir immédiatement.',
    10, 15, 4
);
