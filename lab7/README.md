# Lab 7 – Peer Tutoring Hub: User Management, Authentication & Authorization

Extends the Lab 6 three-tier PHP/PDO/SQLite app with use cases **UM-01 to UM-10** from the
User Management Design Document. Lab 6 pages, the topic-JSON convention and the
two original tables are preserved; a `users` table is added and linked to `students`.

**Classroom application. Use test data only. Not for public deployment.**

---

## 1. What Lab 7 adds to Lab 6

| Area | Lab 6 | Lab 7 |
|---|---|---|
| Tables | students, tutoring_requests | + `users`; `students.user_id` links a student profile to its account |
| Identity | Name/email typed on the request form | Taken from the logged-in account (posted name/email ignored) |
| Access | Anyone could view/edit any request | Login required; Students see/edit only their own; Admin sees all (read only) |
| Forms | No CSRF | CSRF token on every POST form |
| Passwords | — | `password_hash()` / `password_verify()` in the service layer |
| Sessions | — | Strict mode, HttpOnly + SameSite cookie, ID regenerated at login, idle (30 min) and absolute (8 h) timeouts |

Roles in this lab: **Student** and **Administrator**. Tutors can register, log in and manage their
profile; tutor features come in a later lab.

## 2. Architecture (three tiers)

```
Browser
  -> php/*.php                 presentation + thin controllers (HTML, CSRF check, requireLogin/requireRole)
  -> includes/auth.php         session, login/logout, CSRF, access gates
  -> services/*.php            business rules, validation, password hashing, transactions
  -> repositories/*PDO.php     SQL only (prepared statements)
  -> config/db.php             PDO + PRAGMA foreign_keys = ON
  -> database/tutoring.db      SQLite
```

## 3. Use case → code traceability

| UC | Wireframe | Page(s) | Service function (`services/userService.php`) | Repository | Tests |
|---|---|---|---|---|---|
| UM-01 Create User Account | WF-01 | `register.php` → `process-register.php` | `registerUser()` | `insertUser`, `insertStudentForUser` / `linkStudentToUser` | user-management UM-01 |
| UM-02 View Own Account | WF-02 | `profile.php` | `getOwnProfile()` | `findUserById` | UM-02 |
| UM-03 View & Search Accounts | WF-03 | `manage-users.php`, `user-details.php` | `searchAccounts()`, `getAccountForAdmin()` | `searchUsers`, `countRequestsForUser` | UM-03 |
| UM-04 Update Own Account | WF-02 | `edit-profile.php` | `updateOwnProfile()` | `updateUserProfile`, `updateStudentForUser` | UM-04 |
| UM-05 Change Password | WF-04 | `change-password.php` | `changePassword()` | `updateUserPasswordHash` | UM-05 |
| UM-06 Update User Role | WF-05 | `update-role.php` | `changeUserRole()` | `updateUserRole` | UM-06 |
| UM-07 Activate/Deactivate | WF-06 | `account-status.php` | `setAccountStatus()` | `updateUserStatus` | UM-07 |
| UM-08 Delete User Account | WF-07 | `delete-user.php` | `deleteUserAccount()` | `countRequestsForUser`, `deleteStudentForUser`, `deleteUserRow` | UM-08 |
| UM-09 Log In | WF-08 | `login.php` → `process-login.php` | `authenticateUser()` + `loginUser()` in auth.php | `findUserByEmail` | UM-09 |
| UM-10 Log Out | Nav | `logout.php` | `logoutUser()` in auth.php | — | UM-10 |

Request pages (UC01/UC02 from Lab 6) now use `submitRequestForUser`, `listRequestsForUser`,
`canViewRequest`, `canModifyRequest`, `reviseRequestForUser`, `cancelRequestForUser`
in `services/requestService.php`.

## 4. Business rules implemented

Approved: Student/Tutor self-registration, Active immediately; public registration can never
create an Administrator (only `database/create-admin.php` from the command line can).

Proposed rules, implemented as written: unique email (case-insensitive); only administrators change
other users' role/status; inactive users cannot log in, and an existing session ends on the next
request after deactivation; the last active administrator cannot be demoted, deactivated or deleted.

**Assumptions for open decisions. Confirm these with your instructor:**
- **Deletion policy (UM-08):** deletion is **blocked** whenever the account has related tutoring
  requests; dependent records are never silently removed. The admin is pointed to Deactivate instead.
  Accounts with no requests can be deleted.
- **Password rule:** 12–72 characters (Lab 7 states at least 12; bcrypt only uses the first 72 bytes).
- **Self-administration:** an administrator cannot change their own role or status, or delete themselves.
- **USER ↔ TUTORING_REQUEST:** reconciled through the existing `student_id` FK via `students.user_id`,
  so `tutoring_requests` is unchanged. Registering with the email of an existing Lab 6 student row
  (e.g. the seed `maria.lopez@example.com`) claims that row instead of duplicating it.
- **Admin and requests:** administrators can view all requests but not edit or cancel them.

## 5. Complete workflow (Lab 7 Parts 2–10)

### Part 2: Database
```bash
cd lab7/database
sqlite3 tutoring.db < setup-database.sql
sqlite3 tutoring.db < upgrade-to-user-management.sql
sqlite3 tutoring.db
.tables          -- expect: students  tutoring_requests  users
.quit
```
Run the upgrade **once**. Running it again fails on `ALTER TABLE` because the column already exists.
To start over: `rm tutoring.db` and run both scripts again.

### Part 3: Administrator account
```bash
php create-admin.php "Test Administrator" admin@example.com "TestPassword123"
```
Expected:
```
Administrator account created.
  user_id: 1
  name:    Test Administrator
  email:   admin@example.com
  role:    Administrator
  status:  Active
```

### Part 4: Run
```bash
cd ..
php -S 0.0.0.0:8000 php/router.php     # recommended
# php -S 0.0.0.0:8000                  # the lab's command also works (see security note)
```
Open forwarded port 8000. Any protected page (for example `/`) redirects to **Log In** with the message “Please log in to continue.”

> **Security note:** without `php/router.php`, the built-in server serves any file, including
> `database/tutoring.db`. The router returns 404 for `database/`, `config/`, `includes/`,
> `repositories/`, `services/`, `tests/` and `.db/.sql/.md` files. `create-admin.php` refuses to run from the web.

### Part 5: Student workflow
Create Account → Log In → My Profile / Edit Profile → Request a Session → Edit (while Open) → My Requests → Log Out.

### Part 6: Administrator workflow
Log in as admin → Manage Users (search/filter, Manage) → All Requests → Log Out.

### Part 7: Request traces

**Registration:**
`php/register.php` (form, CSRF token from `csrfField()` in `includes/auth.php`)
→ POST `php/process-register.php` (`requirePost()`, `verifyCsrfOrFail()`: registration controller)
→ `registerUser()` in `services/userService.php` (validation from `includes/validation.php`; **`password_hash()` here**; transaction)
→ `insertUser()` in `repositories/userPDO.php` + `ensureStudentForUser()` → `insertStudentForUser()` in `repositories/studentPDO.php`
→ tables **users** and **students** (students only for the Student role).

**Login:**
`php/login.php` → POST `php/process-login.php` (CSRF check: login controller)
→ `authenticateUser()` in `services/userService.php` (**`password_verify()` here** + Active status check)
→ `findUserByEmail()` in `repositories/userPDO.php` → **users**
→ `loginUser()` in `includes/auth.php`: `session_regenerate_id(true)` (prevents session fixation), stores `user_id`, `role`, timestamps, new CSRF token.
Role is decided by `currentUser()`, which re-reads `users.role` from the database on every request; `requireRole()` checks it.

### Part 8: Tests (run from the lab7 root)
```bash
php tests/validation-tests.php
php tests/user-management-tests.php
php tests/integration-tests.php
```
All three use an in-memory SQLite database built from both SQL scripts and never touch `tutoring.db`.
Each prints `PASS:`/`FAIL:` lines, a results summary, and exits non-zero on any failure.

### Part 10: LEFT JOIN query
```sql
SELECT u.user_id, u.full_name, u.email, u.role, u.status,
       s.student_id, s.student_name, s.student_email
FROM users AS u LEFT JOIN students AS s ON u.user_id = s.user_id
ORDER BY u.user_id;
```
Every user is listed. Administrators and Tutors have no `students` row, so their `s.*` columns are NULL.

## 6. Security controls checklist
- Passwords are stored only as `password_hash()` output and checked with `password_verify()`. A dummy hash is verified for unknown emails so response timing does not reveal which emails are registered. Old hashes are upgraded on login when needed.
- The same generic “Invalid email or password.” message is shown for an unknown email and for a wrong password.
- Sessions: `use_strict_mode`, cookies only, HttpOnly, SameSite=Lax, Secure when served over HTTPS. The session ID is regenerated at login, every 15 minutes and after a password change. Logout destroys the session and its cookie.
- Every POST checks a CSRF token with `hash_equals`. Logout is POST-only, so another site cannot log a user out.
- Authorization is enforced on the server in pages (`requireLogin`/`requireRole`) and in services (`assertAdministrator`, ownership checks). Other students' requests return 404.
- Prepared statements everywhere; LIKE wildcards in the admin search are escaped. All output is escaped with `htmlspecialchars`.
- Pages send `no-store` cache headers, so the browser Back button does not show protected pages after logout.

**Known limitations:** no login rate limiting or lockout; no password reset by email; no audit log.
The deletion policy is still an open decision. PHP's built-in server is for development only.
