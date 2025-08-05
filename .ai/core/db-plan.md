# Database Schema — Menu Maker

## 1. Tables

### 1.1 `users`
Already provided by Laravel default migration (`0001_01_01_000000_create_users_table.php`).

| Column            | Type              | Constraints/Notes  |
| ----------------- | ----------------- | ------------------ |
| id                | BIGINT UNSIGNED   | PK, auto-increment |
| name              | VARCHAR(255)      | NOT NULL           |
| email             | VARCHAR(255)      | NOT NULL, UNIQUE   |
| email_verified_at | TIMESTAMP NULL    | —                  |
| password          | VARCHAR(255)      | NOT NULL           |
| remember_token    | VARCHAR(100) NULL | —                  |
| created_at        | TIMESTAMP         | —                  |
| updated_at        | TIMESTAMP         | —                  |

---



### 1.2 `family_members`
| Column     | Type                          | Constraints/Notes         |
| ---------- | ----------------------------- | ------------------------- |
| id         | BIGINT UNSIGNED               | PK, auto-increment        |
| user_id    | BIGINT UNSIGNED               | FK → `users.id`, NOT NULL |
| first_name | VARCHAR(255)                  | NOT NULL                  |
| birth_date | DATE                          | —                         |
| gender     | ENUM('male','female')         | NOT NULL                  |
| created_at | TIMESTAMP                     | —                         |
| updated_at | TIMESTAMP                     | —                         |

---

### 1.3 `units`
| Column                    | Type            | Constraints/Notes                           |
| ------------------------- | --------------- | ------------------------------------------- |
| id                        | BIGINT UNSIGNED | PK, auto-increment                          |
| code                      | VARCHAR(10)     | NOT NULL, UNIQUE (e.g. g, kg, ml, l, pcs)   |
| conversion_factor_to_base | DECIMAL(10,4)   | NOT NULL — factor relative to the base unit |
| created_at                | TIMESTAMP       | —                                           |
| updated_at                | TIMESTAMP       | —                                           |

---

### 1.4 `ingredients`
| Column     | Type            | Constraints/Notes  |
| ---------- | --------------- | ------------------ |
| id         | BIGINT UNSIGNED | PK, auto-increment |
| name       | VARCHAR(255)    | NOT NULL           |
| created_at | TIMESTAMP       | —                  |
| updated_at | TIMESTAMP       | —                  |

---

### 1.5 `recipes`
| Column       | Type                               | Constraints/Notes         |
| ------------ | ---------------------------------- | ------------------------- |
| id           | BIGINT UNSIGNED                    | PK, auto-increment        |
| user_id      | BIGINT UNSIGNED                    | FK → `users.id`, NOT NULL |
| name         | VARCHAR(255)                       | NOT NULL                  |
| category     | ENUM('breakfast','supper','dinner')| NOT NULL                  |
| instructions | TEXT                               | NOT NULL                  |
| calories     | DECIMAL(10,2)                      | NOT NULL — total kcal     |
| servings     | INT UNSIGNED                       | NOT NULL                  |
| created_at   | TIMESTAMP                          | —                         |
| updated_at   | TIMESTAMP                          | —                         |

---

### 1.6 `recipe_ingredients`
| Column        | Type            | Constraints/Notes               |
| ------------- | --------------- | ------------------------------- |
| id            | BIGINT UNSIGNED | PK, auto-increment              |
| recipe_id     | BIGINT UNSIGNED | FK → `recipes.id`, NOT NULL     |
| ingredient_id | BIGINT UNSIGNED | FK → `ingredients.id`, NOT NULL |
| quantity      | DECIMAL(10,2)   | NOT NULL                        |
| unit_id       | BIGINT UNSIGNED | FK → `units.id`, NOT NULL       |
| created_at    | TIMESTAMP       | —                               |
| updated_at    | TIMESTAMP       | —                               |

UNIQUE(recipe_id, ingredient_id)

---

### 1.7 `meal_plans`
| Column          | Type                                                          | Constraints/Notes                                        |
| --------------- | ------------------------------------------------------------- | -------------------------------------------------------- |
| id              | BIGINT UNSIGNED                                               | PK, auto-increment                                       |
| user_id         | BIGINT UNSIGNED                                               | FK → `users.id`, NOT NULL                                |
| start_date      | DATE                                                          | NOT NULL                                                 |
| end_date        | DATE                                                          | NOT NULL, CHECK (end_date = start_date + INTERVAL 6 DAY) |
| status          | ENUM('pending','processing','done','error') DEFAULT 'pending' | NOT NULL, indexed                                        |
| generation_meta | JSON NULL                                                     | Stores `started_at`, `finished_at`, etc.                 |
| pdf_path        | VARCHAR(255) NULL                                             | Relative storage path                                    |
| pdf_size        | BIGINT UNSIGNED NULL                                          | Size in bytes                                            |
| created_at      | TIMESTAMP                                                     | —                                                        |
| updated_at      | TIMESTAMP                                                     | —                                                        |

UNIQUE(user_id, start_date)

---

### 1.8 `meals`
| Column        | Type                               | Constraints/Notes                                                       |
| ------------- | ---------------------------------- | ----------------------------------------------------------------------- |
| id            | BIGINT UNSIGNED                    | PK, auto-increment                                                      |
| meal_plan_id  | BIGINT UNSIGNED                    | FK → `meal_plans.id`, NOT NULL                                          |
| recipe_id     | BIGINT UNSIGNED                    | FK → `recipes.id`, NOT NULL                                             |
| meal_date     | DATE                               | NOT NULL (between start_date & end_date, enforced in application logic) |
| meal_category | ENUM('breakfast','supper','dinner')| NOT NULL                                                                |
| created_at    | TIMESTAMP                          | —                                                                       |
| updated_at    | TIMESTAMP                          | —                                                                       |

---

### 1.9 `logs_meal_plan`
| Column       | Type                                        | Constraints/Notes              |
| ------------ | ------------------------------------------- | ------------------------------ |
| id           | BIGINT UNSIGNED                             | PK, auto-increment             |
| meal_plan_id | BIGINT UNSIGNED                             | FK → `meal_plans.id`, NOT NULL |
| started_at   | DATETIME                                    | NOT NULL                       |
| finished_at  | DATETIME NULL                               | —                              |
| status       | ENUM('pending','processing','done','error') | NOT NULL                       |
| created_at   | TIMESTAMP                                   | —                              |

---

### 1.10 `password_resets`
Laravel provides `password_reset_tokens`; include for completeness if not yet migrated.
| Column     | Type         | Constraints/Notes |
| ---------- | ------------ | ----------------- |
| email      | VARCHAR(255) | PK                |
| token      | VARCHAR(255) | NOT NULL          |
| created_at | TIMESTAMP    | NOT NULL          |


### 1.11 Laravel default support tables
`cache` and `jobs` tables remain as generated by default migrations.

---

## 2. Relationships

1. **`users` 1--N `family_members`**.
2. **`users` 1--N `recipes`**.
3. **`recipes` N--N `ingredients`** via `recipe_ingredients`.
4. **`users` 1--N `meal_plans`**.
5. **`meal_plans` 1--N `meals`**.
6. **`recipes` 1--N `meals`** (each meal references one recipe).
7. **`units` 1--N `recipe_ingredients`**.
8. **`meal_plans` 1--N `logs_meal_plan`**.

All foreign keys use `ON DELETE CASCADE` to ensure full cleanup when a parent record is removed (e.g., deleting a user cascades through meals, meal_plans, etc.).

---

## 3. Indexes

* `users.email` — UNIQUE
* `family_members.user_id` — BTREE
* `units.code` — UNIQUE
* `ingredients.name` — BTREE index + FULLTEXT index (`FULLTEXT (name)`)
* `recipes.name` — BTREE index + FULLTEXT index (`FULLTEXT (name)`)
* `recipe_ingredients.recipe_id, ingredient_id` — UNIQUE composite + separate BTREE on each FK
* `meal_plans`:
  * `UNIQUE (user_id, start_date)`
  * BTREE on `status`
* `meals`:
  * BTREE on `(meal_plan_id, meal_date, meal_category)`
* `logs_meal_plan.meal_plan_id` — BTREE

---

## 4. Additional Notes

1. All timestamps are stored in the `Europe/Warsaw` timezone without UTC conversion, matching application logic.
2. `generation_meta` in `meal_plans` keeps auxiliary data (e.g., AI parameters, progress) without fragmenting the schema; if query-ability of `started_at` / `finished_at` becomes critical, consider separate columns plus indexed views.
3. `CHECK (end_date = start_date + INTERVAL 6 DAY)` ensures every meal plan covers exactly seven consecutive days; MySQL 8.0 allows check constraints.
4. The schema is normalized to 3NF; no soft-delete columns are used as per requirements.
5. PDF files are stored in the filesystem; only metadata (`pdf_path`, `pdf_size`) resides in the database.
6. All enumerations are fixed; if future extensibility is required, convert enums to lookup tables.
