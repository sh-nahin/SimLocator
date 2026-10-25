# SimLocator

Register with **name, email, mobile number, district, area and a password**. After submitting, the page says
**"You are a Robi user from Mirpur, Dhaka."** Later, **log in with your mobile number and password** and you see the same result.
An **admin page** lists everyone who registered, with their operator and location.

- **Frontend:** Next.js + React + HeroUI (v3, Tailwind CSS v4)
- **Backend:** plain PHP (a small JSON API)
- **Database:** MySQL, running on your own computer

## Files

```
simlocator/
├─ database.sql              creates the database, the table, and a local DB user
├─ backend/                  PHP API (port 8000)
│  ├─ index.php              the routes (what each URL does)
│  ├─ lib.php                phone check, operator detection, validation, database connection
│  ├─ .env.example           database login + admin password  -> copy to .env
│  └─ tests/run.php          automated checks
└─ frontend/                 Next.js site (port 3000)
   ├─ app/page.tsx           registration form (with password)
   ├─ app/login/page.tsx     login: phone + password
   ├─ app/admin/page.tsx     admin page
   ├─ app/layout.tsx         header + page frame
   ├─ components/            OperatorChip.tsx, ResultCard.tsx
   └─ lib/api.ts             the one place that talks to the PHP API
```

## What you need installed
- **PHP 8.2+** (with `pdo_mysql` and `mbstring`; XAMPP/Laragon already include them)
- **MySQL 8** or MariaDB (XAMPP includes it)
- **Node.js 20.9+**

## Run it (three steps, three terminals)

### 1) Database
Open a terminal in the project folder:
```bash
mysql -u root -p < database.sql
```
(XAMPP: root has no password, so use `mysql -u root < database.sql`; the `mysql` program is in `C:\xampp\mysql\bin`.)

This creates database `simlocator`, table `registrations`, and a user `simlocator` (password `simlocator_pass`)
that can only read, add and update rows in that one table.

> Used an earlier version of this project? Start clean first: `mysql -u root -p -e "DROP DATABASE simlocator"`, then run the command above.
> The table now has a password column, and `database.sql` does not change an existing table.

### 2) Backend
```bash
cd backend
cp .env.example .env          # Windows: copy .env.example .env
php -S localhost:8000 index.php
```
Leave it running. Check in your browser: http://localhost:8000/api/health shows `{"status":"ok"}`.

Edit `backend/.env` and set your own `ADMIN_KEY` (the admin page password). `.env` is read on every request, so no restart is needed after editing it.

### 3) Frontend
```bash
cd frontend
cp .env.local.example .env.local      # Windows: copy .env.local.example .env.local
npm install
npm run dev
```
Open http://localhost:3000.

## Use it
1. **Register** at http://localhost:3000: fill the form (try `018XXXXXXXX`, a password of 8+ characters, district Dhaka, area Mirpur) and press **Submit**.
   You see: *You are a **Robi** user from Mirpur, Dhaka.*
2. **Log in** at http://localhost:3000/login with the same phone number and password. You see the operator and location again.
3. **Admin** at http://localhost:3000/admin: enter your `ADMIN_KEY`. You see the total, a count per operator, and a searchable table.

Operators come from the first 3 digits: 013/017 Grameenphone, 014/019 Banglalink, 015 Teletalk, 016/018 Robi.
Numbers can be typed as `018…`, `88018…` or `+88018…`, at registration and at login.

## How passwords are handled
- The password is sent to the PHP API once and stored only as a **bcrypt hash**. Nobody, including the admin page, can read it back.
- It must be 8 to 72 characters (bcrypt ignores anything past 72).
- **Lockout:** after 5 wrong passwords for a phone number, that number cannot log in for 10 minutes (even with the right password).
  A correct login resets the counter. To unlock someone right away:
  `mysql -u root -p -e "UPDATE simlocator.registrations SET failed_logins=0, locked_until=NULL WHERE phone='01812345678'"`
- Wrong phone and wrong password give the same message, so the login form does not reveal which phone numbers are registered.

## How to see the database
**Command line**
```bash
mysql -u root -p simlocator
```
```sql
SHOW TABLES;
SELECT * FROM registrations;                        -- password_hash is a long scrambled text starting with $2y$
SELECT COUNT(*) FROM registrations;
SELECT operator, COUNT(*) FROM registrations GROUP BY operator;
SELECT name, phone, operator, CONCAT(area, ', ', district) AS location FROM registrations ORDER BY id DESC LIMIT 10;
SELECT phone, failed_logins, locked_until FROM registrations WHERE locked_until > NOW();   -- locked accounts
```
**With a screen (no commands)**
- **XAMPP:** start Apache + MySQL, open http://localhost/phpmyadmin → database `simlocator` → table `registrations` → Browse.
- **MySQL Workbench or DBeaver:** new connection → host `127.0.0.1`, port `3306`, user `root` (use your root password) → open `simlocator`.
  (Use root for browsing: the `simlocator` user is deliberately limited.)

## How to see the server working
- **PHP terminal:** every request the browser makes is printed there (for example `POST /api/login [200]`). PHP errors appear there too.
- **Browser:** open http://localhost:8000/api/districts directly.
  Admin data needs the key header: `curl -H "X-Admin-Key: YOUR_KEY" http://localhost:8000/api/admin/registrations`
- **Browser DevTools (F12) → Network tab:** click Submit or Log in and watch the request, its JSON body and the response.
- **Next.js terminal:** shows the frontend compiling and any page errors.

## How the pieces connect
```
Browser ──▶ Next.js (3000) page ──fetch──▶ PHP API (8000) ──▶ MySQL (3306)
```
| Setting | File | Meaning |
|---|---|---|
| `DB_*` | `backend/.env` | how PHP logs in to MySQL |
| `FRONTEND_ORIGIN` | `backend/.env` | the site address allowed to call the API (must be exactly `http://localhost:3000`) |
| `ADMIN_KEY` | `backend/.env` | admin page password |
| `NEXT_PUBLIC_API_URL` | `frontend/.env.local` | where the site finds the API |

| Page | API call | Database |
|---|---|---|
| Register | `GET /api/districts`, `POST /api/register` | inserts a row (with the password hash) |
| Log in | `POST /api/login` (phone + password) | reads the row, checks the hash, counts failed attempts |
| Admin | `GET /api/admin/registrations` (header `X-Admin-Key`) | reads rows (never the hash) |

## Testing

**Automated (backend, 69 checks).** With MySQL and the API running:
```bash
cd backend
php tests/run.php
```
Part 1 needs nothing (phone rules, operators, validation, password length). Part 2 calls the live API: register, duplicates, bad input,
SQL-injection text, hashed storage, login (right/wrong/unknown/odd formats), lockout and reset, admin key, CORS.
It leaves rows with `@test.invalid` emails; remove them with
`mysql -u root -p -e "DELETE FROM simlocator.registrations WHERE email LIKE '%@test.invalid'"`.

**Manual checklist (do this in the browser).**

| # | Steps | Expected |
|---|---|---|
| 1 | Register `Rahim Uddin`, email, phone `01812345678`, password, Dhaka, Mirpur | "You are a **Robi** user from Mirpur, Dhaka." and a row in the database |
| 2 | Phone `01712345678` | Grameenphone |
| 3 | Phones `01912345678`, `01512345678`, `01612345678` | Banglalink, Teletalk, Robi |
| 4 | Phone `+8801812345678` and `8801812345678` | accepted, stored as `01812345678` |
| 5 | Phone `01112345678`, `123`, `abc` | error under the phone field, nothing saved |
| 6 | Register the same phone twice / same email twice | "…is already registered." |
| 7 | Password and confirmation differ | "Passwords do not match.", nothing saved |
| 8 | Password with 7 characters | error under the password field, nothing saved |
| 9 | Empty fields / invalid email | error messages on the fields |
| 10 | Name `<script>alert(1)</script>` | saved and shown as plain text, no popup |
| 11 | Check the table in MySQL | `password_hash` starts with `$2y$`, your real password appears nowhere |
| 12 | Log in with the right phone + password | "Welcome back…" with the operator and location |
| 13 | Log in with a wrong password / an unregistered phone | "Wrong phone number or password." (same message for both) |
| 14 | Enter a wrong password 5 times, then the right one | "Too many wrong passwords…", works again after 10 minutes (or after the unlock command) |
| 15 | Log in with `+88018…` instead of `018…` | works |
| 16 | **Log out** button | back to the empty login form |
| 17 | Submit with the backend stopped | red message "Cannot reach the server…" |
| 18 | Stop MySQL, Submit | red message "Cannot connect to the database…" |
| 19 | `/admin` with a wrong key, then the right key | "Wrong admin key.", then total, per-operator counts and the table (no passwords) |
| 20 | Admin search `robi`, then a nonsense word | filters rows, then "No registrations found." |
| 21 | Resize the window to phone width | forms and table remain usable (table scrolls sideways) |

## Troubleshooting
| Problem | Fix |
|---|---|
| "Cannot reach the server" | PHP not running, or `NEXT_PUBLIC_API_URL` wrong. Restart `npm run dev` after editing `.env.local`. |
| Browser console says CORS | `FRONTEND_ORIGIN` in `backend/.env` must match the address bar exactly (`localhost` ≠ `127.0.0.1`). |
| "Cannot connect to the database" | MySQL stopped, wrong `DB_*` in `backend/.env`, or step 1 not done. Details are in the PHP terminal. |
| "Unknown column 'password_hash'" | You still have the old table. See the note under step 1 (drop the database and re-import). |
| `could not find driver` | enable `extension=pdo_mysql` in `php.ini`. |
| "ADMIN_KEY is not set" | add `ADMIN_KEY=...` to `backend/.env`. |
| "Too many wrong passwords" | wait 10 minutes, or use the unlock command above. |
| Port already in use | change `8000`/`3000` and update `FRONTEND_ORIGIN` / `NEXT_PUBLIC_API_URL` to match. |

## Good to know (limits)
- Logging in shows your operator and location. It is not a long-lived session: refreshing the page returns to the login form. Nothing else in the app needs one.
- There is no "forgot password" and no email or SMS verification, so anyone can register any number that is not yet taken.
- The operator comes from the number prefix. A number moved to another operator (number portability) still shows its original operator.
- The lockout is per phone number, so someone could deliberately lock another person's number for 10 minutes by guessing wrongly.
- The admin key is a single shared password sent in a header. Fine on your own computer; on a public server use HTTPS and a long random key.
- The admin table shows the latest 500 registrations. Times are the database server's time.
