Support panel
=================

Requirements
------------

This Web Project is compatible with Nette 3.2 and requires PHP 8.2.


Installation
------------

1. Clone this repository.
2. Install dependencies: composer install
3. Configure database connection in `app/config/common.neon`
4. mysql -u root -p -e "CREATE DATABASE support_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
5. mysql -u root -p support_app < db/schema.sql
6. from project root run: php db/seed.php --nette


Architecture overview
----------------------

Standard Nette layered structure:

```
app/
├── Presentation/        Presenters + Latte templates (Home, Auth, Api, Error)
├── Repository/          Nette Database Explorer queries — no business logic
├── Service/             Validation + orchestration between repositories
├── DTO/                 Plain data objects returned by services (incl. Filter/, PagedResult)
├── Enum/                Whitelisted values (ActivityType, CustomerSort, SortDirection)
├── Security/             OperatorAuthenticator (Nette\Security\Authenticator)
└── Core/                RouterFactory
```

- **Frontend** is a single `www/js/app.js` using `fetch()` against the `Api` presenter — no
  page reloads for search, pagination, or comments. Templates contain only static markup;
  all dynamic rendering happens client-side and is escaped via a local `esc()` helper.
- **Auth** uses Nette's built-in `Nette\Security\User` / `SimpleIdentity`. Passwords are
  hashed with `Nette\Security\Passwords` (bcrypt), with automatic rehash on cost upgrade.
- **Filtering/sorting** on customers and activities goes through PHP enums
  (`CustomerSort`, `SortDirection`, `ActivityType`) so only whitelisted values ever reach
  the SQL `ORDER BY` / `WHERE` clauses.
- **Routes**: `/sign/in`, `/sign/out` for auth; `/api/customers`, `/api/customers/<id>/activities`,
  `/api/activities/<id>/comments`, `/api/activities/<id>/comments/add` for the JSON API;
  everything else falls back to `Home:default`.


Assumptions
-----------

- Single operator role model for now (`operators` table has a `role` column, but no
  permission checks are implemented yet — every logged-in operator can do everything).
- The API is only ever called from the same-origin `app.js`, not by external clients —
  this informed some of the simplifications below (e.g. no API tokens, session-based auth).
- Comment authorship is always derived from the logged-in operator's session
  (`$this->getUser()->getId()`), never trusted from client input.


Known limitations
------------------

- **CSRF**: the login form is protected automatically by `Nette\Application\UI\Form`,
  but `POST /api/activities/<id>/comments/add` is a plain `fetch()` call with no CSRF
  token check. A malicious page could submit a comment on behalf of a logged-in operator.
- **Input validation gaps**: `addComment` doesn't verify the activity exists before
  inserting (relies on the FK constraint, which surfaces as a generic error instead of a
  clean 404), and comment body length is unbounded.
- **No role-based access control**: the `role` column on `operators` is not enforced
  anywhere in the application layer yet.
- **XSS**: client-side rendering escapes all server-provided strings via `esc()`, except
  one `<option value="...">` attribute that currently relies on the value being
  enum-constrained server-side rather than being escaped directly — fragile if that
  assumption changes later.
- No automated tests yet.


Potential future improvements
------------------------------

- Add `X-Requested-With` header check (or a dedicated CSRF token) on all mutating API
  endpoints, and set `cookieSameSite: Lax` in session config.
- Validate `activityId` existence in `CommentService::addComment()` before insert, and
  cap comment body length.
- Enforce `operators.role` via a simple authorization check in `BasePresenter` or a
  dedicated `Authorizator`.
- Escape the remaining unescaped HTML attribute in `app.js` rather than relying on
  server-side enum constraints alone.
- Add PHPUnit/Nette Tester coverage for repositories and services.
- Add pagination caching or debounced search if the customer list grows significantly
  beyond the current seed size.