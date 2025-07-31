# Recipe API Documentation

## Overview

The Recipe API provides full CRUD operations for managing user recipes in the Menu Maker application. All endpoints require authentication and follow RESTful conventions.

## Base URL
```
/recipes
```

## Authentication
All endpoints require authentication via Laravel Sanctum or session-based authentication.

## Endpoints

### 1. List Recipes
**GET** `/recipes`

Retrieve a paginated list of recipes belonging to the authenticated user.

#### Query Parameters
| Parameter   | Type    | Default      | Description                                              |
| ----------- | ------- | ------------ | -------------------------------------------------------- |
| `search`    | string  | -            | Search in recipe name and instructions                   |
| `category`  | string  | -            | Filter by category: `breakfast`, `supper`, `dinner`      |
| `sort`      | string  | `created_at` | Sort field: `name`, `created_at`, `calories`, `category` |
| `direction` | string  | `desc`       | Sort direction: `asc`, `desc`                            |
| `per_page`  | integer | 15           | Items per page (1-100)                                   |

#### Response
```json
{
  "recipes": {
    "data": [
      {
        "id": 1,
        "name": "Chocolate Pancakes",
        "category": "breakfast",
        "instructions": "Mix ingredients and cook on griddle...",
        "calories": 350,
        "servings": 2,
        "created_at": "2025-07-31T10:00:00Z",
        "updated_at": "2025-07-31T10:00:00Z",
        "ingredients": []
      }
    ],
    "links": {...},
    "meta": {...}
  },
  "filters": {
    "search": null,
    "category": null,
    "sort": "created_at",
    "direction": "desc"
  },
  "categories": [
    {"value": "breakfast", "label": "Breakfast"},
    {"value": "supper", "label": "Supper"},
    {"value": "dinner", "label": "Dinner"}
  ]
}
```

### 2. Get Recipe
**GET** `/recipes/{recipe}`

Retrieve a specific recipe with its ingredients.

#### Response
```json
{
  "recipe": {
    "id": 1,
    "name": "Chocolate Pancakes",
    "category": "breakfast",
    "instructions": "Mix ingredients and cook on griddle...",
    "calories": 350,
    "servings": 2,
    "created_at": "2025-07-31T10:00:00Z",
    "updated_at": "2025-07-31T10:00:00Z",
    "ingredients": [
      {
        "ingredient_id": 1,
        "ingredient_name": "Flour",
        "quantity": 200,
        "unit_id": 1,
        "unit_code": "g",
        "unit_name": "grams"
      }
    ]
  }
}
```

### 3. Create Recipe
**POST** `/recipes`

Create a new recipe.

#### Request Body
```json
{
  "name": "Chocolate Pancakes",
  "category": "breakfast",
  "instructions": "Mix ingredients and cook on griddle...",
  "calories": 350,
  "servings": 2,
  "ingredients": [
    {
      "ingredient_id": 1,
      "quantity": 200,
      "unit_id": 1
    }
  ]
}
```

#### Validation Rules
| Field                         | Rules                                |
| ----------------------------- | ------------------------------------ |
| `name`                        | required, string, max:255            |
| `category`                    | required, in:breakfast,supper,dinner |
| `instructions`                | required, string                     |
| `calories`                    | required, numeric, min:0             |
| `servings`                    | required, integer, min:1             |
| `ingredients`                 | sometimes, array                     |
| `ingredients.*.ingredient_id` | required, exists:ingredients,id      |
| `ingredients.*.quantity`      | required, numeric, min:0.01          |
| `ingredients.*.unit_id`       | required, exists:units,id            |

#### Response
**201 Created** - Redirects to recipes index with success message

### 4. Update Recipe
**PUT/PATCH** `/recipes/{recipe}`

Update an existing recipe. For PATCH requests, all fields are optional.

#### Request Body
Same structure as Create Recipe, but all fields optional for PATCH.

#### Response
**200 OK** - Redirects to recipe show page with success message

### 5. Delete Recipe
**DELETE** `/recipes/{recipe}`

Soft delete a recipe.

#### Response
**204 No Content** - Redirects to recipes index with success message

### 6. Create Form
**GET** `/recipes/create`

Display the recipe creation form.

#### Response
Inertia.js page with categories data.

### 7. Edit Form
**GET** `/recipes/{recipe}/edit`

Display the recipe edit form.

#### Response
Inertia.js page with recipe data and categories.

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

### 404 Not Found
```json
{
  "message": "No query results for model [App\\Models\\Recipe] {id}"
}
```

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "category": ["The selected category is invalid."]
  }
}
```

## Business Rules

1. **Ownership**: Users can only access their own recipes
2. **Categories**: Only three categories allowed: breakfast, supper, dinner
3. **Ingredients**: Optional, but if provided must reference existing ingredients and units
4. **Soft Deletes**: Deleted recipes are soft-deleted, not permanently removed
5. **Validation**: All numeric fields must be positive
6. **Duplicates**: Duplicate ingredients in the same recipe are automatically removed

## Rate Limiting

Standard Laravel rate limiting applies (60 requests per minute for authenticated users).

## Security

- All endpoints require authentication
- Authorization is enforced via RecipePolicy
- Mass assignment protection via fillable fields
- CSRF protection for state-changing operations
- XSS protection via proper escaping

## Performance Notes

- Queries are optimized with database indexes
- Pagination is used for list endpoints
- Eager loading is used for relationships when needed
- Query scopes provide reusable optimized filters
