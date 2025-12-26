# Inventory & POS System API Documentation

**Base URL**: `https://op6iimxret.sharedwithexpose.com/api`
**Authentication**: Bearer Token (Sanctum)

---

## 1. Authentication

### Login

**POST** `/login`

-   **Body**:
    ```json
    {
        "email": "user@example.com",
        "password": "password"
    }
    ```
-   **Response (200)**:
    ```json
    {
      "access_token": "1|...",
      "token_type": "Bearer",
      "user": { "id": 1, "name": "...", "role": "admin", ... }
    }
    ```

### Register User (Admin Only)

**POST** `/register`

-   **Headers**: `Authorization: Bearer <token>`
-   **Body**:
    ```json
    {
        "name": "New Staff",
        "email": "staff@example.com",
        "password": "password",
        "role": "staff", // admin, staff, manager, unit_head
        "is_active": true
    }
    ```
-   **Response (200)**: Success message and user data.

### Get Current User

**GET** `/user`

-   **Response (200)**: Returns user profile with assigned units.
-   **Headers**: `Authorization: Bearer <token>`

### Logout

**POST** `/logout`

-   **Headers**: `Authorization: Bearer <token>`

---

## 2. Metadata (Brands & Categories)

**Permissions**:

-   **View**: All Roles
-   **Create/Update/Delete**: `admin`, `manager` only.

### Brands

-   **GET** `/brands` - List all brands
-   **POST** `/brands` - Create brand `{ "name": "Heineken", "category_id": 1 }`
-   **PUT** `/brands/{id}` - Update brand
-   **DELETE** `/brands/{id}` - Delete brand

### Categories

-   **GET** `/categories` - List all categories
-   **POST** `/categories` - Create category `{ "name": "Beer", "description": "Alcoholic" }`
-   **PUT** `/categories/{id}` - Update category
-   **DELETE** `/categories/{id}` - Delete category

---

## 3. Products

**Permissions**:

-   **View**: All Roles
-   **Create/Update/Delete**: `admin`, `manager` only.

### List Products

**GET** `/products`

### Create Product

**POST** `/products`

-   **Body**:
    ```json
    {
        "name": "Heineken 330ml",
        "brand_id": 1,
        "category_id": 1,
        "sku": "HEIN-330",
        "unit_of_measurement": "bottle",
        "cost_price": 10.0,
        "selling_price": 15.0,
        "trackable": true
    }
    ```

### Update Product

**PUT** `/products/{id}`

### Delete Product

**DELETE** `/products/{id}`

---

## 3. Inventory

### List Inventory

**GET** `/inventory?unit_id={id}`

### Add/Update Stock (Admin/Manager Only)

**POST** `/inventory`

-   **Body**:
    ```json
    {
        "unit_id": 1,
        "product_id": 5,
        "quantity": 50, // Amount to ADD (not set)
        "low_stock_threshold": 10
    }
    ```

### Transfer Stock

**POST** `/inventory/transfer`

-   **Body**:
    ```json
    {
        "from_unit_id": 1,
        "to_unit_id": 2,
        "product_id": 5,
        "quantity": 10
    }
    ```

---

## 4. Sales (POS)

### Process Sale

**POST** `/sales`

-   **Body**:
    ```json
    {
        "unit_id": 1,
        "payment_method": "cash",
        "items": [{ "product_id": 5, "quantity": 2, "unit_price": 15.0 }]
    }
    ```
-   **Note**: Stock is automatically deducted. Returns 400 if insufficient stock.

### Sales History

**GET** `/sales/history/{unit_id}`

---

## 5. Dashboard

### Get Dashboard Stats

**GET** `/dashboard/stats`

-   **Description**: Returns statistics based on user role (Admin vs Staff).
-   **Response (Admin)**:
    ```json
    {
        "role": "admin",
        "total_users": 10,
        "total_products": 50,
        "total_sales_count": 120,
        "total_revenue": 50000.0,
        "low_stock_alerts": 5
    }
    ```
-   **Response (Manager / Unit Head)**:
    ```json
    {
        "role": "manager",
        "unit_sales_count": 45,
        "unit_revenue": 12500.0,
        "low_stock_alerts": 2,
        "total_products_in_units": 20
    }
    ```
-   **Response (Staff)**:
    ```json
    {
        "role": "staff",
        "my_sales_count": 15,
        "my_revenue": 4500.0,
        "items_sold": 30
    }
    ```

---

## Response Format

Standard success response (where applicable):

```json
{
  "status": "success",
  "message": "...",
  "data": { ... }
}
```

## Error Codes

-   **401**: Unauthenticated
-   **403**: Unauthorized (Role restrictions)
-   **422**: Validation Error
