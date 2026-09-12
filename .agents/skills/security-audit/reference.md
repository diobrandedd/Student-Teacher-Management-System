# Security audit reference (STMS)

Read this when SKILL.md is not enough detail for a category.

## SQL injection checklist

Flag when user-controlled data reaches SQL without binding:

- String concatenation into `query()` / `prepare()` SQL text
- Interpolating `$_GET` / `$_POST` / session fields into SQL
- Dynamic `ORDER BY` / `LIMIT` / table/column names from request (whitelist only)
- `OFFSET $var` built by concatenating unvalidated ints (cast/validate first)
- LIKE patterns: bind values; do not embed raw `%user%` into SQL text unsafely

Safe baseline: `db()->prepare('... ? ...')` + `execute([...])`.

## Authorization / least privilege

For each page in `$allowed` and each POST handler:

1. Is there `require_auth()` or `require_role([...])`?
2. Is the role list minimal for that action?
3. Can a lower role reach a higher-privilege code path via another `page=` value?
4. After authz, do object-level checks exist (student can only see own grades/docs)?

Roles in this app (typical):

| Role | Intended power |
|------|----------------|
| admin | Full management |
| registrar | Enrollment pipeline |
| staff | Teaching / assigned blocks / scores |
| student | Own portal only |

Flag:

- Staff/registrar creating admins or assigning `admin` role
- Students hitting staff/admin pages
- Missing role gate on create/update/delete
- Trusting a hidden form field for `role`

## Account creation & credentials

Inspect setup, user forms, student create/reset:

- First-admin `setup` only when user count is 0; locked afterward
- Who may set `role` on insert/update
- Temporary passwords: forced change (`must_change_password`); not reused
- Lockout: `failed_attempts` / `locked_until` on both `users` and `students`
- Password policy: length + complexity (`password_is_strong` / setup rules)
- No plaintext passwords in flash messages, logs, or HTML (temp password disclosure is a finding if shown to wrong party or logged)

## CSRF

Every state-changing POST should:

1. Call `verify_csrf()` early
2. Include the CSRF token field in the form

Flag GET-based deletes/updates and POSTs that skip verification.

## XSS

- All dynamic HTML text through `e()`
- Attributes and URLs carefully encoded
- Avoid unescaped content in inline scripts
- Uploaded files never served as executable HTML/JS from a trusted origin without forced download / safe Content-Type

## Session & cookies

Expect (from `bootstrap.php`):

- Custom session name
- `httponly`, `samesite`, `secure` when HTTPS
- Logout clears session + cookie
- `must_change_password` enforced before other pages

Flag storing secrets in session beyond needed identity/role fields.

## IDOR & documents

Enrollment documents, student profiles, grade submissions:

- Authorize before `readfile` / path join
- Path must not allow `../` escape
- Storage under `storage/` (not public web) is expected

## Secrets & environment

- `.env` gitignored; only `.env.example` committed (no real passwords)
- Production: `APP_ENV` not `development` (display_errors off)
- DB user should not need to be MySQL `root` in production notes (Info/Low if docs encourage root)

## Headers

Confirm still present in `bootstrap.php`: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy`, `Cache-Control: no-store`.

Missing or weakened CSP → Low/Medium depending on XSS residual risk.

## Scoring examples

**Example A** — One High (staff can create admin), two Medium (CSRF on one form, reflected XSS), one Low:

- 40 − 6 − 3 − 3 − 1 = **27/40**

**Example B** — Unauthenticated SQLi Critical + auth bypass Critical:

- 40 − 10 − 10 = **20/40** (or lower if more Criticals)

**Example C** — Only Info hardening items:

- **38–40/40**
