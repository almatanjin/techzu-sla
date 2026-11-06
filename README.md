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

### User Administration (`/api/v1/admin/users`, `/api/v1/admin/agents`)

| Method | Endpoint | Access | Description |
| ------ | -------- | ------ | ----------- |
| `GET` | `/api/v1/admin/users` | Admin only | List users with search, role, status and pagination |
| `POST` | `/api/v1/admin/users` | Admin only | Create user with specified role and password |
| `GET` | `/api/v1/admin/users/{id}` | Admin only | Retrieve user details |
| `PATCH` | `/api/v1/admin/users/{id}` | Admin only | Update user details, role, status or password |
| `GET` | `/api/v1/admin/agents` | Admin only | Overview of agents, workload and category assignments |

### Categories (`/api/v1/categories`, `/api/v1/admin/categories`)

| Method | Endpoint | Access | Description |
| ------ | -------- | ------ | ----------- |
| `GET` | `/api/v1/categories` | Authenticated | List active categories (Admins may include inactive with `include_inactive=1`) |
| `POST` | `/api/v1/admin/categories` | Admin only | Create a new ticket category |
| `PATCH` | `/api/v1/admin/categories/{id}` | Admin only | Rename, activate, or deactivate a category |

### Customer Lookup (`/api/v1/customers`)

| Method | Endpoint | Access | Description |
| ------ | -------- | ------ | ----------- |
| `GET` | `/api/v1/customers` | Staff only | Search active customers by name or email fragment |
| `GET` | `/api/v1/customers/{id}` | Staff only | Retrieve customer details (id, name, email) by user ID |

### Tickets (`/api/v1/tickets`)

| Method | Endpoint | Access | Description |
| ------ | -------- | ------ | ----------- |
| `GET` | `/api/v1/tickets` | Authenticated | List tickets (`view=active`, `view=closed`, or `view=all`, with optional `sla_status` for staff) |
| `POST` | `/api/v1/tickets` | Authenticated | Open a support ticket |
| `GET` | `/api/v1/tickets/{ticket_number}` | Authenticated | Retrieve ticket details and permissions (includes staff `sla` block) |
| `PATCH` | `/api/v1/tickets/{ticket_number}` | Authenticated | Update ticket content or classification with `lock_version` |
| `PUT` | `/api/v1/tickets/{ticket_number}/assignment` | Admin only | Assign or unassign ticket with `lock_version` |
| `POST` | `/api/v1/tickets/{ticket_number}/claim` | Staff only | Claim an unassigned ticket atomically |
| `POST` | `/api/v1/tickets/{ticket_number}/release` | Assignee only | Release assigned ticket back to unassigned pool |
| `POST` | `/api/v1/tickets/{ticket_number}/status` | Authenticated | Transition ticket status with `lock_version` |
| `GET` | `/api/v1/tickets/{ticket_number}/activities` | Authenticated | Retrieve ticket activity history timeline (oldest first) |
| `GET` | `/api/v1/tickets/{ticket_number}/messages` | Authenticated | List conversation messages (newest first, 30 per page with cursor pagination) |
| `POST` | `/api/v1/tickets/{ticket_number}/messages` | Authenticated | Post a public reply or internal note (`multipart/form-data`) |
| `GET` | `/api/v1/attachments/{id}/download` | Authenticated | Download an attachment as a file stream |

#### Ticket search rules

The search parameter accepts exact ticket numbers and boolean keyword queries:
- **Number shape**: Inputs matching `TKT-YYYY-NNNNNN` (case-insensitive, surrounding whitespace trimmed) locate the exact ticket number and bypass the active/closed view tab.
- **3-character words**: Keyword searches require tokens of at least 3 characters.
Submitting only shorter tokens returns a 422 validation response with "Search for words of at least 3 characters." on `search`.
- **Prefix matching**: Tokens match as prefixes, allowing `print` to match `printer`.
- **All words required**: Multi-word queries mandate the presence of all extracted tokens across subject and description.
- **No matches inside words**: Search matches prefixes and full words only, so `rint` does not match `printer`.

#### Database indexes and query execution

Database indexes optimize listing, searching, filtering, and SLA tracking:

| Index Name | Columns | Serves |
| ---------- | ------- | ------ |
| `tickets_fulltext` | FULLTEXT (`subject`, `description`) | Boolean keyword search |
| `tickets_ticket_number_unique` | (`ticket_number`) | Exact ticket number search |
| `tickets_created_at_index` | (`created_at`) | Default ticket sorting, date range filter |
| `tickets_customer_id_status_created_at_index` | (`customer_id`, `status`, `created_at`) | Customer portal ticket lists, customer filter |
| `tickets_category_id_status_index` | (`category_id`, `status`) | Category filter, automatic assignment matching |
| `tickets_status_priority_index` | (`status`, `priority`) | Priority filter, status counts |
| `tickets_status_sla_due_at_index` | (`status`, `sla_due_at`) | SLA check background command, SLA due sorting |
| `tickets_status_sla_warning_at_index` | (`status`, `sla_warning_at`) | SLA warning notifications |
| `tickets_assigned_agent_id_status_index` | (`assigned_agent_id`, `status`) | Assignment filter (`assigned=me`, `assigned={id}`) |
| `tickets_resolved_at_index` | (`resolved_at`) | Dashboard resolved counts, daily resolved and SLA compliance series |
| `tickets_closed_at_index` | (`closed_at`) | Dashboard daily SLA compliance for tickets closed without a resolution |

##### Query plan: Default Active list

```sql
EXPLAIN FORMAT=TRADITIONAL
SELECT * FROM `tickets`
WHERE `status` IN ('open', 'in_progress', 'waiting_for_customer')
ORDER BY `created_at` DESC, `id` DESC
LIMIT 20;
```

Execution plan on development stack:
- Table: `tickets`
- Type: `ALL` (on small development datasets with under 100 rows, MySQL optimizer prefers an in-memory scan over index lookups; on tables with realistic volume, it uses `tickets_created_at_index`)
- Possible keys: `tickets_status_sla_due_at_index, tickets_status_sla_warning_at_index, tickets_status_priority_index`
- Extra: `Using where; Using filesort`

##### Query plan: Keyword search

```sql
EXPLAIN FORMAT=TRADITIONAL
SELECT * FROM `tickets`
WHERE MATCH (`subject`, `description`) AGAINST ('+login*' IN BOOLEAN MODE)
  AND `status` IN ('open', 'in_progress', 'waiting_for_customer')
ORDER BY `created_at` DESC, `id` DESC
LIMIT 20;
```

Execution plan on development stack:
- Table: `tickets`
- Type: `fulltext`
- Key: `tickets_fulltext`
- Possible keys: `tickets_status_sla_due_at_index, tickets_status_sla_warning_at_index, tickets_status_priority_index, tickets_fulltext`
- Rows: `1`
- Extra: `Using where; Ft_hints: no_ranking; Using filesort`

##### Deep pagination note

Offset-based pagination (`OFFSET N LIMIT M`) scans and discards `N` rows before returning the requested page.
For deep pagination (such as page 100 and beyond), cursor pagination on `(created_at, id)` is the recommended path to avoid linear scan costs.

### SLA and Business Hours Administration (`/api/v1/admin`)

| Method | Endpoint | Access | Description |
| ------ | -------- | ------ | ----------- |
| `GET` | `/api/v1/admin/sla-policies` | Admin, Agent (read-only) | List resolution policies and warning thresholds |
| `PATCH` | `/api/v1/admin/sla-policies/{priority}` | Admin only | Update resolution minutes and warning threshold percentage |
| `GET` | `/api/v1/admin/business-hours` | Admin, Agent (read-only) | Retrieve weekly business operating schedule and timezone |
| `PUT` | `/api/v1/admin/business-hours` | Admin only | Replace the 7-day operating schedule |
| `GET` | `/api/v1/admin/holidays` | Admin, Agent (read-only) | List holidays for a given year sorted by date |
| `POST` | `/api/v1/admin/holidays` | Admin only | Add a new holiday date and name |
| `DELETE` | `/api/v1/admin/holidays/{id}` | Admin only | Delete a holiday by ID |

### Dashboard (`/api/v1/dashboard`)

| Method | Endpoint | Access | Description |
| ------ | -------- | ------ | ----------- |
| `GET` | `/api/v1/dashboard` | Authenticated | Role-scoped operational dashboard metrics and recent tickets; staff also get a 14-day `daily` series |

### Ticket Imports (`/api/v1/admin/imports`)

| Method | Endpoint | Access | Description |
| ------ | -------- | ------ | ----------- |
| `GET` | `/api/v1/admin/imports` | Admin only | List ticket import history (newest first, 20 per page) |
| `POST` | `/api/v1/admin/imports` | Admin only | Upload CSV file to start background import (up to 50 MB) |
| `GET` | `/api/v1/admin/imports/{id}` | Admin only | Retrieve specific import details, progress, and counts |
| `GET` | `/api/v1/admin/imports/{id}/errors.csv` | Admin only | Download streaming error report CSV for skipped rows |
| `GET` | `/api/v1/admin/imports/template.csv` | Admin only | Download sample import template CSV |

## SLA and business hours

Every ticket receives an SLA resolution deadline based on its priority, measured strictly in business hours.
Because new required SLA columns exist on tickets, local databases must be rebuilt with `docker compose exec app php artisan migrate:fresh --seed`.

### Business hours schedule and timezone

The business calendar is defined by a weekly schedule and a list of holidays.
The configured timezone `TICKETS_TIMEZONE` (default: `Asia/Dhaka`, UTC+6) drives both ticket numbering years and business hours calculations.
By default, working days are Sunday through Thursday from 09:00 to 18:00 (9 working hours per day).
Friday and Saturday are closed days.
Configured holidays count as closed days even when falling on a regular working weekday.

### SLA policy defaults and "48 hours" interpretation

Policy durations are specified in business minutes with a default 75% warning threshold:
- **Low**: 2880 business minutes (48 business hours, spanning 5 full 9-hour working days plus 3 hours into the 6th working day).
- **Medium**: 1440 business minutes (24 business hours).
- **High**: 480 business minutes (8 business hours).
- **Critical**: 120 business minutes (2 business hours).

### Worked examples

All timestamps are stored in UTC and evaluated against the business calendar:
- **Critical ticket late on Thursday**: A Critical ticket created Thursday at 17:00 business time receives 1 hour on Thursday (until 18:00 closing).
Friday and Saturday are closed.
The remaining 1 hour is counted on Sunday morning from 09:00, making the deadline Sunday 10:00 (with warning threshold at Sunday 09:30).
- **Start on a closed day**: A ticket created Friday at 11:00 starts counting from Sunday 09:00 opening.
A 2-hour Critical ticket is due Sunday 11:00.
- **Start before opening**: A ticket created Sunday at 08:00 starts counting at 09:00 opening.
- **Holiday during the week**: When Thursday is a holiday, a Critical ticket created Wednesday at 17:30 counts 30 minutes on Wednesday, skips Thursday, Friday, and Saturday, and finishes its remaining 90 minutes Sunday at 10:30.

### The no-pause and snapshot rules

- **No pause rule**: The SLA clock never pauses for customer replies or status transitions.
Waiting for customer response still consumes SLA time.
If a resolved ticket is reopened after its deadline has passed, its SLA status immediately registers as `breached`.
- **Snapshot rule**: Deadlines are computed and stored upon ticket creation.
Changing a policy, the weekly schedule, or holidays applies exclusively to tickets created afterwards.
Existing ticket deadlines are never shifted by calendar or policy edits.
Reprioritizing an open ticket recomputes its deadline from its original start timestamp using the target priority's current policy.

### Staff-only visibility

SLA details are restricted to staff roles (Administrators and Support Agents).
Responses for staff include an `sla` object containing `status`, `started_at`, `warning_at`, `due_at`, and `policy_minutes`.
Responses to Customers completely omit the `sla` block.
The `sla_status` query filter (`within`, `approaching`, `breached`, `met`, `missed`) on `GET /api/v1/tickets` is accepted for staff and silently ignored for customers.

## Ticket Assignment

Tickets support automatic assignment on creation, manual reassignment by administrators, and self-service claiming by support agents.

### Selection rule

Automatic assignment uses a category-workload strategy:
- Category specialty has highest priority.
Only agents assigned to the ticket's category are evaluated first.
- Among matching candidates, the agent with the lowest active ticket workload is selected.
Active workload counts open, in-progress, and waiting-for-customer tickets.
- Agents who have reached their `max_open_tickets` threshold or are marked unavailable are skipped.
- When no available agent handles the category, assignment falls back to any available agent with the lowest active workload below their cap.
- Tied candidates are broken deterministically: the candidate with the oldest `last_assigned_at` timestamp is picked, favoring candidates who have never been assigned, with lowest ID as the final tie-breaker.
- This strategy balances incoming tickets across the team while ensuring domain specialists receive relevant issues.

### Atomic concurrent claims

Support agents can claim unassigned tickets directly from the detail view:
- Claim operations execute an atomic conditional SQL query (`WHERE id = ? AND assigned_agent_id IS NULL`).
- When two agents attempt to claim the same ticket concurrently, MySQL row-level locking guarantees that exactly one transaction succeeds.
- The colliding request receives an HTTP 409 Conflict with the winner's name (`"This ticket was already taken by {name}."`) and the latest ticket data.

### Assignment endpoints

- `PUT /api/v1/tickets/{ticket_number}/assignment`: Administrators assign or unassign a ticket.
The payload requires `agent_id` (agent ID or `null`) and the ticket's `lock_version` for optimistic concurrency control.
- `POST /api/v1/tickets/{ticket_number}/claim`: Available support agents or administrators claim an unassigned ticket.
- `POST /api/v1/tickets/{ticket_number}/release`: The current assignee releases their in-progress ticket back to the unassigned pool.
- `GET /api/v1/admin/agents`: Administrators view all agents, their availability, maximum ticket caps, handled categories, and current active ticket counts.

### Retry command and schedule

Tickets created when no eligible agent is available remain unassigned:
- The Artisan command `tickets:assign-unassigned` scans for unassigned active tickets that have never been assigned (`first_assigned_at IS NULL`).
- The command runs on a scheduled task every 5 minutes without overlapping (`->everyFiveMinutes()->withoutOverlapping()`).
- Tickets intentionally released by an agent preserve their `first_assigned_at` timestamp and are skipped by the retry command, remaining available for manual claims.

### Concurrency test suite

The claim race suite tests concurrent execution against MySQL row-level locking:

```bash
docker compose exec app php artisan test --testsuite=Concurrency
```

## Conversation and attachments

Customers and support staff talk through tickets with public replies.
Staff members can also record internal notes that customers never see.
Both tickets and messages accept file attachments.

### Permissions and visibility

- **Writing replies**: Customers can reply on their own open or resolved tickets.
Support agents and administrators can reply on any ticket they can view, provided the ticket is not closed.
Closed tickets accept no new messages from any role.
- **Internal notes**: Support agents and administrators can post internal notes on any non-closed ticket.
Customers receive an HTTP 403 Forbidden if they attempt to post an internal note.
- **Reading messages**: The conversation thread returns messages ordered newest first, paginated at 30 messages per page.
The database query scope `visibleTo` automatically excludes internal notes when evaluated for customers.
- **Activity timeline**: Activity logging records `replied` and `internal_note_added` events.
The activity endpoint automatically filters out internal note entries for customers.

### Allowed file types and content validation

The system accepts attachments with the following extensions: `pdf`, `png`, `jpg`, `jpeg`, `gif`, `webp`, `txt`, `csv`, `log`, `docx`, `xlsx`, `zip`.
The system validates file content using PHP's `finfo` byte inspection.
The detected MIME type must strictly match the declared file extension.
Renaming an executable or script to an image extension is rejected with HTTP 422 Unprocessable Content.
Each file must not exceed 10 MB in size.
Each upload allows up to 5 files per request.
When any single attachment fails validation, the entire request is rejected and nothing is stored.

### Private storage and downloads

Uploaded files are stored on a private disk outside the public web root.
Each stored file receives a generated UUID filename, preventing path traversal attacks and collisions.
The database records the cleaned original filename, MIME type, byte size, and SHA-256 hash.
Downloads are served through `GET /api/v1/attachments/{id}/download`.
The endpoint verifies the caller's authorization through `AttachmentPolicy`.
Responses deliver the raw file stream with `Content-Disposition: attachment; filename="{original_name}"`.
Security headers include `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-cache, no-store, must-revalidate`.

### Docker upload limits

Docker configurations accommodate multi-file uploads and CSV imports:
- `docker/php/php.ini`: `upload_max_filesize = 51M`, `post_max_size = 60M`, `max_file_uploads = 10`.
- `docker/nginx/default.conf`: `client_max_body_size 60M`.
Requests exceeding `post_max_size` answer HTTP 413 Payload Too Large with a JSON error message.

## Ticket Status Workflow

Ticket status transitions follow a state machine.
Only authorized roles can perform specific transitions.
Every status change requires the current `lock_version` for optimistic locking.

| Current Status | Target Status | Allowed Roles | Notes |
| -------------- | ------------- | ------------- | ----- |
| `open` | `in_progress` | Staff (Admin, Agent) | Begins agent work on the ticket |
| `open` | `waiting_for_customer` | Staff (Admin, Agent) | Pauses work while waiting for user input |
| `open` | `resolved` | Staff (Admin, Agent) | Marks resolution and sets `resolved_at` |
| `open` | `closed` | Staff, Ticket Owner (Customer) | Closes ticket and sets `closed_at` |
| `in_progress` | `waiting_for_customer` | Staff (Admin, Agent) | Pauses work while waiting for user input |
| `in_progress` | `resolved` | Staff (Admin, Agent) | Marks resolution and sets `resolved_at` |
| `in_progress` | `closed` | Staff, Ticket Owner (Customer) | Closes ticket and sets `closed_at` |
| `waiting_for_customer` | `in_progress` | Staff (Admin, Agent) | Resumes active agent work |
| `waiting_for_customer` | `resolved` | Staff (Admin, Agent) | Marks resolution and sets `resolved_at` |
| `waiting_for_customer` | `closed` | Staff, Ticket Owner (Customer) | Closes ticket and sets `closed_at` |
| `resolved` | `open` | Ticket Owner (Customer) | Reopens resolved ticket and clears `resolved_at` |
| `resolved` | `in_progress` | Staff (Admin, Agent) | Reopens resolved ticket and clears `resolved_at` |
| `resolved` | `closed` | Staff, Ticket Owner (Customer) | Confirms closure and sets `closed_at` |
| `closed` | *(none)* | *(none)* | Closed tickets are final |

## Ticket Numbering Format

Every ticket receives a unique sequential ticket number.
The format is `{PREFIX}-{YEAR}-{SEQUENCE}`, for example `TKT-2026-000001`.
The sequence restarts at 1 each year in the configured timezone.
Numbers grow naturally past 6 digits without truncation.

## Notifications and escalation

TechZu SLA delivers transactional email notifications and automatic breach escalations.

### Event notifications

Notifications are dispatched as domain events after transaction commit (`ShouldDispatchAfterCommit`), ensuring an event is only dispatched once database changes persist.
Each notification implements `ShouldQueue` and is sent asynchronously via the `queue` service.
Local emails are captured by Mailpit and viewable at http://localhost:8025.

| Event | Recipients | Subject format |
| ----- | ---------- | -------------- |
| Ticket created | Customer | `[{ticket_number}] We received your request` |
| Ticket needs an agent | All active Admins | `[{ticket_number}] New ticket needs an agent` |
| Ticket assigned | Assigned agent | `[{ticket_number}] Ticket assigned to you` |
| Customer replied | Assigned agent (or all active Admins if unassigned) | `[{ticket_number}] Customer replied` |
| Staff replied | Customer | `[{ticket_number}] New reply from support` |
| Internal note added | Assigned agent | `[{ticket_number}] New internal note` |
| Ticket resolved | Customer | `[{ticket_number}] Your ticket was resolved` |
| SLA deadline approaching | Assigned agent | `[{ticket_number}] SLA deadline approaching` |
| SLA escalated | Former assigned agent and all active Admins | `[{ticket_number}] Escalated: SLA deadline passed` |

Recipient resolution rules:
- **Self-action suppression:** Users never receive notifications for actions they perform themselves (the customer ticket creation confirmation is the sole exception).
- **Deactivated users:** Deactivated users are excluded from all notification dispatches.
- **Deduplication:** Multiple recipient criteria targeting the same user result in a single email.

### Scheduled SLA check and escalation

The `tickets:check-sla` command runs every minute via the task scheduler (`scheduler` container).
You can also run it on demand:

```bash
docker compose exec app php artisan tickets:check-sla
```

Key operational characteristics:
- **Idempotency through conditional updates:** Warnings atomically update `sla_warning_notified_at` where null.
Breaches atomically update `sla_breached_at` and `escalated_at` where `sla_breached_at` is null.
Running the command repeatedly within the same minute causes no duplicate notifications or duplicate activities.
- **Unassign on breach:** When an active ticket breaches its SLA deadline, the system automatically unassigns the ticket, sets `escalated_at`, logs `unassigned` and `escalated` activities, and notifies the former assignee and all active Admins.
Unassigning the ticket returns it to the pool so other agents or admins can urgently triage and pick it up.
- **Timezone presentation:** Deadlines in email notifications are formatted in the operational business timezone (`Asia/Dhaka`, configured via `TICKETS_TIMEZONE`).
- **Overlapping lock recovery:** The scheduler prevents overlapping runs via a cache lock.
If a scheduler process dies abruptly and the lock remains stuck, clear the lock with:

```bash
docker compose exec app php artisan schedule:clear-cache
```

- **Notification opt-out for future imports:** All ticket notification logic routes through `App\Listeners\SendTicketNotifications`, providing a single seam to disable notifications during batch data migrations or imports.

## CSV import

Administrators can import historical tickets in bulk through the web UI at `/admin/imports` or the REST API.
The import pipeline processes files up to 50 MB using constant memory streaming, background chunk workers, idempotent retries, and silent SLA calculation.

### Columns and formats

Files must be comma-separated values (CSV) encoded in UTF-8.
Optional UTF-8 byte-order marks (BOM) are stripped automatically.
Header names are case-insensitive and can appear in any order.
The template provides the following 10 columns:

- `customer_email`: Required customer email address matching an existing user with role `customer`.
Both active and deactivated customers are accepted so historical tickets retain accurate authorship.
- `category_name`: Required category name matching an existing active category.
- `priority`: Required case-insensitive ticket priority (`low`, `medium`, `high`, `critical`).
- `subject`: Required issue summary between 1 and 200 characters.
- `description`: Required issue description up to 65,535 characters.
Multi-line quoted text with embedded line breaks is fully supported.
- `status`: Optional ticket status (`open`, `in_progress`, `waiting_for_customer`, `resolved`, `closed`).
Defaults to `open` when omitted or blank.
- `created_at`: Optional creation timestamp formatted as ISO 8601 or `YYYY-MM-DD HH:MM:SS`.
Times lacking timezone offsets are interpreted in the business timezone (`TICKETS_TIMEZONE`, default `Asia/Dhaka`).
Defaults to the import timestamp when omitted or blank.
Timestamps in the future are rejected.
- `resolved_at`: Timestamp required when status is `resolved` or `closed`.
Forbidden on active ticket statuses.
Must be at or after `created_at` and cannot be in the future.
- `closed_at`: Timestamp required when status is `closed`.
Forbidden on non-closed ticket statuses.
Must be at or after `resolved_at` and cannot be in the future.
- `external_reference`: Required identifier up to 64 characters from the origin system.

### Row rules, errors, and duplicate handling

Invalid rows are rejected and reported with descriptive reasons:
- Non-CSV files, empty uploads, or files exceeding 50 MB are rejected immediately with HTTP 422.
- Missing required header columns return an immediate 422 error detailing the missing column name.
- Invalid UTF-8 sequences trigger a file rejection.
- Unknown customer emails report `Unknown customer email {email}`.
- Unknown categories report `Unknown category {category}`.
- Invalid priorities report `Invalid priority {priority}`.
- Invalid statuses report `Invalid status {status}`.
- Inconsistent timestamps report specific mismatches, including `Resolved at is required for resolved tickets` and `Resolved at cannot be earlier than created at`.
- In-file duplicate references report `Duplicate of line {earlier_line} in this file.`.
- Database duplicate references report `Reference {reference} already exists in the database.`.
- Re-importing a previously imported file flags all previously imported rows as duplicates and creates zero tickets.

Administrators can download the error report via `GET /api/v1/admin/imports/{id}/errors.csv`.
The report streams a CSV file with columns `line`, `external_reference`, and `reasons` for failed and duplicate rows in original file order.

### Architecture: scan pass and chunk jobs

The import architecture splits execution into two coordinated phases:
1. **Scan pass (`ScanTicketImport`)**:
A single streaming pass reads the uploaded file row by row with `fgetcsv`.
It verifies headers, indexes byte offsets every 500 rows, maps external references to detect in-file duplicates, and records chunk metadata in `ticket_import_chunks`.
Total rows and detected duplicate counts are persisted upon scan completion.
2. **Chunk jobs (`ProcessTicketImportChunk`)**:
The scan job dispatches a `Bus::batch` containing one job per 500-row chunk.
Queue workers seek directly to chunk byte offsets and process rows independently.
Each chunk caches business schedules and SLA policies in memory, avoiding repeated policy queries.
Ticket sequence numbers are allocated in batch blocks using `TicketNumberGenerator::nextBlock($count)`.
Tickets, SLA properties, and timeline activities commit within a single database transaction per chunk.

Retries remain idempotent:
- Each chunk checks its `ticket_import_chunks` record.
- A chunk marked `completed` returns immediately, preventing duplicate rows or repeated count increments on worker retries.
- Failed chunk jobs record row-level error entries and mark the parent import status as `failed` with "Some rows could not be processed.".
- Clean chunks in the same batch continue processing normally.

Memory consumption remains constant regardless of row volume:
- Benchmarking 100,000 valid rows imported completely in 14.14 seconds.
- Peak memory usage during the 100,000-row run increased by only 22.4 MB, well below the 64 MB budget.

### Quiet import and silent SLA assumptions

Imports operate under quiet conventions to avoid spamming customers or triggering false alerts on legacy data:
- **Zero notifications**: Transactional customer notifications and agent alerts are suppressed entirely during import execution.
- **Unassigned tickets**: Imported tickets are created without an assigned agent (`assigned_agent_id = NULL`).
- **Assignment retry exclusion**: The scheduled assignment retry command `tickets:assign-unassigned` filters out imported tickets via `whereNull('ticket_import_id')`.
Support agents can claim imported tickets manually, and administrators can assign them at any time.
- **Silent SLA calculation**: SLA resolution deadlines are calculated per row using the priority's current SLA policy.
For tickets whose deadlines passed prior to import, `sla_warning_notified_at` and `sla_breached_at` are stamped with the import time, while `escalated_at` remains NULL.
This silences notifications and escalations for historic breaches while correctly displaying breached status on dashboards and ticket views.
Future deadlines trigger normal warning emails and escalations when `tickets:check-sla` runs.
Historical resolved tickets evaluate their resolution against `resolved_at` and record `met` status when resolved within deadline.

### Remote object storage and worker hosts

Chunk jobs read directly from the CSV file using byte offsets (`fseek`).
POSIX byte seeking requires a seekable local filesystem.
Remote object storage providers like AWS S3 or Cloudflare R2 do not provide native byte seeking.
When running across multiple queue worker hosts with remote object storage, workers must stream or download the import file to a shared seekable disk or shared local volume.

### Sample generator command

Generate realistic sample CSV files for testing with the Artisan generator:

```bash
docker compose exec app php artisan tickets:make-sample-csv --rows=100000 --invalid=1 --duplicates=1
```

Options:
- `--rows`: Number of data rows to generate (default: 1000).
- `--path`: Custom destination file path (default: `storage/app/private/samples/tickets-{rows}.csv`).
- `--invalid`: Percentage of invalid rows to inject (e.g. invalid emails or priorities).
- `--duplicates`: Percentage of duplicate external references to inject.

Monitor queue execution in real time:

```bash
docker compose logs -f queue
```

## Dashboard

The home page displays an operational dashboard tailored to each user's authenticated role.

### Role-specific visibility

- **Administrators**: See team counts (`open`, `unassigned`, `critical`, `sla_within`, `sla_approaching`, `sla_breached`), resolution throughput (`resolved_today`, `resolved_last_7_days`), an interactive priority distribution bar, the `Workload by agent` chart, and the full `Tickets by agent` workload table.
Admins have no `My queue` section and the API sends them no `my_queue`, because their own tickets appear in their row of the workload table.
The workload table lists all active support agents as well as any inactive agents holding open tickets, each with `open`, `sla_within`, `sla_approaching` and `sla_breached` counts.
Each metric tile and table cell links directly to `/tickets` filtered by the corresponding criteria.
- **Support Agents**: See the shared team operational counts, resolution throughput, priority distribution bar, and personal queue metrics (`my_queue`: `open`, `sla_approaching`, `sla_breached`).
The agent workload table is omitted for non-admin staff.
- **Customers**: See a simplified view focused entirely on their own tickets, including open, waiting for customer reply, and closed counts, followed by their 5 most recently updated tickets.
All team metrics, agent assignments, and SLA deadlines are omitted from customer responses.

### Charts

Admins and agents see the charts below the tiles (and below "My queue" for agents), drawn with [Recharts](https://recharts.org) and loaded in their own chunk, so customers never download them.

| Chart | Type | Who | Data |
| ----- | ---- | --- | ---- |
| Created vs resolved | Line | Admin, Agent | `daily.created` and `daily.resolved` for the last 14 days |
| Daily SLA compliance | Stacked bars | Admin, Agent | `daily.sla_met` and `daily.sla_missed` for the last 14 days |
| SLA health | Donut | Admin, Agent | `team.sla_within`, `sla_approaching` and `sla_breached`, with the active total in the center |
| Workload by agent | Horizontal stacked bars | Admin | Each `by_agent` row's `sla_within`, `sla_approaching` and `sla_breached` |

Every chart has a text legend and a hover tooltip, and the two daily charts have a "Show table" button with the same numbers.
In "SLA health", every part above 0 takes at least 12 degrees of the ring, so a few within or approaching tickets stay visible next to many breached ones; the legend keeps the exact counts.
The SLA health legend items link to `/tickets?sla_status=...`, and selecting a workload segment opens `/tickets?assigned={id}&sla_status=...`.
Chart colors come from the `--color-chart-*` and `--color-sla-*` tokens in `frontend/src/index.css`, checked for color-blind separation.

### Daily series

Staff responses include `daily`: the last 14 calendar days in the business timezone (`TICKETS_TIMEZONE`, default `Asia/Dhaka`), oldest first and today last, with days that have no tickets filled with zeros.

| Field | Meaning |
| ----- | ------- |
| `date` | The day, `YYYY-MM-DD` in the business timezone |
| `created` | Tickets created that day |
| `resolved` | Tickets whose resolution time falls on that day; a reopened ticket no longer counts |
| `sla_met` | Tickets now resolved or closed, finished that day at or before their SLA due time |
| `sla_missed` | Tickets now resolved or closed, finished that day after their SLA due time |

A ticket's finish time is its resolution time, or its closing time when it was closed without a resolution.
So a ticket closed without a resolution counts in `sla_met` or `sla_missed` and not in `resolved`.
`team.resolved_today` is the last day's `resolved`, and `team.resolved_last_7_days` is the sum of the last 7 days' `resolved`.
`sla_within`, `sla_approaching` and `sla_breached` always add up to `open`, in `team` and in each `by_agent` row.

### Caching and freshness

The dashboard layer wraps data computation in `App\Dashboard\CachedDashboard` using Laravel's cache store with a 60-second time-to-live:
- **Shared blocks**: Team operational metrics with the daily series (`dashboard:team:v2`) and agent workload data (`dashboard:by-agent:v2`) are cached globally across all staff members.
- **Personal blocks**: An agent's queue metrics (`dashboard:my-queue:{id}`) and customer ticket summaries (`dashboard:customer:{id}`) are cached per user ID.
- **Timestamp tracking**: Responses include a `generated_at` timestamp reflecting the oldest cached block used to assemble the response.
- **Manual refresh**: Users can trigger the "Refresh" action on the dashboard to fetch the latest metrics on demand.

### Query optimization and indexes

The dashboard calculates metrics without hydrating Eloquent ticket models, executing constant query counts regardless of data volume:
- Single-pass conditional aggregation (`SUM(CASE ... THEN 1 ELSE 0 END)`) groups active tickets by priority and SLA status in one query.
- An administrator's dashboard runs 5 queries: team aggregation, daily created, daily finished, agent workload counts, and the agent list.
- An agent's dashboard runs 4 queries: team aggregation, daily created, daily finished, and the personal queue.
- Day buckets come from UTC day bounds computed in PHP and a `CASE` expression in SQL, so they are exact in any timezone without MySQL timezone tables.
- A customer's dashboard runs 2 queries: the summary counts and the 5 recent tickets.
- The daily created query uses `tickets_created_at_index`. The daily finished query is a `UNION ALL` of a `tickets_resolved_at_index` range and a `tickets_closed_at_index` range, because MySQL does not merge the two indexes for an `OR`.
- Composite indexes `tickets_status_priority_index`, `tickets_status_sla_due_at_index`, and `tickets_assigned_agent_id_status_index` ensure index coverage for active ticket filters.

## Environment variables

Key configuration settings in `backend/.env`:

- `FRONTEND_URL`: Allowed CORS origin and base URL for email links (default: `http://localhost:5173`).
- `SANCTUM_TOKEN_EXPIRATION`: Personal access token validity period in minutes (default: `10080` minutes / 7 days).
- `TICKET_NUMBER_PREFIX`: Prefix for ticket numbers (default: `TKT`).
- `TICKET_NUMBER_PADDING`: Zero-padding width for sequential ticket numbers (default: `6`).
- `TICKETS_TIMEZONE`: Timezone for determining creation year and sequence reset (default: `Asia/Dhaka`).

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

Run only the concurrency race suite:

```bash
docker compose exec app php artisan test --testsuite=Concurrency
```

Run the import benchmark suite:

```bash
docker compose exec app php artisan test --testsuite=Import
```

Skip large import benchmarks during local test runs:

```bash
docker compose exec app php artisan test --exclude-group=large-import
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

Reset and reseed the development database:

```bash
docker compose exec app php artisan migrate:fresh --seed
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
