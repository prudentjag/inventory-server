# Inventory & POS System API Documentation

**Base URL**: `/api`  
**Authentication**: Bearer Token (Sanctum)

---

## Roles

| Role        | Description                                       |
| ----------- | ------------------------------------------------- |
| `admin`     | Full system access                                |
| `stockist`  | Central stock management, approve/reject requests |
| `manager`   | Unit management, inventory, sales                 |
| `unit_head` | Similar to manager                                |
| `staff`     | Sales only                                        |

---

## 1. Authentication

### Login

**POST** `/login`

```json
{ "email": "user@example.com", "password": "password" }
```

**Response**: `{ "access_token": "...", "user": {...} }`

### Register User (Admin Only)

**POST** `/register`

```json
{
    "name": "New Staff",
    "email": "staff@example.com",
    "password": "password",
    "role": "staff",
    "is_active": true
}
```

### Get Current User

**GET** `/user`

### Logout

**POST** `/logout`

---

## 2. Brands & Categories

### Brands

| Method | Endpoint       | Body                                                 |
| ------ | -------------- | ---------------------------------------------------- |
| GET    | `/brands`      | -                                                    |
| POST   | `/brands`      | `{ name, category_id, image (file), items_per_set }` |
| PUT    | `/brands/{id}` | Same as POST                                         |
| DELETE | `/brands/{id}` | -                                                    |

**Response includes**: `image_url` (full URL to brand image)

### Categories

| Method | Endpoint           | Body                    |
| ------ | ------------------ | ----------------------- |
| GET    | `/categories`      | -                       |
| POST   | `/categories`      | `{ name, description }` |
| PUT    | `/categories/{id}` | Same as POST            |
| DELETE | `/categories/{id}` | -                       |

---

## 3. Products

| Method | Endpoint         | Body                                                                                                    |
| ------ | ---------------- | ------------------------------------------------------------------------------------------------------- |
| GET    | `/products`      | -                                                                                                       |
| POST   | `/products`      | `{ name, brand_id, category_id, sku, unit_of_measurement, size, cost_price, selling_price, trackable }` |
| PUT    | `/products/{id}` | Same as POST                                                                                            |
| DELETE | `/products/{id}` | -                                                                                                       |

**Size options**: `small`, `medium`, `big`

---

## 4. Units (Admin Only)

| Method | Endpoint            | Description                       |
| ------ | ------------------- | --------------------------------- |
| GET    | `/units`            | List all units/stores             |
| POST   | `/units`            | Create unit                       |
| PUT    | `/units/{id}`       | Update unit                       |
| DELETE | `/units/{id}`       | Delete unit                       |
| POST   | `/units/{id}/users` | Assign user to unit `{ user_id }` |
| DELETE | `/units/{id}/users` | Remove user from unit             |

---

## 5. Central Stock (Stockist/Admin)

### Stock Management

| Method | Endpoint      | Body                                                                       |
| ------ | ------------- | -------------------------------------------------------------------------- |
| GET    | `/stock`      | List central warehouse stock                                               |
| POST   | `/stock`      | Add to stock `{ product_id, quantity, low_stock_threshold, batch_number }` |
| PUT    | `/stock/{id}` | Update stock                                                               |
| DELETE | `/stock/{id}` | Delete stock entry                                                         |

### Stock Requests (Approval Workflow)

| Method | Endpoint                       | Access         | Body                                       |
| ------ | ------------------------------ | -------------- | ------------------------------------------ |
| GET    | `/stock-requests`              | All            | Query: `?status=pending`                   |
| POST   | `/stock-requests`              | All            | `{ unit_id, product_id, quantity, notes }` |
| GET    | `/stock-requests/{id}`         | All            | -                                          |
| POST   | `/stock-requests/{id}/approve` | Stockist/Admin | -                                          |
| POST   | `/stock-requests/{id}/reject`  | Stockist/Admin | `{ notes }`                                |

**Approval Flow**:

1. Unit manager creates request
2. Stockist sees pending requests
3. On approve: Central stock ↓, Unit inventory ↑

---

## 6. Unit Inventory

| Method | Endpoint                  | Body                                                               |
| ------ | ------------------------- | ------------------------------------------------------------------ |
| GET    | `/inventory?unit_id={id}` | List unit inventory                                                |
| POST   | `/inventory`              | Add stock `{ unit_id, product_id, quantity, low_stock_threshold }` |
| POST   | `/inventory/transfer`     | Transfer `{ from_unit_id, to_unit_id, product_id, quantity }`      |

---

## 7. Sales (POS)

### Process Sale

**POST** `/sales`

```json
{
    "unit_id": 1,
    "payment_method": "monnify",
    "redirect_url": "https://yourfrontend.com/callback",
    "items": [{ "product_id": 5, "quantity": 2, "unit_price": 15.0 }]
}
```

_Note: If `payment_method` is `monnify`, the response will include `payment_data` with a `checkoutUrl`. This will display a **Virtual Account Number** (Bank Transfer) for the customer to pay._

### Webhooks

**POST** `/webhooks/monnify` (External only)
Used by Monnify to notify the system of successful payments.

### Sales History

| Method | Endpoint                                 | Access         | Description                      |
| ------ | ---------------------------------------- | -------------- | -------------------------------- |
| GET    | `/sales`                                 | Admin/Stockist | List ALL sales across all units  |
| GET    | `/sales/history/{unit_id}`               | Assigned Staff | List sales for a specific unit   |
| GET    | `/sales/{invoice_number}/verify-payment` | All            | Manually verify Monnify payment  |
| GET    | `/my-sales`                              | All            | List YOUR personal sales history |

**Available Filters** (Query Params):

-   `start_date`: e.g., `2024-01-01`
-   `end_date`: e.g., `2024-01-31`
-   `payment_method`: `cash`, `pos`, `transfer`
-   `payment_status`: `paid`, `pending`
-   `unit_id`: Filter by unit (works on `/sales` and `/my-sales`)
-   `user_id`: Filter by seller (Admin/Stockist only on `/sales`)

---

## 8. Dashboard

**GET** `/dashboard/stats`

### Response by Role

**Admin**:

```json
{
    "role": "admin",
    "total_users": 10,
    "total_products": 50,
    "total_sales_count": 120,
    "total_revenue": 50000,
    "low_stock_alerts": 5,
    "bookings_today": 3,
    "bookings_revenue_today": 45000
}
```

**Stockist**:

```json
{
    "role": "stockist",
    "total_central_stock": 1000,
    "total_products_in_stock": 25,
    "low_stock_alerts": 3,
    "pending_requests": 5
}
```

**Manager/Unit Head**:

```json
{
    "role": "manager",
    "unit_sales_count": 45,
    "unit_revenue": 12500,
    "low_stock_alerts": 2,
    "total_products_in_units": 20,
    "unit_bookings_today": 2
}
```

**Staff**:

```json
{ "role": "staff", "my_sales_count": 15, "my_revenue": 4500, "items_sold": 30 }
```

---

## 9. Audit Logs (Admin/Stockist)

| Method | Endpoint                  | Description                         | Query Params           |
| ------ | ------------------------- | ----------------------------------- | ---------------------- |
| GET    | `/audit-logs`             | List all activity logs              | `action`, `product_id` |
| GET    | `/audit-logs/{type}/{id}` | Get history for a specific resource | -                      |

**Action types**: `stock_added`, `stock_updated`, `product_created`, `product_updated`, `stock_request_approved`, `inventory_updated`, `inventory_transfer`.

**Resource types**: `stock`, `product`, `stockRequest`, `inventory`.

---

## 10. Facilities (Bookable Resources)

Manage bookable spaces like football pitches, event halls, courts, and conference rooms.

### Facility Types

| Type              | Description       |
| ----------------- | ----------------- |
| `pitch`           | Football pitch    |
| `event_hall`      | Event venue       |
| `court`           | Tennis/basketball |
| `conference_room` | Meeting room      |

### Facilities CRUD

| Method | Endpoint                        | Access    | Description           |
| ------ | ------------------------------- | --------- | --------------------- |
| GET    | `/facilities`                   | All       | List (filter: `type`) |
| GET    | `/facilities/types`             | All       | Get type options      |
| GET    | `/facilities/{id}`              | All       | Get details           |
| GET    | `/facilities/{id}/availability` | All       | Check slots for date  |
| POST   | `/facilities`                   | Admin/Mgr | Create facility       |
| PUT    | `/facilities/{id}`              | Admin/Mgr | Update facility       |
| DELETE | `/facilities/{id}`              | Admin/Mgr | Delete facility       |

**Create/Update Body**:

```json
{
    "name": "Grand Hall",
    "type": "event_hall",
    "description": "500-capacity event venue",
    "hourly_rate": 25000,
    "ticket_price": 500,
    "capacity": 500,
    "unit_id": 1,
    "is_active": true
}
```

> **Note**: `hourly_rate` is used for facility bookings (rent by time), while `ticket_price` is the default price for individual tickets (drop-in players).

**Availability Query**: `GET /facilities/1/availability?date=2026-01-05`

---

## 11. Facility Bookings

| Method | Endpoint                          | Description                       |
| ------ | --------------------------------- | --------------------------------- |
| GET    | `/facility-bookings`              | List (filter: date, status, type) |
| POST   | `/facility-bookings`              | Create booking + Sale record      |
| GET    | `/facility-bookings/{id}`         | Get details                       |
| PUT    | `/facility-bookings/{id}`         | Update booking                    |
| DELETE | `/facility-bookings/{id}`         | Delete booking                    |
| POST   | `/facility-bookings/{id}/confirm` | Confirm & mark as paid            |
| POST   | `/facility-bookings/{id}/cancel`  | Cancel booking                    |

**Create Booking**:

```json
{
    "facility_id": 1,
    "customer_name": "John Doe",
    "customer_phone": "08012345678",
    "customer_email": "john@example.com",
    "booking_date": "2026-01-05",
    "start_time": "14:00",
    "end_time": "18:00",
    "payment_method": "transfer",
    "notes": "Birthday party"
}
```

**Response**: Includes auto-generated `booking_reference` (e.g., `FB-20260105-001`) and linked `sale` record.

**Query Filters**:

-   `date`: Single date filter
-   `start_date` / `end_date`: Date range
-   `facility_id`: Specific facility
-   `facility_type`: Filter by type (e.g., `event_hall`)
-   `status`: `pending`, `confirmed`, `cancelled`

**Booking Status Flow**:

1. Created → `pending`
2. `/confirm` → `confirmed` (Sale marked as `paid`)
3. `/cancel` → `cancelled`

---

## 12. Facility Tickets (Drop-in / Individual Payments)

For individual players who come to use facilities and pay per session (e.g., drop-in football players).

| Method | Endpoint                        | Description                      |
| ------ | ------------------------------- | -------------------------------- |
| GET    | `/facility-tickets`             | List tickets                     |
| POST   | `/facility-tickets`             | Sell a ticket + create Sale      |
| GET    | `/facility-tickets/{id}`        | Get ticket details               |
| PUT    | `/facility-tickets/{id}`        | Update ticket info               |
| POST   | `/facility-tickets/{id}/refund` | Refund a ticket                  |
| GET    | `/facility-tickets-stats`       | Get ticket statistics for a date |

**Create Ticket**:

```json
{
    "facility_id": 1,
    "customer_name": "John Doe",
    "customer_phone": "08012345678",
    "ticket_date": "2026-01-03",
    "check_in_time": "14:00",
    "amount": 500,
    "payment_method": "cash",
    "notes": "Regular player"
}
```

**Response**: Includes auto-generated `ticket_reference` (e.g., `FT-20260103-0001`) and linked `sale` record.

**Query Filters**:

-   `date`: Single date filter
-   `start_date` / `end_date`: Date range
-   `facility_id`: Specific facility
-   `status`: `paid`, `refunded`

**Stats Query**: `GET /facility-tickets-stats?date=2026-01-03&facility_id=1`

**Stats Response**:

```json
{
    "total_tickets": 25,
    "total_revenue": 12500,
    "payment_breakdown": [
        { "payment_method": "cash", "count": 15, "amount": 7500 },
        { "payment_method": "transfer", "count": 10, "amount": 5000 }
    ]
}
```

### Booking vs Ticketing

| Feature     | Booking                           | Ticketing                         |
| ----------- | --------------------------------- | --------------------------------- |
| Use Case    | Rent entire facility for a period | Individual drop-in payments       |
| Pricing     | `hourly_rate` × duration          | Fixed `ticket_price` or custom    |
| Time Slots  | Start/End time, prevents overlaps | No time restrictions              |
| Capacity    | One booking per time slot         | Multiple tickets (manual control) |
| Status Flow | pending → confirmed/cancelled     | paid → refunded                   |

## Response Format

```json
{
  "status": "success",
  "message": "...",
  "data": { ... }
}
```

## Error Codes

-   **401**: Unauthenticated
-   **403**: Unauthorized
-   **422**: Validation Error
-   **400**: Bad Request
