# User Revenue Report — Laravel API

A Laravel 12 API that returns a paginated revenue summary per user, based on their **completed** orders only.

---

## Requirements

| Tool | Minimum version |
|------|----------------|
| PHP | 8.2 |
| Composer | 2.x |

> **Database:** SQLite is used by default (no extra server needed).  
> Switch to MySQL/PostgreSQL by editing the `DB_*` variables in `.env`.

---

## Setup

### 1. Install dependencies
```bash
composer install
```

### 2. Copy environment file
```bash
cp .env.example .env
```

### 3. Generate application key
```bash
php artisan key:generate
```

### 4. Run database migrations
```bash
php artisan migrate
```

### 5. (Optional) Seed sample data
Creates 10 users, each with 3-8 completed orders and some pending/cancelled ones.
```bash
php artisan db:seed
```

### 6. Start the development server
```bash
php artisan serve
```

The app is now available at **http://127.0.0.1:8000**

---

## API Endpoint

### `GET /api/reports/user-revenue`

Returns a paginated revenue summary per user. Only `completed` orders are counted.

#### Query parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer (≥ 1) | No | Page number (default: 1) |
| `per_page` | integer (1–100) | No | Results per page (default: 15) |
| `start_date` | date (Y-m-d) | No | Include orders completed on or after this date |
| `end_date` | date (Y-m-d) | No | Include orders completed on or before this date |

#### Example requests

```
GET http://127.0.0.1:8000/api/reports/user-revenue
GET http://127.0.0.1:8000/api/reports/user-revenue?page=2&per_page=5
GET http://127.0.0.1:8000/api/reports/user-revenue?start_date=2024-01-01&end_date=2024-12-31
```

#### Example response

```json
{
    "current_page": 1,
    "data": [
        {
            "user_id": 1,
            "email": "test@example.com",
            "orders_count": 5,
            "total_revenue": 1249.50
        },
        {
            "user_id": 2,
            "email": "jane@example.com",
            "orders_count": 3,
            "total_revenue": 374.00
        }
    ],
    "per_page": 15,
    "total": 10,
    "last_page": 1,
    "next_page_url": null,
    "prev_page_url": null
}
```

#### Validation errors (HTTP 422)

Returned when query parameters are invalid:

```json
{
    "message": "The start date field must be a date.",
    "errors": {
        "start_date": ["The start date field must be a date."]
    }
}
```

---

## Running Tests

```bash
php artisan test
```

Expected output:
```
PASS  Tests\Unit\ExampleTest
PASS  Tests\Feature\UserRevenueReportTest
  ✓ it returns completed order totals per user
  ✓ it filters results by completed at date range
  ✓ it paginates results
  ✓ it validates the date range

Tests: 5 passed (23 assertions)
```

---

## Switching to MySQL

1. Create a database in MySQL.
2. Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

3. Re-run migrations:

```bash
php artisan migrate
```

---

## Project Structure

```
app/
  Http/
    Controllers/Api/
      UserRevenueReportController.php   ← report logic
    Requests/
      UserRevenueReportRequest.php      ← query param validation
  Models/
    User.php                            ← hasMany orders
    Order.php                           ← belongsTo user, scopes
database/
  migrations/
    ..._create_orders_table.php         ← orders schema
  factories/
    OrderFactory.php                    ← test/seed data
  seeders/
    DatabaseSeeder.php                  ← sample data
routes/
  api.php                               ← GET /api/reports/user-revenue
tests/
  Feature/
    UserRevenueReportTest.php           ← feature tests
```
"# BackendTask" 
"# BackendTask" 
