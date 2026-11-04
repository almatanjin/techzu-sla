# Techzu SLA - Support Ticket & SLA Management System

A support ticket system with SLA tracking, built as two separate applications:

- `backend/` - Laravel 13 REST API (PHP 8.5, MySQL 9.7), served under `/api/v1`.
- `frontend/` - React 19 single-page app (Vite 8, TypeScript 7, Tailwind CSS 4).

The implementation plan lives in [PLAN.md](PLAN.md).

## Prerequisites

Docker Desktop (or Docker Engine with the Compose plugin) is the only requirement.
PHP, Composer and Node run inside containers.

## Local setup

Copy the example environment files (they hold local-only values):

```bash
cp backend/.env.example backend/.env
```

```bash
cp frontend/.env.example frontend/.env
```

Build and start the stack:

```bash
docker compose up -d --build
```

Install the backend dependencies, generate the app key, create the database tables, and seed the demo accounts:

```bash
docker compose exec app composer install
```

```bash
docker compose exec app php artisan key:generate
```

```bash
docker compose exec app php artisan migrate
```

```bash
docker compose exec app php artisan db:seed
```

The `queue` and `scheduler` containers restart until `composer install` has finished, then stay up.
The `frontend` container installs its npm packages on first start, which takes about a minute.

Open http://localhost:5173 to sign in.
The home page shows the backend status once signed in.

## Demo accounts

The database seeder creates standard accounts for local development.
All demo accounts share the password `Password123`.

| Email | Role | Description |
| ----- | ---- | ----------- |
| `admin@techzu-sla.test` | Administrator | Full administrative access to users and system configuration |
| `agent1@techzu-sla.test` | Support Agent | First support agent account |
| `agent2@techzu-sla.test` | Support Agent | Second support agent account |
| `agent3@techzu-sla.test` | Support Agent | Third support agent account |
| `customer1@techzu-sla.test` | Customer | First demo customer account |
| `customer2@techzu-sla.test` | Customer | Second demo customer account |
| `customer3@techzu-sla.test` | Customer | Third demo customer account |
| `customer4@techzu-sla.test` | Customer | Fourth demo customer account |
| `customer5@techzu-sla.test` | Customer | Fifth demo customer account |

Seeding is idempotent and safely skips execution in production environments.

## API Endpoints

All API endpoints reside under the `/api/v1` prefix.

### Authentication (`/api/v1/auth`)

| Method | Endpoint | Access | Description |
| ------ | -------- | ------ | ----------- |
| `POST` | `/api/v1/auth/register` | Public | Register a new customer account |
| `POST` | `/api/v1/auth/login` | Public | Sign in and receive a 7-day Bearer token |
| `POST` | `/api/v1/auth/logout` | Authenticated | Revoke current Bearer token |
| `GET` | `/api/v1/auth/me` | Authenticated | Retrieve current user profile |
| `POST` | `/api/v1/auth/forgot-password` | Public | Request password reset email via Mailpit |
| `POST` | `/api/v1/auth/reset-password` | Public | Reset password using email token |

### User Administration (`/api/v1/admin/users`)

| Method | Endpoint | Access | Description |
| ------ | -------- | ------ | ----------- |
| `GET` | `/api/v1/admin/users` | Admin only | List users with search, role, status and pagination |
| `POST` | `/api/v1/admin/users` | Admin only | Create user with specified role and password |
| `GET` | `/api/v1/admin/users/{id}` | Admin only | Retrieve user details |
| `PATCH` | `/api/v1/admin/users/{id}` | Admin only | Update user details, role, status or password |

## Environment variables

Key configuration settings in `backend/.env`:

- `FRONTEND_URL`: Allowed CORS origin and base URL for email links (default: `http://localhost:5173`).
- `SANCTUM_TOKEN_EXPIRATION`: Personal access token validity period in minutes (default: `10080` minutes / 7 days).

## Local services

| Service | URL / port | Purpose |
| ------- | ---------- | ------- |
| `frontend` | http://localhost:5173 | React app (Vite dev server with hot reload) |
| `nginx` + `app` | http://localhost:8000/api/v1 | Laravel API (php-fpm behind nginx) |
| `queue` | - | Queue worker (`queue:listen`, picks up code changes per job) |
| `scheduler` | - | Task scheduler (`schedule:work`) |
| `mysql` | `localhost:3306` | Development database, data kept in the `mysql-data` volume |
| `mysql-test` | internal only | Disposable database used by the backend tests |
| `mailpit` | http://localhost:8025 | Catches every email the app sends locally |

Health check: `curl http://localhost:8000/api/v1/health` returns `{"status":"ok","checks":{"database":"ok"}}`, or HTTP 503 when the database is down.

## Tests and lint

Backend tests (Pest, against the `mysql-test` database, so development data is never touched):

```bash
docker compose exec app php artisan test
```

Frontend tests (Vitest + React Testing Library, network mocked with MSW):

```bash
docker compose exec frontend npm test
```

Frontend lint and type check (Oxlint + `tsc`):

```bash
docker compose exec frontend npm run lint
```

## Everyday commands

Stop the stack (data is kept):

```bash
docker compose down
```

Stop the stack and delete the development database and installed npm packages:

```bash
docker compose down -v
```

Run any Artisan command:

```bash
docker compose exec app php artisan route:list
```

Follow the logs of a service:

```bash
docker compose logs -f queue
```

## Troubleshooting

- **Port 3306 is already in use:** set `FORWARD_DB_PORT=3307` in your shell (or a root `.env` file) before `docker compose up`.
- **Frontend does not hot reload:** some Docker setups miss file change events; start the stack with `VITE_USE_POLLING=true docker compose up -d`.
- **Files created by containers belong to another user (Linux):** start the stack with `HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose up -d --build` so the PHP image runs as your user.
- **"The app is not configured" page:** `frontend/.env` is missing `VITE_API_URL`; copy it from `frontend/.env.example` and run `docker compose restart frontend`.
