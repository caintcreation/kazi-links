# KaziLink

KaziLink is a Kenyan online-jobs platform where users complete short tasks, earn rewards, manage referrals, and request M-Pesa withdrawals.

**Live site:** [kazilinks.kesug.com](https://kazilinks.kesug.com/)

## Features

- User registration, login, email verification, and password recovery
- Short jobs and level-based tasks
- Wallet ledger with earnings, referrals, and withdrawals
- M-Pesa payments through PayHero
- Customer support contact form
- Separate administration area for managing users, jobs, levels, transactions, referrals, and withdrawals
- Responsive interface for mobile and desktop users

## Technology

- PHP 7.4+ / PHP 8.x
- MySQL or MariaDB
- PDO
- PHPMailer
- PayHero API
- Vanilla JavaScript and CSS

## Project structure

| Path | Purpose |
| --- | --- |
| `index.php`, `register.php`, `login.php` | Public user-facing pages |
| `dashboard.php`, `jobs.php`, `wallet.php` | Authenticated user experience |
| `admin/` | Administration portal |
| `api/` | Form and payment API endpoints |
| `includes/` | Authentication, database, mail, payment, and shared helpers |
| `assets/` | Stylesheets and browser JavaScript |
| `database/database.sql` | Database schema and seed data |
| `vendor/phpmailer/` | PHPMailer dependency used by the mail service |

## Local setup

1. Install PHP 7.4 or later, MySQL/MariaDB, and a web server such as Apache.
2. Create a database named `kazilink`.
3. Import [`database/database.sql`](database/database.sql) using phpMyAdmin or the MySQL command line.
4. Configure the application values using environment variables. The committed `includes/config.php` contains no production credentials.
5. For local development, the application defaults to:
   - Database host: `localhost`
   - Database name: `kazilink`
   - Database user: `root`
   - Database password: empty
6. Open the project through Apache, or use PHP's development server from the project directory:

   ```text
   php -S localhost:8000
   ```

## Production configuration

Set these environment variables on the hosting server:

```text
KAZILINK_DB_HOST
KAZILINK_DB_NAME
KAZILINK_DB_USER
KAZILINK_DB_PASS
KAZILINK_DB_PORT=3306
KAZILINK_DB_CHARSET=utf8mb4
KAZILINK_SMTP_USER
KAZILINK_SMTP_PASS
KAZILINK_SMTP_FROM_EMAIL
KAZILINK_SMTP_FROM_NAME=KaziLink Kenya
KAZILINK_SUPPORT_EMAIL
KAZILINK_PAYHERO_USERNAME
KAZILINK_PAYHERO_PASSWORD
KAZILINK_PAYHERO_CHANNEL_ID
KAZILINK_PAYHERO_BASE_URL=https://backend.payhero.co.ke/api/v2
KAZILINK_PAYHERO_BASIC_AUTH
KAZILINK_PAYHERO_CALLBACK_SECRET
```

On shared hosting where environment variables are unavailable, create an ignored `includes/config.local.php` containing the deployment-specific overrides and never commit it. Do not place passwords, API keys, SMTP app passwords, or database credentials in tracked files.

After deployment, configure the PayHero callback URL to point to:

```text
https://kazilinks.kesug.com/api/payhero-callback.php
```

## Security

- Keep `includes/config.local.php`, logs, and all credentials outside version control.
- Use HTTPS in production.
- Use a strong, unique administrator password.
- Rotate any credential that has previously been exposed in source files or logs.

## License

This project is maintained for the KaziLink platform. Contact the project owner before reusing it in another service.
