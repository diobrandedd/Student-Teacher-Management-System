---
name: security-audit
description: >-
  Audits this Student-Teacher Management System for security vulnerabilities
  (SQL injection, XSS, CSRF, authn/authz, least privilege, account creation,
  session handling, file uploads, secrets, IDOR). Flags current issues, lists
  required fixes, and assigns a security rating from 1–40. Use when the user
  asks for a security audit, vulnerability review, least-privilege check,
  permission review, or to rate/harden app security.
---

# Security Audit (STMS)

Static security audit of this PHP/MySQL app. Read code and config; do **not**
write exploit PoCs, payloads, or attack steps. Report findings and fixes only.

## When to run

Apply on requests like: security audit, vuln review, least privilege, who can
create accounts, SQL injection check, permission matrix, security rating.

## Scope (always cover)

| Area | What to inspect |
|------|-----------------|
| Injection | Raw SQL string concat; `query()` with user input; dynamic `LIMIT`/`ORDER BY`; shell/exec |
| Authn | Login, lockout, password hashing/strength, temp passwords, `must_change_password`, setup bootstrap |
| Authz / least privilege | Every mutating page/action: `require_role` / `require_auth`; role vs capability mismatch |
| Account creation | Who can create admin/staff/registrar/student; role assignment; self-registration gaps |
| CSRF | State-changing POSTs call `verify_csrf()`; tokens in forms |
| XSS | Echo of user/DB data without `e()`; JS sinks; file/HTML serving |
| Session | Cookie flags, fixation, logout wipe, privilege in `$_SESSION` |
| IDOR / object access | IDs from GET/POST checked against ownership/role (grades, enrollment docs, profiles) |
| Uploads / files | Type/size checks; storage outside web root; download auth |
| Secrets / config | `.env` not committed; `display_errors`; default DB root; CSP/headers in `bootstrap.php` |

Primary surfaces: `public/index.php`, `app/*.php`, `app/bootstrap.php`, `database/schema.sql`, `.env.example`.

## Workflow

1. **Map entry points** — pages in `public/index.php` `$allowed` and each controller action.
2. **Map roles** — `admin`, `staff`, `registrar`, `student` (and any others). Note home pages via `home_page_for_user()`.
3. **Trace each sensitive action** — create/update/delete users, students, grades, enrollments, blocks, documents, settings.
4. **Check helpers** — prefer `db()->prepare`, `e()`, `verify_csrf()`, `require_role([...])`, `password_hash` / `password_verify`, `audit()`.
5. **Flag gaps** — missing checks, weak checks, trust of client role, overly broad roles.
6. **Score** — apply the 1–40 rubric below.
7. **Report** — use the output template exactly.

Do not invent vulns. Cite file paths and line ranges. Prefer concrete fix steps.

## Severity

| Level | Meaning |
|-------|---------|
| Critical | Unauth RCE/SQLi, auth bypass, anyone becomes admin |
| High | Privilege escalation, mass data leak, account takeover |
| Medium | CSRF on sensitive action, stored XSS, weak password policy hole |
| Low | Missing headers, info leak in errors, defense-in-depth gap |
| Info | Hardening suggestion; not currently exploitable |

## Rating (1–40)

Start at **40**. Subtract for each confirmed finding (use the highest applicable band per distinct issue; do not double-count the same root cause):

| Severity | Deduction each |
|----------|----------------|
| Critical | −8 to −10 |
| High | −5 to −7 |
| Medium | −2 to −4 |
| Low | −1 |
| Info | 0 (list only) |

Rules:

- Floor at **1**, ceiling at **40**.
- Multiple Critical findings can drive the score near 1.
- If no Critical/High and only minor Low/Info, stay in the **32–40** band.
- State the final score as `Security rating: N/40` with a one-line rationale.

Band guide (after deductions):

| Score | Verdict |
|-------|---------|
| 33–40 | Strong — residual risk low |
| 25–32 | Acceptable — fix Medium+ soon |
| 17–24 | Weak — High issues need priority work |
| 9–16 | Poor — unsafe for real user data |
| 1–8 | Critical — do not deploy / treat as breach-prone |

## Output template

```markdown
# Security audit

**Security rating: N/40** — <one-line rationale>

## Summary
- Critical: n | High: n | Medium: n | Low: n | Info: n
- Top risks: <up to 3 bullets>

## Findings

### [SEVERITY] Short title
- **Where:** `path/to/file.php` (lines X–Y) — <symbol or page action>
- **Issue:** <what is wrong>
- **Impact:** <who can abuse it / what they get>
- **Change needed:**
  1. <concrete fix>
  2. <concrete fix>
- **Deduction:** −N

(repeat per finding)

## Least-privilege matrix
| Action | Allowed roles (current) | Recommended | Gap? |
|--------|-------------------------|-------------|------|
| Create admin/staff users | … | … | … |
| Create students / reset passwords | … | … | … |
| Approve enrollments | … | … | … |
| Enter / submit grades | … | … | … |
| View enrollment documents | … | … | … |
| Change system settings | … | … | … |

(add rows for other sensitive actions found)

## Required changes (priority order)
1. **P0** — …
2. **P1** — …
3. **P2** — …

## Score breakdown
- Starting: 40
- Deductions: …
- **Final: N/40**
```

## Project conventions (expected safe patterns)

- SQL: `db()->prepare(...)` + bound params; `ATTR_EMULATE_PREPARES => false`
- Output: `e($value)` in views
- Mutations: `verify_csrf()` before processing POST
- Gates: `require_auth()` / `require_role(['admin', ...])` at top of page/action
- Passwords: `password_hash` / `password_verify`; `password_is_strong()`
- Audit trail: `audit($action, $entityType, $entityId, $details)`
- Headers/CSP: set in `app/bootstrap.php`

Violations of the above are findings unless a documented, compensating control exists.

## Additional resources

- Deeper checklists and common anti-patterns: [reference.md](reference.md)
