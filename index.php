<?php
/**
 * Front Controller — public/index.php
 * =====================================
 * ALL HTTP requests are routed through here by Apache/.htaccess or Nginx.
 *
 * Author : Mounir Bekkar
 */

declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────

define('ROOT', dirname(__DIR__));

require ROOT . '/config/app.php';
require ROOT . '/app/Router.php';
require ROOT . '/app/Controllers/BaseController.php';

session_start();

// ── Routes ────────────────────────────────────────────────────────────────────

$router = new Router();

// Public
$router->get('/',                    'RecipeController@index');
$router->get('/recipes/:id',         'RecipeController@show');

// Auth
$router->get('/register',            'AuthController@showRegister');
$router->post('/register',           'AuthController@register');
$router->get('/login',               'AuthController@showLogin');
$router->post('/login',              'AuthController@login');
$router->post('/logout',             'AuthController@logout');

// Recipes — authenticated
$router->get('/recipes/create',      'RecipeController@create');
$router->post('/recipes',            'RecipeController@store');
$router->get('/recipes/:id/edit',    'RecipeController@edit');
$router->post('/recipes/:id/update', 'RecipeController@update');
$router->post('/recipes/:id/delete', 'RecipeController@destroy');
$router->get('/my-recipes',          'RecipeController@myRecipes');

// ── Dispatch ──────────────────────────────────────────────────────────────────

$router->dispatch(
    $_SERVER['REQUEST_URI'],
    $_SERVER['REQUEST_METHOD']
);
