# 🍳 RecipeSite — MVC PHP Recipe Website

> A full-stack PHP web application to share and discover recipes.  
> Built from scratch with a **custom MVC framework** — no Laravel, no Symfony.

**Author:** Mounir Bekkar · Licence Informatique · Université Lumière Lyon 2  
**GitHub:** [github.com/mbekkar](https://github.com/mbekkar)

---

## ✨ Features

| Feature | Details |
|---------|---------|
| **Custom MVC** | Router, BaseController, Models — zero external framework |
| **Authentication** | Register · Login · Logout · Session management |
| **Passwords** | bcrypt via `password_hash()` — cost factor 12 |
| **CSRF protection** | Token generated per session, verified on every POST |
| **CRUD recipes** | Create · Read · Update · Delete with ownership check |
| **Search** | Full-text search on title, description and category |
| **Pagination** | Configurable items per page |
| **Image upload** | JPG/PNG/WebP · max 5 MB · server-side validation |
| **SQL injection** | 100% PDO prepared statements |
| **XSS** | `htmlspecialchars()` on all user output |
| **Clean URLs** | Apache `.htaccess` rewrite rules |
| **Responsive** | Mobile-first CSS — works on all screen sizes |

---

## 🗂️ Project Structure

```
recipe_mvc/
├── app/
│   ├── Router.php                    ← URL routing (regex, named params)
│   ├── Database.php                  ← PDO singleton
│   ├── Controllers/
│   │   ├── BaseController.php        ← Auth guards, CSRF, flash, view()
│   │   ├── RecipeController.php      ← Full CRUD for recipes
│   │   └── AuthController.php        ← Register, login, logout
│   ├── Models/
│   │   ├── RecipeModel.php           ← All recipe DB queries
│   │   ├── UserModel.php             ← User auth + validation
│   │   └── CategoryModel.php         ← Category list
│   └── Views/
│       ├── layouts/main.php          ← Shared HTML layout
│       ├── recipes/                  ← index, show, create, edit, my-recipes
│       ├── auth/                     ← login, register
│       └── 404.php
├── config/
│   ├── app.php                       ← App constants, timezone, env
│   └── database.php                  ← DB credentials (env vars)
├── database/
│   └── schema.sql                    ← Full MySQL schema + seed data
├── public/                           ← Web root (point Apache/Nginx here)
│   ├── index.php                     ← Front controller
│   ├── .htaccess                     ← URL rewriting + security headers
│   ├── css/app.css
│   ├── js/app.js
│   └── uploads/                      ← User-uploaded images
└── README.md
```

---

## 🚀 Installation

### Requirements
- PHP 8.1+
- MySQL 8.0+ or MariaDB 10.6+
- Apache with `mod_rewrite` (or Nginx)

### Steps

**1. Clone the repository**
```bash
git clone https://github.com/mbekkar/recipe-mvc.git
cd recipe-mvc
```

**2. Create the database**
```bash
mysql -u root -p < database/schema.sql
```

**3. Configure the database connection**

Edit `config/database.php` or set environment variables:
```bash
export DB_HOST=127.0.0.1
export DB_NAME=recipe_db
export DB_USER=root
export DB_PASSWORD=yourpassword
```

**4. Configure Apache**

Point your VirtualHost `DocumentRoot` to the `public/` folder:
```apache
<VirtualHost *:80>
    ServerName recipesite.local
    DocumentRoot /path/to/recipe_mvc/public
    <Directory /path/to/recipe_mvc/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Or use PHP's built-in server for local dev:
```bash
cd public
php -S localhost:8000
```

**5. Create the uploads directory**
```bash
mkdir -p public/uploads
chmod 755 public/uploads
```

**6. Open in your browser**
```
http://localhost:8000
```

Demo account: `demo@recipesite.fr` / `password123`

---

## 🧠 Architecture

```
Browser  →  public/index.php (Front Controller)
                    │
                    ▼
              Router::dispatch()
              (matches URI + method → Controller@action)
                    │
                    ▼
         ┌──────────────────────┐
         │      Controller      │
         │  ├ requireAuth()     │ ← Session / CSRF
         │  ├ verifyCsrfToken() │
         │  ├ Model->query()    │ ← Business logic + DB
         │  └ view('...')       │ ← Renders PHP view
         └──────────────────────┘
                    │
                    ▼
              app/Views/*.php
              (wrapped by layouts/main.php)
```

---

## 🔐 Security Measures

| Threat | Protection |
|--------|-----------|
| **SQL Injection** | PDO prepared statements on every query |
| **XSS** | `htmlspecialchars()` on all user-generated output |
| **CSRF** | Random token generated per session, verified on POST |
| **Session Fixation** | `session_regenerate_id(true)` on login |
| **Password Storage** | bcrypt `password_hash()` — cost 12 |
| **File Upload** | MIME type check + size limit + random filename |
| **Directory Traversal** | Uploads directory has no PHP execution |
| **Information Leak** | Errors hidden in production (`APP_ENV=production`) |

---

## 📊 Database Schema

```
users           → id, username, email, password (bcrypt), created_at
categories      → id, name
recipes         → id, user_id, category_id, title, description,
                  ingredients_text, steps_text,
                  prep_time, cook_time, servings, image, created_at
ingredients     → id, recipe_id, position, quantity, unit, name
steps           → id, recipe_id, step_number, instruction
```

Foreign keys with `ON DELETE CASCADE` ensure referential integrity.

---

## 🚀 Possible Improvements

- [ ] User profile page with avatar
- [ ] Recipe ratings and comments
- [ ] Favourite recipes list
- [ ] RESTful JSON API endpoints
- [ ] Docker Compose setup (PHP + MySQL + Nginx)
- [ ] PHPUnit tests for controllers and models
- [ ] Email verification on registration

---

## 📄 License

MIT License — free to use and modify.
