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

**1. What is authentication in this application?**
Authentication confirms who the user is. The user submits an email and password on `login.php`; `authenticateUser()` in `services/userService.php` finds the account with `findUserByEmail()` and checks the password with `password_verify()` against the stored hash. It also confirms the account is Active. If both checks pass, `loginUser()` in `includes/auth.php` starts an authenticated session.

**2. What is authorization in this application?**
Authorization decides what an authenticated user is allowed to do. Pages call `requireLogin()` or `requireRole([...])` in `includes/auth.php`, and services add checks such as `assertAdministrator()` and the ownership checks `canViewRequest()` / `canModifyRequest()`. The role is re-read from the `users` table on every request, so a role or status change takes effect immediately.

**3. How is student access different from administrator access?**
A Student can create tutoring requests, and can view, edit and cancel only their own Open requests. They can also view and update their own profile and password. Another student's request returns "Request not found". An Administrator can view every user account and every tutoring request (read only), and can manage accounts. An administrator cannot create, edit or cancel tutoring requests.

**4. Which functions or pages should be limited to administrators?**
- `manage-users.php` and `user-details.php`: view and search accounts (UM-03)
- `update-role.php`: change a user's role (UM-06)
- `account-status.php`: activate or deactivate accounts (UM-07)
- `delete-user.php`: delete accounts (UM-08)
- Viewing all students' requests in `requests.php`
- Service functions: `searchAccounts()`, `getAccountForAdmin()`, `changeUserRole()`, `setAccountStatus()`, `deleteUserAccount()`, all protected by `assertAdministrator()`
- Creating administrators: only from the command line with `database/create-admin.php`, never through public registration

**5. What happens after logout if a user tries to visit a protected page directly?**
`logout.php` calls `logoutUser()`, which clears `$_SESSION`, deletes the session cookie and destroys the session. If the user then types a protected URL such as `php/requests.php`, `requireLogin()` finds no logged-in user and redirects to `login.php` with the message "Please log in to continue." Protected pages are also sent with `Cache-Control: no-store`, so the browser Back button does not show cached private pages.

## Part 7: Trace Registration and Login

### Student registration workflow
1. **Form displayed by:** `php/register.php`
2. **Form submitted to:** `php/process-register.php` (registration controller)
3. **CSRF token:** `includes/auth.php`. `csrfToken()` / `csrfField()` create it; `verifyCsrfOrFail()` / `isValidCsrfToken()` check it with `hash_equals()`.
4. **Validation service:** `registerUser()` in `services/userService.php`, using rules in `includes/validation.php` (`validateFullName`, `validateEmailAddress`, `validatePassword`, `validatePasswordConfirmation`, `validateSelfRegistrationRole`)
5. **Password hashed:** in `registerUser()` (`services/userService.php`) with `password_hash($password, PASSWORD_DEFAULT)`
6. **Repository files:** `repositories/userPDO.php` → `insertUser()`; `repositories/studentPDO.php` → `insertStudentForUser()` (or `linkStudentToUser()` for an existing Lab 6 student row)
7. **Tables written:** `users` (account) and `students` (profile linked by `students.user_id`)

### User login workflow
1. **Form displayed by:** `php/login.php`
2. **Credentials submitted to:** `php/process-login.php` (login controller)
3. **User retrieved by email:** `findUserByEmail()` in `repositories/userPDO.php`
4. **Password verified:** `authenticateUser()` in `services/userService.php` with `password_verify()`; it also checks that `status` is Active
5. **Session actions after login:** `loginUser()` in `includes/auth.php` calls `session_regenerate_id(true)`, stores `user_id`, `role`, `login_time`, `last_activity`, and creates a new CSRF token
6. **Why regenerate the session ID:** to prevent session fixation. An ID an attacker obtained or planted before login becomes useless, because the logged-in session gets a new ID.
7. **Student vs administrator:** `currentUser()` in `includes/auth.php` reloads the user's `role` from the `users` table on every request; `requireRole(['Administrator'])` or `requireRole(['Student'])` allows or denies the page.

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
