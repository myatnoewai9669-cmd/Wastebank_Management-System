# Wastebank_Management-System
# WasteBank - Smart Waste Management System

A web-based waste bank where members deposit recyclable waste, earn money and points, book pickups, and redeem rewards. Staff record deposits by scanning member QR codes, and admins manage members, pickups, and prices.

Built with **PHP, MySQL, JavaScript, and CSS** (no framework).

## Features

### Members
- Register, log in, and reset a forgotten password with a 6-digit email code
- Dashboard with balance, points, charts, and recent transactions
- Personal QR code (member ID card) to show at deposit
- Transaction history with date and waste type filters, pagination
- Book a pickup (3-step form), view and cancel pending requests
- Recycling points map (Leaflet + OpenStreetMap) with nearest-first list
- Rewards catalog: redeem points for vouchers and items
- Recycling guide, profile editing with photo upload, help and support form
- Notifications and a page search in the top bar

### Staff
- Scan a member QR code with the camera, or type the member ID
- Record a deposit: balance and points update automatically

### Admin
- Dashboard with totals, yearly chart, and top waste types
- Confirm or complete pickup requests (completed pickups give bonus points)
- Manage members (role and active status), waste types (price and points per kg)
- Support inbox for messages sent from the Help page

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8 (PDO, sessions) |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, vanilla JavaScript |
| Charts | Chart.js |
| Map | Leaflet + OpenStreetMap |
| QR | qrcodejs (generate), html5-qrcode (scan) |
| Email | Gmail SMTP (built-in minimal sender, no library needed) |

## Security

- Passwords hashed with `password_hash()`
- All database access uses PDO prepared statements
- CSRF token on every form
- Output escaped with `htmlspecialchars`
- Role checks on staff and admin pages
- Balance, points, and stock changes run inside database transactions with row locks
- Password reset codes are hashed, expire after 10 minutes, and allow 5 attempts
- Avatar uploads are validated by real file type and size

## Getting Started

### Requirements
- XAMPP (or any Apache + PHP 8 + MySQL stack)
- A browser with camera access for QR scanning (works on `localhost` or HTTPS)

### Installation

1. Clone the repository into your web root:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/YOUR-USERNAME/wastebank.git
   ```
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, then import `database/schema.sql`. This creates the `wastebank` database and all tables.
4. Check the database settings and site URL in `db.php`:
   ```php
   define('APP_URL', 'http://localhost/wastebank');
   define('APP_DEBUG', true);   // set to false on a real server
   ```
5. (Optional) Enable email, see the next section.
6. Open `http://localhost/wastebank/` and register an account.

### Create an admin or staff account

Register normally, then change the role in phpMyAdmin (SQL tab):

```sql
UPDATE users SET role = 'admin' WHERE email = 'you@example.com';
```

Use `'staff'` instead of `'admin'` for a staff account. Log out and log in again afterwards.

### Email setup (optional)

Email is used for password reset codes and support messages. Without it, messages are still saved in the database.

1. Turn on 2-Step Verification for your Gmail account and create an **App Password**.
2. Copy `mail_config.example.php` to `mail_config.php` and fill in your values.
3. Open `test_mail.php` to check that sending works, then delete that file.

`mail_config.php` is listed in `.gitignore`. Never commit real passwords.

## Project Structure

```
wastebank/
├── index.php               # redirects to the right page
├── login.php, register.php, logout.php, forgot_password.php
├── dashboard.php           # member dashboard
├── profile.php, qr.php, balance.php, transactions.php
├── pickup.php, points.php, rewards.php, guide.php
├── help-support.php
├── scan.php, add_waste.php          # staff
├── admin_dashboard.php, members.php # admin
├── pickup_requests.php, waste_types.php, support_inbox.php
├── header.php, footer.php, db.php, mail.php
├── api/chart.php
├── assets/
│   ├── css/  (auth.css, dashboard.css)
│   └── js/   (auth, dashboard, pickup, points, rewards, scan, ...)
└── database/schema.sql
```

## Database Overview

`users` · `waste_types` · `transactions` · `pickup_requests` · `recycling_points` · `rewards` · `redemptions` · `points_ledger` · `support_messages` · `password_codes`

## Before Deploying

- Set `APP_DEBUG` to `false` (otherwise reset codes are shown on screen)
- Set `APP_URL` to your real HTTPS address
- Use a dedicated MySQL user with a password instead of `root`
- Turn off `display_errors` in `php.ini`
- Delete `test_mail.php`

## Screenshots

Add screenshots here, for example:

| Login | Member Dashboard | Recycling Points |
|---|---|---|
| 

![Login](docs/login.png)

 | 

![Dashboard](docs/dashboard.png)

 | 

![Map](docs/map.png)

 |

## Author

**Myat Noe Wai**
Information Systems, Universitas Nusa Putra, Sukabumi, Indonesia

## License

This project was created for learning and portfolio purposes. Add a license of your choice (for example MIT) in a `LICENSE` file.
