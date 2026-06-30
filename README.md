# 🌿 LandcareLink Prototype

A full-stack prototype for an environmental community platform that manages
**community groups** data — inspired by LandcareLink. It covers the full slice:
a native-PHP REST API backed by SQLite (PDO + prepared statements), a vanilla
HTML/CSS/JS frontend with a Leaflet map, and a PHPUnit test suite.

## Features

- REST API with full CRUD (`GET`, `POST`, `PUT`, `DELETE`) for the `Group` entity
- SQLite via PDO using **prepared statements only**
- Input validation with structured JSON error responses
- Modular backend: `config/`, `models/`, `controllers/`, `routes/`, `public/`
- Frontend table to view / add / edit / delete groups via the Fetch API
- Interactive Leaflet map plotting groups by lat/long, **color-coded by type**
- PHPUnit tests — at least one per CRUD endpoint (runs on in-memory SQLite, no DB needed)

## Entity: `Group`

| Field           | Type                                                                       |
| --------------- | -------------------------------------------------------------------------- |
| `id`            | int (auto-increment)                                                       |
| `name`          | string                                                                     |
| `type`          | enum: `environmental_group`, `catchment_collective`, `catchment_group`     |
| `region`        | string                                                                     |
| `contact_email` | string (validated email)                                                   |
| `latitude`      | decimal (-90..90)                                                          |
| `longitude`     | decimal (-180..180)                                                        |
| `created_at`    | datetime                                                                   |

## Project structure

```
landcarelink-prototype/
├── backend/
│   ├── config/
│   │   └── db.php                 # PDO connection (env-configurable)
│   ├── controllers/
│   │   └── GroupController.php     # validation + request handling
│   ├── models/
│   │   └── Group.php               # prepared-statement DB access
│   ├── routes/
│   │   └── api.php                 # method/path -> controller routing
│   └── public/
│       └── index.php               # front controller / API entry point
├── frontend/
│   ├── index.html
│   ├── css/style.css
│   └── js/app.js                   # Fetch + Leaflet
├── tests/
│   └── GroupApiTest.php            # PHPUnit, one+ test per CRUD endpoint
├── schema.sql                      # SQLite schema + 10 seed rows
├── composer.json
├── phpunit.xml
└── README.md
```

## Prerequisites

- PHP **8.1+** with the `pdo_sqlite` extension
- Composer (for installing PHPUnit)

## Setup

### 1. Create and seed the database

```bash
sqlite3 database/database.sqlite < schema.sql
```

This creates the `groups` table inside `database/database.sqlite` and inserts
10 sample groups (a mix of **Waikato** and **Bay of Plenty** regions).

## Running locally

### Backend API

```bash
php -S localhost:8000 -t backend/public
```

The API is now available at `http://localhost:8000/api/groups`.

| Method   | Path                | Description          |
| -------- | ------------------- | -------------------- |
| `GET`    | `/api/groups`       | List all groups      |
| `GET`    | `/api/groups/{id}`  | Get one group        |
| `POST`   | `/api/groups`       | Create a group       |
| `PUT`    | `/api/groups/{id}`  | Update a group       |
| `DELETE` | `/api/groups/{id}`  | Delete a group       |

Example:

```bash
# List
curl http://localhost:8000/api/groups

# Create
curl -X POST http://localhost:8000/api/groups \
  -H "Content-Type: application/json" \
  -d '{"name":"New Group","type":"environmental_group","region":"Waikato","contact_email":"hi@example.org","latitude":-37.78,"longitude":175.28}'
```

Validation failures return `422` with structured detail:

```json
{ "error": "Validation failed", "errors": { "contact_email": "A valid contact email is required." } }
```

### Frontend

Serve the static frontend on a separate port (the API allows CORS):

```bash
php -S localhost:8080 -t frontend
```

Open <http://localhost:8080>. If your API runs somewhere other than
`http://localhost:8000`, edit `API_BASE` at the top of `frontend/js/app.js`.

The map is color-coded by type:
🟢 environmental group · 🔵 catchment collective · 🟠 catchment group.

## Deploying to Railway

The backend is configured for Railway's **Nixpacks** auto-detection — no
Dockerfile needed.

- Set the service **Root Directory** to `backend/`
- Nixpacks detects PHP + Composer and runs `composer install --no-dev --optimize-autoloader`
- The `backend/Procfile` tells Railway how to start the server:
  ```
  web: php -S 0.0.0.0:$PORT -t public
  ```
- Set the `ANTHROPIC_API_KEY` environment variable in the Railway service dashboard

The SQLite database file lives at `database/database.sqlite` relative to the
project root. Seed it once with `sqlite3 database/database.sqlite < schema.sql`
before first deploy, or commit the pre-seeded file to the repo.

## Running the tests

Install dev dependencies, then run PHPUnit:

```bash
composer install
composer test        # or: ./vendor/bin/phpunit
```

The suite spins up an **in-memory SQLite** database, so it requires no running
MySQL instance. It includes at least one test per CRUD endpoint plus validation
and not-found cases.

## Notes & next steps

This is a prototype. For production you'd add: authentication/authorization,
rate limiting, pagination, request logging, environment-based error reporting,
database migrations, and CI.
