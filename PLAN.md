# Support Ticket & SLA Management System - Implementation Plan

This plan covers how to deliver the assessment in `Assessment for PHP team.md`.
It describes the stack, architecture, data model, the design of each business rule, the delivery phases, testing, local Docker setup, and free deployment options.

---

## 1. Stack and Versions

Versions below were checked against npm, Packagist, php.net, nodejs.org and Docker Hub on 2026-11-05.

| Layer | Choice | Version |
| ----- | ------ | ------- |
| Language (backend) | PHP | 8.5 (8.5.12 current patch) |
| Backend framework | Laravel | 13.x (13.40 current) |
| API auth | Laravel Sanctum | 4.x |
| Backend tests | Pest | 5.x |
| API docs (bonus) | dedoc/scramble (OpenAPI generated from code) | latest |
| Database | MySQL | 9.7 LTS (`mysql:9.7`, same digest as `mysql:lts`) |
| Cache / queue / locks | Laravel database drivers (MySQL) | built in |
| Local mail catcher | Mailpit | latest |
| Runtime (frontend tooling) | Node.js | 26 LTS ("Lithium", LTS since 2026-10-28) |
| Frontend library | React + React DOM | 19.3.x |
| Build tool | Vite | 8.x |
| Language (frontend) | TypeScript | 7.x |
| Linting (frontend) | Oxlint | latest |
| Routing | React Router | 8.x |
| Server state | TanStack Query | 5.x |
| Styling | Tailwind CSS | 4.x |
| Forms / validation | React Hook Form + Zod | latest |
| HTTP client | Axios | 1.x |
| Frontend tests | Vitest + React Testing Library (+ Playwright as bonus) | latest |

PHP 8.6 is at RC4 and is due on 2026-11-19.
Pin PHP 8.5 now, since Laravel 13 and most extensions are verified against it.

---

## 2. Repository Layout

One GitHub repository holds two independent applications.
They share nothing except the HTTP API contract and the root `docker-compose.yml`.

```
techzu-sla/
├── backend/                  # Laravel 13 API (no Blade UI)
├── frontend/                 # React 19 SPA (Vite)
├── docker/                   # Dockerfiles, nginx conf, php.ini, supervisord
│   ├── php/Dockerfile
│   ├── nginx/default.conf
│   ├── supervisord.conf     # production: php-fpm + nginx + queue + scheduler
│   └── node/Dockerfile
├── docker-compose.yml        # local development
├── render.yaml              # Render Blueprint for the backend deployment
├── PLAN.md
└── README.md
```

Each app has its own `.env.example`, its own dependencies, its own tests and its own deploy target.

---

## 3. Backend Architecture

### 3.1 Request flow

```
Route (routes/api.php, /api/v1)
  → Middleware (auth:sanctum, throttle, role)
  → FormRequest (validation + authorize via Policy)
  → Controller (thin: authorize, map request to DTO, call service, return Resource)
  → Service (business rules, transactions, events)
  → Model / Eloquent (relationships, scopes, casts)
  → API Resource (response shape, hides fields per role)
```

Rules that keep the layering clean:

- Controllers never contain business logic or queries beyond route model binding.
- Services never receive a `Request` object; they receive typed DTOs or primitives, so they are reusable from jobs, commands and the CSV importer.
- Services own `DB::transaction()` boundaries.
- Events are dispatched after commit (`ShouldDispatchAfterCommit`), and queued listeners and notifications run after commit (`after_commit => true` on the queue connection).
- Domain exceptions (for example `TicketAlreadyAssignedException`, `InvalidStatusTransitionException`) are mapped to HTTP codes (409, 422) in `bootstrap/app.php`, added in the phase that introduces each exception; error bodies keep Laravel's default JSON format (`message`, `errors`).
- N+1 queries are prevented by explicit eager loading in the query builder and services, and guarded by query-count assertions in tests (see section 8).

### 3.2 Backend folder structure

```
backend/app/
├── Enums/                  # UserRole, TicketStatus, TicketPriority, SlaStatus,
│                           # MessageType, ActivityType, ImportStatus
├── Data/                   # Readonly DTOs: CreateTicketData, UpdateTicketData,
│                           # TicketFilterData, ReplyData, ImportRowData
├── Http/
│   ├── Controllers/Api/V1/ # AuthController, TicketController, TicketAssignmentController,
│   │                       # TicketMessageController, AttachmentController,
│   │                       # TicketActivityController, DashboardController,
│   │                       # TicketImportController, Admin/*
│   ├── Requests/           # One FormRequest per write action
│   ├── Resources/          # TicketResource, TicketListResource, MessageResource, ...
│   └── Middleware/
├── Models/                 # User, Ticket, Category, TicketMessage, Attachment,
│                           # TicketActivity, SlaPolicy, BusinessHour, Holiday,
│                           # TicketSequence, TicketImport, TicketImportError
├── Policies/               # TicketPolicy, TicketMessagePolicy, AttachmentPolicy, ...
├── Services/
│   ├── Tickets/            # TicketService, TicketNumberGenerator, TicketStatusService
│   ├── Assignment/         # AssignmentService, AutoAssignmentStrategy (interface),
│   │                       # LeastLoadedAgentStrategy
│   ├── Sla/                # BusinessHoursCalculator, SlaPolicyRepository,
│   │                       # SlaCalculator, SlaEscalationService
│   ├── Conversation/       # ConversationService, AttachmentService
│   ├── Search/             # TicketQueryBuilder (filters, sort, pagination)
│   ├── Dashboard/          # DashboardService
│   ├── Import/             # TicketImportService, CsvRowValidator
│   └── Activity/           # ActivityLogger
├── Events/                 # TicketCreated, TicketAssigned, TicketReplied,
│                           # TicketStatusChanged, TicketSlaApproaching, TicketSlaBreached
├── Listeners/              # AutoAssignTicket, SendTicketNotifications, ...
├── Notifications/          # One per event, ShouldQueue, mail + database channels
├── Jobs/                   # CheckSlaJob, ImportTicketsJob, ProcessImportChunkJob
├── Console/Commands/       # tickets:check-sla
└── Exceptions/
```

### 3.3 Authentication

- Sanctum personal access tokens (Bearer) with an expiry (for example 7 days), issued on login and revoked on logout.
- Reason: the frontend (Vercel domain) and the API (another domain) are cross-site in production, and Vercel's `*.vercel.app` is on the public suffix list, so cookie-based Sanctum SPA auth would need a shared parent domain.
- Bearer tokens also make the API usable by third-party clients, which counts toward the "API endpoints" bonus.
- Login and register endpoints are rate limited (`throttle:auth`, 5 per minute per IP + email).
- Public registration creates Customer accounts only; Agents and Admins are created by an Admin.

### 3.4 Roles and authorization

Three roles stored as a backed enum column `users.role` (`admin`, `agent`, `customer`).
Authorization lives in Policies and is enforced on every endpoint, plus query scoping so lists never leak rows.

| Action | Customer | Agent | Admin |
| ------ | -------- | ----- | ----- |
| Create ticket | Own | On behalf of customer | Yes |
| View ticket | Own only | Assigned + unassigned in their categories | All |
| Edit subject/description | Own, while Open | Yes | Yes |
| Change priority / category | No | Yes | Yes |
| Change status | Close own / reopen own resolved | All allowed transitions | All |
| Claim unassigned ticket | No | Yes | Yes |
| Assign / reassign to any agent | No | No | Yes |
| Public reply | Own tickets | Accessible tickets | Yes |
| Internal note (write/read) | Never | Yes | Yes |
| Download attachment | Own tickets, public messages only | Accessible tickets | All |
| Search by customer / agent filters | No | Yes | Yes |
| Dashboard | Own ticket summary | Own + team queue | Global |
| CSV import | No | No | Yes |
| Manage users, categories, SLA policies, business hours | No | No | Yes |

Implementation details:

- `Ticket::scopeVisibleTo(User $user)` is applied in every list and search query.
- `TicketPolicy` handles single-record checks; `Gate::before` is avoided so Admin rules stay explicit and testable.
- `TicketMessage::scopeVisibleTo(User $user)` excludes internal notes for customers at the query level, and `MessageResource` never includes internal-only fields for customers (defence in depth).

A permissions package (spatie/laravel-permission) is unnecessary for three fixed roles.
The enum + Policy approach is easy to explain and to modify during the interview.

---

## 4. Data Model

All timestamps are stored in UTC.
Business-hours calculations convert to the configured business timezone.

### 4.1 Tables

**users**
- `id`, `name`, `email` (unique), `password`, `role` (enum, indexed)
- `is_available` (bool, agents only), `max_open_tickets` (nullable int)
- `last_assigned_at` (nullable, used as the round-robin tie breaker)
- timestamps

**categories**
- `id`, `name` (unique), `is_active`, timestamps

**agent_category** (pivot: which agents handle which categories)
- `user_id`, `category_id`, primary key (`user_id`, `category_id`), index (`category_id`, `user_id`)

**tickets**
- `id` (bigint PK)
- `ticket_number` (string, unique) - for example `TKT-2026-000001`
- `subject`, `description` (text)
- `priority` (enum), `status` (enum), `category_id` (FK)
- `customer_id` (FK users), `assigned_agent_id` (nullable FK users), `created_by_id` (FK users)
- `sla_policy_minutes` (snapshot of the policy used, for auditability)
- `sla_started_at`, `sla_warning_at`, `sla_due_at`
- `sla_warning_notified_at`, `sla_breached_at` (nullable)
- `is_escalated` (bool), `escalated_at` (nullable)
- `first_responded_at`, `resolved_at`, `closed_at` (nullable)
- `external_reference` (nullable, unique) - used for CSV duplicate detection
- `lock_version` (unsigned int) - optimistic locking on edits
- timestamps, soft deletes

**ticket_sequences**
- `year` (PK), `last_number` (unsigned bigint)

**ticket_messages**
- `id`, `ticket_id` (FK), `author_id` (FK), `type` (enum: `reply`, `internal_note`), `body` (text), timestamps

**attachments**
- `id`, `ticket_id` (FK), `ticket_message_id` (nullable FK), `uploaded_by_id` (FK)
- `disk`, `path` (random UUID path), `original_name`, `mime_type`, `size_bytes`, `checksum_sha256`
- `created_at`

**ticket_activities** (append-only, no `updated_at`)
- `id`, `ticket_id` (FK), `actor_id` (nullable FK, null means System)
- `type` (enum: created, assigned, unassigned, claimed, status_changed, priority_changed, category_changed, replied, internal_note_added, attachment_added, sla_warning, sla_breached, escalated, imported)
- `old_values` (json, nullable), `new_values` (json, nullable), `created_at`

**sla_policies**
- `id`, `priority` (enum, unique), `resolution_minutes`, `warning_threshold_percent` (default 75), timestamps

**business_hours**
- `id`, `day_of_week` (0 = Sunday ... 6 = Saturday, unique), `is_working_day`, `opens_at` (time), `closes_at` (time)

**holidays** (optional but cheap)
- `id`, `date` (unique), `name`

**notifications** (Laravel default table for the database channel)

**ticket_imports**
- `id`, `user_id`, `original_filename`, `path`, `status` (enum: pending, processing, completed, failed)
- `total_rows`, `processed_rows`, `succeeded_rows`, `failed_rows`, `duplicate_rows`
- `batch_id` (nullable, Laravel job batch), `started_at`, `finished_at`, timestamps

**ticket_import_errors**
- `id`, `ticket_import_id` (FK), `row_number`, `raw_row` (json), `errors` (json)

Plus Laravel's `jobs`, `job_batches`, `failed_jobs`, `cache`, `personal_access_tokens`.

### 4.2 Indexes (driven by real queries)

| Table | Index | Serves |
| ----- | ----- | ------ |
| tickets | unique (`ticket_number`) | Lookup by ticket ID, route binding |
| tickets | (`customer_id`, `status`, `created_at`) | Customer's ticket lists |
| tickets | (`assigned_agent_id`, `status`) | Agent queue, workload count for auto-assignment |
| tickets | (`status`, `priority`) | Dashboard counts, filters |
| tickets | (`status`, `sla_due_at`) | Breach scan, "breached" filter |
| tickets | (`status`, `sla_warning_at`) | Approaching scan, "approaching" filter |
| tickets | (`category_id`, `status`) | Category filter, auto-assignment |
| tickets | (`created_at`) | Date range filter, default sort |
| tickets | FULLTEXT (`subject`, `description`) | Keyword search |
| tickets | unique (`external_reference`) | CSV duplicate detection |
| ticket_messages | (`ticket_id`, `created_at`) | Conversation timeline |
| attachments | (`ticket_id`), (`ticket_message_id`) | Attachment lists |
| ticket_activities | (`ticket_id`, `created_at`) | Activity timeline |
| ticket_import_errors | (`ticket_import_id`, `row_number`) | Error report |

Each index is justified in the README with the query it supports, and the README shows `EXPLAIN` output for the main list query.

---

## 5. Feature Designs

### 5.1 Ticket management and status workflow

`TicketStatus` enum owns the allowed transitions through `canTransitionTo(TicketStatus $to): bool`.

```
Open ──► In Progress ──► Waiting for Customer ──► In Progress
  │           │                    │
  └───────────┴──────► Resolved ◄──┘
                          │  ├──► Closed (terminal; manual or auto after N days)
                          └──► Open (reopened by customer reply)
```

- Agent's first public reply on an Open ticket sets `first_responded_at` and moves it to In Progress.
- Customer reply on Waiting for Customer moves it back to In Progress.
- Customer reply on Resolved reopens it (Open) and records an activity.
- A scheduled job auto-closes Resolved tickets after a configurable number of days (default 3).
- "Active" list = Open, In Progress, Waiting for Customer.
- "Resolved/Closed" list = Resolved, Closed.
- Edits use `lock_version` for optimistic locking; a stale update returns 409 so two agents never overwrite each other silently.

### 5.2 Ticket ID generation

Format: `TKT-{YEAR}-{SEQUENCE padded to 6}`, for example `TKT-2026-000001`.

`TicketNumberGenerator::next(): string`:

1. Inside the ticket-creation transaction, `SELECT ... FOR UPDATE` the `ticket_sequences` row for the current year (insert it with `insertOrIgnore` first if missing).
2. Increment `last_number`, then format with `sprintf('%s-%d-%06d', $prefix, $year, $number)`.
3. The unique index on `ticket_number` is the final guarantee.

Properties:

- No gaps under normal operation and no duplicates under concurrency (row lock serialises creators per year only).
- Sequence restarts every year, so the year part stays meaningful.
- Growth: past 999,999 tickets in a year, `%06d` simply produces 7 digits (`TKT-2026-1000000`); the column is `varchar(32)` and nothing parses a fixed width.
- Prefix and padding live in `config/tickets.php`.
- `nextBlock(int $count)` reserves a range in one lock for the CSV importer.

### 5.3 SLA management

**Configuration**
- `sla_policies` table (Admin-editable) maps priority to resolution minutes and warning threshold.
- `SlaPolicyRepository` caches policies (`Cache::rememberForever`, cleared when a policy is saved), so the rest of the app asks one class and never hardcodes hours.
- Seeded defaults: Low 48h, Medium 24h, High 8h, Critical 2h, warning at 75% of the business time elapsed.
- Interpretation of "48 hours": business hours (the business-hours section of the assessment says so explicitly). This is documented as an assumption.

**Calculation** (`SlaCalculator`)
- On creation: `sla_started_at = created_at`, `sla_due_at = addBusinessMinutes(start, policyMinutes)`, `sla_warning_at = addBusinessMinutes(start, floor(policyMinutes * threshold%))`.
- On priority change: recompute both from the original `sla_started_at` with the new policy, and log the change.
- Storing `sla_warning_at` and `sla_due_at` as columns makes SLA status filterable and indexable with plain range queries.

**SLA status** (derived, `SlaStatus` enum)

| Status | Rule |
| ------ | ---- |
| Within SLA | Active and `now < sla_warning_at` |
| Approaching | Active and `sla_warning_at <= now < sla_due_at` |
| Breached | Active and `now >= sla_due_at` |
| Met | Resolved/Closed and `resolved_at <= sla_due_at` |
| Missed | Resolved/Closed and `resolved_at > sla_due_at` |

The same rules exist as an accessor (for responses) and as query scopes (for filters and dashboard), covered by tests that check both agree.

**Stretch:** pause the SLA clock while Waiting for Customer (add `sla_paused_at` and `sla_paused_minutes`, shift `sla_due_at` on resume).
Only build this after the core is solid.

### 5.4 Business hours

`BusinessHoursCalculator` is a pure, dependency-free class (constructed from a `BusinessSchedule` value object loaded from `business_hours` + `holidays`, cached).
It has no database access, so it is fast to unit test with data sets.

Configuration:
- Timezone: `BUSINESS_TIMEZONE` env (default `Asia/Dhaka`).
- Seeded schedule: Sunday to Thursday, 09:00 to 18:00; Friday and Saturday closed.
- Admin screen to edit hours and holidays.

Algorithm for `addBusinessMinutes(CarbonImmutable $start, int $minutes): CarbonImmutable`:

```
cursor = start converted to business timezone
remaining = minutes
loop:
  if cursor's day is non-working or a holiday, or cursor >= closing time:
      cursor = opening time of the next working day; continue
  if cursor < opening time: cursor = opening time
  available = closing time - cursor (minutes)
  if remaining <= available: return (cursor + remaining) converted to UTC
  remaining -= available
  cursor = opening time of the next working day
```

A safety cap (for example 366 iterations) throws if the schedule has no working days at all.

Worked examples (each becomes a test case):

| Created (local) | Priority | Result |
| --------------- | -------- | ------ |
| Thu 17:00 | Critical (2h) | 1h on Thu (17:00-18:00), Fri/Sat closed, 1h on Sun → **Sun 10:00** |
| Fri 11:00 | Critical (2h) | Clock starts Sun 09:00 → **Sun 11:00** |
| Sun 08:00 | High (8h) | Starts 09:00 → **Sun 17:00** |
| Mon 10:00 | Medium (24h) | 8h Mon + 9h Tue + 7h Wed → **Wed 16:00** |
| Wed 17:30 before a Thu holiday | Critical (2h) | 30m Wed, Thu holiday, Fri/Sat closed, 90m Sun → **Sun 10:30** |

### 5.5 Assignment

**Manual assignment** (Admin): `PUT /tickets/{ticket}/assignment` with `agent_id`.
Validates that the target is an available agent; runs in a transaction with `lockForUpdate()` on the ticket; logs `assigned` or `reassigned` activity; dispatches `TicketAssigned`.

**Claim** (Agent): `POST /tickets/{ticket}/claim` - see 5.6.

**Automatic assignment** (strategy: category-aware least-workload with round-robin tie break):

1. Candidate agents: `role = agent`, `is_available = true`, linked to the ticket's category in `agent_category` (fall back to all available agents if none match the category).
2. Exclude agents at or above `max_open_tickets`.
3. Pick the agent with the fewest active tickets (one grouped query using the `(assigned_agent_id, status)` index).
4. Tie break by oldest `last_assigned_at` (round-robin among equally loaded agents).
5. Assign with the same atomic conditional update used by claim, then update `last_assigned_at`.

It runs in a queued listener on `TicketCreated`, so ticket creation stays fast.
A `Cache::lock('auto-assign')` serialises concurrent auto-assignments so two tickets created at the same moment see accurate workloads.
If no agent is available the ticket stays unassigned and shows up in the "Unassigned" dashboard tile; a scheduled job retries unassigned tickets every few minutes.
`AutoAssignmentStrategy` is an interface bound in a service provider, so switching to pure round-robin is a one-line change (a likely interview exercise).

### 5.6 Concurrent ticket assignment

Problem: two agents click "Accept" on the same unassigned ticket at the same time.

Solution: an atomic conditional update; the database decides the winner.

```php
$affected = Ticket::query()
    ->whereKey($ticket->id)
    ->whereNull('assigned_agent_id')
    ->whereIn('status', TicketStatus::active())
    ->update([
        'assigned_agent_id' => $agent->id,
        'lock_version' => DB::raw('lock_version + 1'),
        'updated_at' => now(),
    ]);

if ($affected === 0) {
    throw new TicketAlreadyAssignedException($ticket); // → HTTP 409
}
```

Why this approach:

- MySQL/InnoDB row-locks the row during the `UPDATE`, so the second statement re-evaluates `assigned_agent_id IS NULL` after the first commits and matches zero rows.
- No lock is held across application code, so there is no deadlock risk and no lock wait under load.
- The activity log entry and the `TicketAssigned` event are written in the same transaction only when `$affected === 1`.
- The frontend shows "Already taken by Agent X" on 409 and refreshes the ticket.

Tests:
- Feature test: first claim returns 200, second returns 409, and only one `assigned` activity exists.
- Concurrency test (MySQL only, bonus): two separate PDO connections race the same update; exactly one wins.

### 5.7 Ticket conversation

- `ticket_messages.type` is `reply` or `internal_note`.
- Customers can only create `reply`; the FormRequest rejects `internal_note` from customers, and the Policy re-checks it.
- Read path: `TicketMessage::scopeVisibleTo($user)` removes internal notes for customers, and attachments on internal notes inherit that restriction.
- Notifications for internal notes go to agents only.
- UI: agents get a "Reply / Internal note" toggle; notes render with a distinct yellow style.
- Test: a customer fetching a ticket, its messages, its activity timeline, or an attachment URL never sees internal-note content (activity entries for notes are filtered too).

### 5.8 Attachments

- Upload with the ticket or with a reply (`multipart/form-data`), max 5 files per request.
- Validation: `File::types([...])->max(10 * 1024)` with an allow-list (pdf, png, jpg, jpeg, gif, webp, txt, csv, log, docx, xlsx, zip); Laravel checks MIME by content, not only the extension.
- Storage: private disk (`local` under `storage/app/private` in development, private Cloudflare R2 bucket in production), path `tickets/{ticket_id}/{uuid}.{ext}`, so user-supplied file names never reach the filesystem.
- `original_name` is sanitised and only used in `Content-Disposition` on download.
- Access: `GET /attachments/{attachment}/download` authorises through `AttachmentPolicy` (ticket visibility + internal-note rule), then returns a short-lived temporary URL (S3) or streams the file (local) with `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`.
- `checksum_sha256` stored for integrity and future de-duplication.

### 5.9 SLA escalation

Scheduler (`routes/console.php`):

```php
Schedule::command('tickets:check-sla')->everyMinute()->withoutOverlapping()->onOneServer();
```

`SlaEscalationService`:

1. **Approaching:** active tickets where `sla_warning_at <= now` and `sla_warning_notified_at IS NULL`.
2. **Breached:** active tickets where `sla_due_at <= now` and `sla_breached_at IS NULL`.
3. Process with `chunkById(200)`.
4. Each ticket is marked with a conditional update (`... WHERE id = ? AND sla_breached_at IS NULL`), so overlapping runs or retries never double-escalate (idempotent).
5. On breach: set `sla_breached_at`, `is_escalated = true`, `escalated_at`; write `sla_breached` and `escalated` activities; dispatch `TicketSlaBreached`.
6. On approaching: set `sla_warning_notified_at`; write `sla_warning` activity; dispatch `TicketSlaApproaching`.

Who is notified:
- Approaching: assigned agent (or all admins if unassigned).
- Breached: assigned agent + all admins.

Tests use `$this->travelTo()` to move time and assert state, activities and queued notifications, including a second run producing no duplicates.

### 5.10 Notifications

Every notification class implements `ShouldQueue` and uses `mail` + `database` channels; the database channel powers an in-app notification bell.

| Event | Recipients |
| ----- | ---------- |
| Ticket created | Customer (confirmation), admins if unassigned |
| Ticket assigned | Assigned agent |
| Agent replied (public) | Customer |
| Customer replied | Assigned agent (or admins if unassigned) |
| Internal note | Assigned agent if the author is someone else |
| Ticket resolved | Customer |
| SLA approaching | Assigned agent / admins |
| SLA breached | Assigned agent + admins |

- Queue: database connection with `after_commit => true`, worker started by `php artisan queue:work --tries=3 --backoff=10`.
- Event → Listener → Notification keeps services free of notification logic.
- Users never receive notifications about their own actions.
- CSV-imported tickets do not trigger notifications.

### 5.11 Search, filtering and pagination

`GET /api/v1/tickets` query parameters:

| Param | Behaviour |
| ----- | --------- |
| `ticket_number` | Exact match (fast unique index) |
| `q` | FULLTEXT search in boolean mode on subject + description; falls back to exact ticket number if the input matches the `TKT-` pattern |
| `status[]`, `priority[]`, `category_id[]` | `whereIn` |
| `assigned_agent_id` | Agent id, or `unassigned` |
| `customer_id` | Staff only |
| `created_from`, `created_to` | Date range on `created_at` |
| `sla_status` | Maps to the SLA query scopes from 5.3 |
| `view` | `active` or `closed` |
| `sort` | Allow-listed columns (`created_at`, `sla_due_at`, `priority`), default `-created_at` |
| `per_page` | Default 20, max 100 |

- `TicketQueryBuilder` receives a validated `TicketFilterData` DTO and applies `visibleTo($user)` first.
- Eager loads `customer:id,name`, `agent:id,name`, `category:id,name` and `withCount('messages')`.
- List endpoint uses a lighter `TicketListResource` (no description body).
- Length-aware pagination for the UI; the README notes cursor pagination as the scaling path for very deep pages.
- The frontend keeps filters in the URL query string, so filtered views are shareable and survive refresh.

### 5.12 CSV import

Flow:

1. Admin uploads a CSV (`max:51200` KB, `mimes:csv,txt`); file is stored privately; a `ticket_imports` row is created with status `pending`; `ImportTicketsJob` is dispatched; the API returns 202 with the import id.
2. `ImportTicketsJob` streams the file (`SplFileObject` / `LazyCollection`, never loading it all), validates headers, counts rows, and dispatches a `Bus::batch()` of `ProcessImportChunkJob` jobs of 500 rows each.
3. Each chunk job:
   - Validates each row with the same rules as the API (`CsvRowValidator`): required columns, enum values, known category, known customer email, valid date.
   - Detects duplicates in two ways: `external_reference` already in the database (one `whereIn` per chunk), and repeats inside the same file.
   - Reserves ticket numbers with `TicketNumberGenerator::nextBlock()`.
   - Computes SLA fields with `SlaCalculator` (from the CSV `created_at` if present).
   - Bulk inserts valid tickets and one `imported` activity each in a single transaction per chunk.
   - Writes invalid rows to `ticket_import_errors` with row number and messages.
   - Increments counters atomically on `ticket_imports`.
4. Batch `then` / `catch` / `finally` callbacks set the final status.

CSV columns: `external_reference, customer_email, subject, description, priority, category, status, created_at`.

Assumptions (documented): unknown customer emails are rejected as row errors (no silent account creation); notifications and auto-assignment are skipped for imports.

UI: upload page with a progress bar (polls `GET /imports/{id}` every 2 seconds), counters, and a "Download error report" button (`GET /imports/{id}/errors.csv`, streamed).
A sample CSV and a generator command (`php artisan tickets:make-sample-csv --rows=100000`) are included for testing large files.

### 5.13 Dashboard

`GET /api/v1/dashboard` returns role-scoped numbers from a handful of aggregate queries (no loading of models):

- One conditional-aggregation query over `tickets` for: total open, unassigned, critical, SLA breached, SLA approaching, resolved today / this week.
- `GROUP BY priority` for tickets by priority (active only).
- `GROUP BY assigned_agent_id` joined to users for tickets by agent (open count, breached count).
- Results cached for 60 seconds per role scope; cache key includes the user id for agents and customers.

UI: stat tiles that link to the pre-filtered ticket list (for example "SLA breached: 12" opens `/tickets?sla_status=breached`), a priority breakdown bar, and an agent workload table.

### 5.14 Activity / audit history

- `ActivityLogger::log(Ticket $ticket, ActivityType $type, ?User $actor, array $old = [], array $new = [])` is called explicitly from services inside the same transaction as the change.
- Explicit calls are preferred over model observers, so each entry carries business context (who, why, which action) and imports can bulk insert.
- System actions (escalation, auto-assignment, auto-close) have `actor_id = null` and display as "System".
- `GET /tickets/{ticket}/activities` returns the timeline, formatted on the frontend as in the assessment example ("10:05 AM - Assigned to Agent B").
- Customers see a filtered timeline (internal-note entries removed).

---

## 6. API Surface (v1)

```
POST   /auth/register | /auth/login | /auth/logout
GET    /auth/me

GET    /tickets                         list + search + filters
POST   /tickets                         create (with attachments)
GET    /tickets/{ticket_number}         show
PATCH  /tickets/{ticket_number}         update (lock_version required)
POST   /tickets/{ticket_number}/status  transition (resolve, close, reopen, ...)
POST   /tickets/{ticket_number}/claim   agent claims (409 if taken)
PUT    /tickets/{ticket_number}/assignment  admin assigns / unassigns

GET    /tickets/{ticket_number}/messages
POST   /tickets/{ticket_number}/messages      reply or internal note (+ attachments)
GET    /tickets/{ticket_number}/activities
GET    /attachments/{attachment}/download

GET    /dashboard
GET    /notifications | POST /notifications/{id}/read | POST /notifications/read-all

POST   /imports                         admin uploads CSV (202)
GET    /imports | /imports/{id} | /imports/{id}/errors.csv

Admin: /admin/users, /admin/categories, /admin/sla-policies, /admin/business-hours, /admin/holidays
```

All responses use API Resources; errors use Laravel's default JSON format (`message`, `errors`).
Scramble serves OpenAPI docs at `/docs/api`.

---

## 7. Frontend Plan

### 7.1 Structure

```
frontend/src/
├── app/            # router, providers (QueryClient, Auth), layout, route guards
├── features/
│   ├── auth/       # login, register, useAuth, token storage
│   ├── tickets/    # list + filters, detail, create/edit, conversation,
│   │               # attachments, activity timeline, SLA badge, claim button
│   ├── dashboard/
│   ├── imports/
│   ├── notifications/
│   └── admin/      # users, categories, SLA policies, business hours
├── shared/
│   ├── api/        # axios instance, interceptors (token, 401 → logout, 409/422 handling)
│   ├── components/ # Table, Pagination, Badge, Modal, FileDropzone, EmptyState
│   └── lib/        # date formatting, query-string helpers
└── main.tsx
```

### 7.2 Screens

- Login / Register.
- Dashboard (role-aware tiles linking to filtered lists).
- Tickets list: Active / Resolved tabs, filter bar, search box, sortable columns, SLA badge with countdown, pagination.
- Ticket detail: header (number, status, priority, SLA, assignee with Claim / Assign controls), conversation with Reply / Internal note toggle and attachments, activity timeline in a side panel.
- Create ticket form with file dropzone.
- Admin: users and agents (availability, categories, max load), categories, SLA policies, business hours and holidays, CSV import with progress.
- Notification bell with unread count (polling every 30s; real-time with Laravel Reverb is a stretch bonus).

### 7.3 Conventions

- Route guards by role, plus the server is always the authority; the UI hides actions the API would reject.
- Responsive layout (mobile list → cards).
- Loading, empty and error states on every data view.
- `VITE_API_URL` env var for the API base URL.

---

## 8. Testing Strategy

The test database is MySQL (same image as development), because FULLTEXT search, `FOR UPDATE` locking and the conditional-update race depend on MySQL behaviour.
Tests use `RefreshDatabase` and model factories with role states.
The ticket list, ticket detail and dashboard tests assert a fixed query count (`$this->expectsDatabaseQueryCount()`) with several records seeded, so N+1 regressions fail tests.

Priority order (what matters most first):

1. **Unit: `BusinessHoursCalculator`** - Pest data sets for all worked examples in 5.4 plus edge cases (exactly at opening, exactly at closing, multi-week spans, holidays, timezone conversion).
2. **Unit: `SlaCalculator` and SLA status** - deadline per priority, warning time, priority change recalculation, accessor and scope agree.
3. **Feature: authorization matrix** - data-driven test over role × action from section 3.4, including customers fetching another customer's ticket (404), and internal notes never appearing in any customer response.
4. **Feature: concurrent claim** - second claim returns 409, single activity; MySQL race test with two connections as bonus.
5. **Feature/Unit: ticket number generation** - format, sequential, yearly rollover (with `travelTo`), block reservation.
6. **Feature: SLA escalation command** - approaching and breached detection, idempotency on second run, notifications queued (`Notification::fake()`).
7. **Unit/Feature: auto-assignment** - category match, least workload, tie break, capacity limit, no agents available.
8. **Feature: attachments** - type and size rejection, private storage, download authorisation, internal-note attachments hidden from customers.
9. **Feature: CSV import** - valid rows imported, invalid rows reported with row numbers, in-file and database duplicates skipped, counters correct (`Bus::fake()` for dispatch, real run for processing).
10. **Feature: search and filters** - each filter, combined filters, pagination meta, visibility scope respected.
11. **Feature: status transitions** - allowed and forbidden transitions, reopen on customer reply.
12. **Feature: dashboard** - counts are correct and role-scoped; query count stays constant as data grows.
13. **Frontend (Vitest + RTL)** - filter bar ↔ URL sync, SLA badge states, claim button 409 handling, role-based visibility of internal-note toggle.
14. **E2E (bonus, Playwright)** - customer creates ticket → agent claims and replies → customer sees reply but not the note.

TDD per the project's `/tdd` skill during each phase: seams are confirmed before writing tests.

---

## 9. Local Development with Docker

`docker-compose.yml` services:

| Service | Image | Purpose |
| ------- | ----- | ------- |
| `app` | custom `php:8.5-fpm` (pdo_mysql, intl, zip, bcmath, pcntl, opcache, gd) | Laravel API |
| `nginx` | `nginx:alpine` | Serves the API on `http://localhost:8000` |
| `queue` | same image as `app` | `php artisan queue:work --tries=3` |
| `scheduler` | same image as `app` | `php artisan schedule:work` |
| `mysql` | `mysql:9.7` | Database (named volume, healthcheck) |
| `mysql-test` | `mysql:9.7` with tmpfs | Fast isolated test database |
| `mailpit` | `axllent/mailpit` | Catch emails at `http://localhost:8025` |
| `frontend` | `node:26-alpine` | Vite dev server on `http://localhost:5173` |

Developer commands (documented in README):

```bash
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
docker compose exec frontend npm test
docker compose exec frontend npm run lint
```

Seeders create: 1 admin, 5 agents across categories, 20 customers, categories, SLA policies, business hours, and ~500 tickets spread across statuses, priorities and SLA states so every dashboard tile and filter has data.
Demo credentials live only in the seeder and README for local use.

---

## 10. Free Deployment (fully managed, no VM)

Goal: deploy with the fewest moving parts, no server to manage, and auto-deploy on `git push`.

### 10.1 Target setup

| Part | Service | Free tier (verify before signing up) |
| ---- | ------- | ------------------------------------ |
| Frontend | Vercel (Hobby) | Static Vite build on a CDN, Git auto-deploy |
| Backend API + queue worker + scheduler | Render free web service (Docker) | 512 MB RAM, 0.1 CPU, 750 instance-hours per month, sleeps after 15 minutes idle |
| Database | Aiven for MySQL (free plan) | Real MySQL, 1 GB RAM, 1 GB disk, no credit card |
| Attachments + CSV uploads | Cloudflare R2 (S3-compatible) | 10 GB storage; Backblaze B2 is the fallback if R2 asks for a card you would rather not add |
| Email | Brevo free SMTP | About 300 emails per day, sender email verification only |
| Keep-awake pinger | cron-job.org | Free HTTP cron |

Five sign-ups, zero servers, and every piece deploys or runs on its own.

### 10.2 Why the backend should not go on Vercel

Vercel suits the React frontend perfectly.
The Laravel backend needs a long-running queue worker, a scheduler that ticks every minute, and persistent storage.
Vercel's serverless PHP runtime has no long-running process, Hobby cron jobs run at most once per day, and the filesystem is ephemeral.

### 10.3 Backend on Render: one container does everything

Render charges for separate background-worker and cron services, so the free setup runs all backend processes inside the single free web service:

- `backend/Dockerfile` (production stage): `php:8.5-fpm` + nginx + supervisord, with Composer dependencies installed `--no-dev` and opcache enabled.
- `docker/supervisord.conf` starts four programs: `php-fpm`, `nginx`, `php artisan queue:work --tries=3 --max-time=3600`, and `php artisan schedule:work`.
- Container start script runs `php artisan migrate --force` and `php artisan optimize` before supervisord.
- `render.yaml` (Render Blueprint) in the repo root describes the service, so the whole setup is reproducible from the repo; secrets are entered in the Render dashboard, never committed.
- Render auto-deploys on every push to `main`.
- Health check path: `/up` (built into Laravel).

**Sleep handling.**
Free services sleep after 15 minutes without traffic, which also pauses the worker and scheduler.
A cron-job.org job calls `/up` every 10 minutes to keep the service awake; one always-on service uses about 720 of the 750 free hours per month.
If it sleeps anyway, nothing is lost: queued jobs wait in the database, and the SLA escalation command scans by timestamps and is idempotent, so the first run after waking catches up on every ticket it missed.

### 10.4 No Redis: database drivers everywhere

To avoid a sixth service, the app uses Laravel's database drivers for everything Redis would have done:

- `QUEUE_CONNECTION=database` (jobs, batches and failed jobs live in MySQL).
- `CACHE_STORE=database` (also provides the atomic locks used by auto-assignment, `withoutOverlapping()` and `onOneServer()`).
- `SESSION_DRIVER=array` (the API is stateless with Bearer tokens).

The same drivers run locally, so development matches production.
This is comfortably fast for an assessment-scale workload; switching to Redis later is an environment-variable change.

### 10.5 Database on Aiven

- Aiven requires TLS: download its CA certificate and point `MYSQL_ATTR_SSL_CA` to it (path set through an env var; the certificate is public, not a secret).
- Aiven runs MySQL 8.x while local development uses MySQL 9.7; the schema and queries use only features available in both (FULLTEXT, `FOR UPDATE`, JSON columns), and the README notes this.
- With 1 GB of disk, keep demo seeding and demo CSV imports modest (for example 5,000 tickets); the large-file test runs locally.
- Aiven may power off a free service that sits unused for a long time, so open the demo shortly before sharing the link.

### 10.6 Files on Cloudflare R2

- Render's free disk is wiped on every deploy and restart, so attachments and uploaded CSVs go to R2 through Laravel's `s3` disk (`league/flysystem-aws-s3-v3`).
- `FILESYSTEM_DISK=local` in development and `FILESYSTEM_DISK=s3` in production; no code changes between them.
- The bucket stays private; downloads use short-lived temporary URLs issued after the policy check (section 5.8).
- The CSV import job reads the file as a stream from the disk, so it works the same on local storage and R2.

### 10.7 Frontend on Vercel

- Project root set to `frontend/`, framework preset Vite, build `npm run build`, output `dist`.
- `vercel.json` rewrite of all paths to `/index.html` for client-side routing.
- `VITE_API_URL` set to the Render backend URL.
- Backend `config/cors.php` allows only the Vercel production domain.
- Vercel auto-deploys on every push to `main`.

### 10.8 Known trade-offs of the free setup

- The first request after a sleep takes 30 to 60 seconds; the pinger makes this rare.
- 0.1 CPU makes large CSV imports slow in the demo; the progress bar keeps that visible to the reviewer.
- If any free tier changes, the fallback with the same architecture is Railway (low-cost usage plan) or upgrading the Render instance to the cheapest paid tier, which also removes sleeping.

---

## 11. CI/CD (optional bonus, Phase 11)

Left out of the foundation phase; added only if time remains.
GitHub Actions `.github/workflows/ci.yml` on every push and PR:

- Backend job: MySQL 9.7 service container, `composer install`, Pest.
- Frontend job: `npm ci`, Oxlint, `tsc -b`, Vitest, `vite build`.
- Deployment needs no CI job: Render and Vercel both auto-deploy from `main`; CI can be set as a required check before merging.

---

## 12. Delivery Phases (vertical slices)

Quality of finished phases comes first; phases 10 and 11 are cut first if time runs short.

| # | Phase | Main outputs |
| - | ----- | ------------ |
| 0 | Foundation | Repo layout, Docker compose, Laravel 13 + React 19 skeletons, Oxlint, Pest, Vitest |
| 1 | Auth and roles | Sanctum tokens, register/login/logout, `UserRole`, seeders, route guards, login UI, auth tests |
| 2 | Core tickets | Schema + indexes, `TicketNumberGenerator`, create/view/update, status transitions, policies + visibility scope, activity logger, list (active/closed) + detail UI |
| 3 | SLA + business hours | `sla_policies`, `business_hours`, `holidays`, `BusinessHoursCalculator`, `SlaCalculator`, SLA status accessor + scopes, SLA badge UI, admin SLA/hours screens |
| 4 | Assignment | Manual assign, claim with atomic update (409), auto-assignment strategy + queued listener + retry job, agent settings UI |
| 5 | Conversation + attachments | Replies, internal notes, attachment upload/storage/download, conversation UI with note toggle and dropzone |
| 6 | Notifications + escalation | Events, queued notifications (mail + database), scheduler command, escalation service, notification bell UI |
| 7 | Search + filters | `TicketQueryBuilder`, FULLTEXT search, all filters, sort, pagination, URL-synced filter bar |
| 8 | Dashboard | Aggregate queries, caching, role-scoped tiles, by-priority and by-agent widgets |
| 9 | CSV import | Upload, streamed job + batched chunks, validation, duplicates, error report, progress UI, sample generator |
| 10 | Docs + deployment | README (setup, env, migrations, queue, scheduler, tests, architecture decisions, assumptions), OpenAPI docs, production Dockerfile + supervisord + `render.yaml`, deploy to Render, Aiven, R2 and Vercel |
| 11 | Bonus | GitHub Actions CI, Playwright E2E, Reverb real-time updates, SLA pause on Waiting for Customer, first-response SLA, rate-limit tuning, structured logging |

---

## 13. README Checklist (submission requirements)

- Project overview and feature list with screenshots.
- Prerequisites (Docker only) and step-by-step local setup.
- Environment variables (`.env.example` for both apps, no secrets committed).
- Database migration and seeding commands, demo accounts.
- Queue worker and scheduler: what runs, how to start them, how to trigger SLA checks manually.
- Running tests (backend, frontend, E2E).
- Architecture decisions: layering, auth choice, ticket number strategy, concurrency strategy, auto-assignment strategy, SLA and business-hours design, escalation idempotency, import pipeline, indexing rationale.
- Assumptions (SLA counts business time, unknown CSV customers rejected, imports skip notifications, and so on).
- Deployment notes and demo URL.

---

## 14. Interview Readiness

Be ready to explain and change these areas live, since they are the most likely interview exercises:

- Changing an SLA rule (edit `sla_policies` or the warning threshold) and seeing deadlines change without code edits.
- Switching auto-assignment to pure round-robin (swap the strategy binding).
- Adding a new status (enum case + transition map + test).
- Changing who can do what (one Policy method + one row in the authorization test data set).
- Explaining why the claim uses a conditional update instead of `lockForUpdate`, and why the ticket sequence uses `lockForUpdate`.
- Reading `EXPLAIN` for the ticket list and justifying each index.
- Adding a filter end to end (DTO field → query builder → request rule → UI control → test).

---

## 15. Open Questions

These have defaults in this plan; confirm or change them before Phase 0.

- **Q1. TypeScript or plain JavaScript for the frontend?** Decided: TypeScript 7 with Oxlint (the Vite template defaults).
- **Q2. Business timezone?** Default: `Asia/Dhaka` (matches the Sunday-Thursday example).
- **Q3. Should SLA "48 hours" mean business hours or calendar hours?** Default: business hours.
- **Q4. Should unknown customer emails in the CSV create accounts automatically?** Default: no, report them as row errors.
- **Q5. Should the SLA clock pause while Waiting for Customer?** Default: no in the core build, available as a stretch item.
- **Q6. Deployment target?** Decided: fully managed, no VM (Vercel + Render + Aiven + Cloudflare R2 + Brevo).
