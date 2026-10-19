# Ahmed LMS API

Laravel 13 API backend for the Ahmed Programming Academy React LMS.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
mysql -u root -p -e "CREATE DATABASE ahmed_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed
php -c php.ini artisan serve
```

For convenience, the project also exposes:

```bash
composer run serve
```

This keeps the PHP upload limits high enough for lesson media uploads while using the local development server.

The API runs at `http://localhost:8000`. Set the frontend to API mode with:

```env
VITE_USE_MOCK_API=false
VITE_API_URL=http://localhost:8000/api
```

`php artisan db:seed` loads only the local development dataset: one student, one linked
parent, one administrator, three sample courses with lessons, and a Python assessment
and enrollment. Course durations match the lessons; ratings and enrollment counters
start at zero. Rerunning it preserves existing local edits and passwords.

Local accounts (all use `password123`):

- Student: `student@ahmed-lms.local`
- Parent: `parent@ahmed-lms.local`
- Administrator: `admin@ahmed-lms.local`

Additional administrator QA scenarios are optional:

```bash
php artisan db:seed --class=AdminTestingSeeder
```

This adds role-specific staff accounts, dashboard courses, payment-review scenarios,
support cases, incidents, notifications, and question-bank examples. Its staff emails
are `{admin,finance,reviewer,content,support}.dashboard@test.local`; the password is
`TestingPassword123!`. Each fixture is applied once and can be requested separately
through its own seeder class. All local and QA seeders reject production execution.
They do not remove records from an existing database.

## Laravel admin portal

The administrator UI is server-rendered by Laravel and does not depend on React or client-side admin API calls:

- Portal: `http://localhost:8000/admin`
- Login: `http://localhost:8000/admin/login`
- Views: `resources/views/admin`
- Web controllers: `app/Http/Controllers/Web/Admin`
- Routes: `routes/web.php`

Admin authentication uses Laravel sessions, secure cookies, CSRF-protected forms, role middleware, and server-side redirects and validation. The React application contains no administrator routes, components, or login flow; administrators access this portal directly.

The default local database configuration uses MySQL/MariaDB database `ahmed_lms`. Keep real production credentials in `.env`; never commit them.

## Implemented endpoints

Public:

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/courses`
- `GET /api/courses/{course}`
- `GET /api/certificates/verify/{publicId}`

Sanctum authenticated:

- `GET /api/auth/me`
- `POST /api/auth/logout`
- `POST /api/courses/{course}/enroll`
- `GET /api/me/learning`
- `POST /api/courses/{course}/lessons/{lesson}/complete`
- `GET /api/courses/{course}/assessment`
- `POST /api/courses/{course}/assessment/submit`
- `GET /api/courses/{course}/certificate`
- `GET /api/me/wishlist`
- `POST /api/me/wishlist/{course}` — toggles a published course
- `GET /api/me/notifications`
- `PATCH /api/me/notifications/{notification}/read`
- `POST /api/me/notifications/read-all`
- `GET /api/me/orders`
- `POST /api/payments/checkout` — student role only
- `POST /api/payments/confirm`
- `GET /api/parent/students` — parent role only
- `GET /api/parent/students/{student}` — linked parent only
- `GET /api/admin/overview` — admin role only
- `GET|POST /api/admin/courses` — list all states or create a draft
- `GET|PATCH /api/admin/courses/{course}`
- `POST /api/admin/courses/{course}/{publish|unpublish|archive|restore}`
- `POST|PATCH|DELETE /api/admin/courses/{course}/lessons[/{lesson}]`
- `POST /api/admin/courses/{course}/lessons/reorder`
- `GET|PUT /api/admin/courses/{course}/assessment`
- `POST /api/admin/courses/{course}/assessment/{publish|unpublish}`
- `POST|PATCH|DELETE /api/admin/courses/{course}/assessment/questions[/{question}]`
- `POST /api/admin/courses/{course}/assessment/questions/reorder`
- `GET /api/admin/courses/{course}/assessment/attempts`
- `GET /api/admin/users` — searchable student and parent directory
- `GET /api/admin/users/{user}` — learning, payment, and family summary
- `POST /api/admin/users/{user}/{activate|deactivate}`
- `POST /api/admin/parent-student-links`
- `DELETE /api/admin/parent-student-links/{parent}/{student}`
- `POST /api/admin/courses/{course}/lessons/{lesson}/media`
- `DELETE /api/admin/courses/{course}/lessons/{lesson}/media/{media}`
- `GET /api/media/{media}/access` — enrolled student or admin
- `GET /api/media/{media}/preview` — preview lessons only
- `GET /api/media/{media}/stream` — temporary signed URL only
- `GET /api/admin/audit-logs`

## Security boundaries

- Public registration accepts only `student` and `parent` roles.
- Paid courses cannot use the free-enrollment endpoint.
- Lesson completion requires an authenticated enrollment.
- Assessment answers are scored server-side; correct answers are not included in the question payload.
- Certificates are issued only after all lessons are complete and an assessment is passed.
- Public certificate verification exposes only credential information.
- Parent reports require the parent role and expose only explicitly linked students.
- Wishlists and notification read states are scoped to the authenticated owner.
- Unpublished courses cannot be added to a wishlist.
- Paid-course prices are loaded from the database and never trusted from browser input.
- Payment sessions use hashed opaque tokens and are scoped to their authenticated owner.
- Enrollment is created atomically only after server-side payment verification.
- Stripe callbacks are independently verified through `POST /api/payments/webhooks/stripe` using the signed raw payload.
- Public registration cannot assign the admin role; admin endpoints enforce role middleware.
- Course and lesson mutations create an actor-attributed audit record.
- Courses are archived instead of deleted, and published-course lessons cannot be deleted.
- Published assessments are immutable until explicitly unpublished.
- Assessment questions require matching bilingual answer sets and a valid correct-answer index.
- Admin assessment reports expose scores and student identity but not submitted answer payloads.
- Deactivated accounts have all API tokens revoked and cannot log in or use authenticated APIs.
- Administrators cannot deactivate themselves or change another administrator through account operations.
- Parent–student links validate both account roles and are unique per pair.
- Uploaded lesson media is stored on the private disk and raw storage paths are never returned.
- Paid lesson media requires enrollment before issuing a ten-minute signed URL.
- Public media access is limited to lessons explicitly marked as previews.
- Video and resource MIME types and file sizes are validated server-side.

Parent linking management, media storage, and administration remain upcoming backend phases.

## Payments

Local development uses the non-production `local` provider so the complete flow can be tested without charging a card. It is rejected outside the `local` and `testing` environments. Production should use:

```env
PAYMENT_PROVIDER=stripe
PAYMENT_CURRENCY=EGP
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Configure Stripe to send `checkout.session.completed` events to `/api/payments/webhooks/stripe`. The success redirect does not grant access by itself; the backend retrieves and verifies the Checkout Session or processes the signed webhook.

## Quality checks

```bash
vendor/bin/pint --test
php artisan test
php artisan route:list --path=api
```

## Production deployment

After migrations, set `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in the
production environment or secret manager, refresh cached configuration, and run:

```bash
php artisan production:setup
```

This command requires `APP_ENV=production`. It creates the first super administrator
and inserts missing subscription plans, notification templates, grades, academic-year
terms, and programming subject records. Existing passwords, prices, disabled records,
content, and subscription scopes are preserved. Platform settings retain their code
defaults until configured through admin; no demo courses, students, or payments are
created. The bootstrap password must be at least 12 characters and at most 72 bytes;
it is never printed. Existing accounts are never promoted or reactivated.

After verifying login, remove bootstrap credentials and refresh the configuration
cache. Repeat setup and release checks accept an existing active super administrator
without those credentials. Review initial subscription prices and academic dates
before opening registration. Concurrent setup runs are locked and database changes
commit together or roll back together.

Production-safe environment templates are provided in `.env.production.example`. Run
`php artisan ops:release-check` before releasing; it blocks unsafe environment, HTTPS, session,
queue, mail, payment, proxy trust, token-expiry, administrator, and backup configuration without
displaying secrets. Production HTML uses a restrictive Content Security Policy, and authenticated
responses are marked private and non-cacheable.

The complete staging and production procedure is documented in [`../DEPLOYMENT.md`](../DEPLOYMENT.md).
# ThanawyaLMSBackend
