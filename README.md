# FocusList — PHP/MySQL To-Do App

A responsive Todoist-inspired task manager built with:

- HTML and Bootstrap 5
- Custom CSS
- JavaScript and jQuery AJAX
- PHP 8+
- MySQL or MariaDB

## Included features

- Create, edit, complete, restore, and delete tasks
- Default categories: Important, Urgent, School, Work, and Personal
- Create and delete custom categories
- Separate task priority: Low, Medium, High, or Urgent
- Due date and time
- Inbox, Today, Upcoming, Overdue, and Completed views
- Category filtering
- Search and sorting
- Dashboard statistics
- Responsive mobile sidebar
- Prepared SQL statements
- CSRF protection for data-changing requests
- Frontend output escaping to reduce XSS risk

## Project structure

```text
focuslist/
├── index.php
├── api/
│   ├── categories.php
│   └── tasks.php
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
├── config/
│   ├── app.php
│   └── database.php
├── sql/
│   └── database.sql
└── README.md
```

## Run with XAMPP on Windows

1. Extract the project into:

   ```text
   C:\xampp\htdocs\todoist-lite
   ```

2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.

3. Open phpMyAdmin in your browser:

   ```text
   http://localhost/phpmyadmin
   ```

4. Select the **Import** tab and import:

   ```text
   todoist-lite/sql/database.sql
   ```

5. Open the application:

   ```text
   http://localhost/todoist-lite/
   ```

## Database configuration

The default XAMPP configuration is already present in `config/database.php`:

```php
host: 127.0.0.1
port: 3306
database: todoist_lite
username: root
password: empty
```

Change those values if your MySQL installation uses different credentials.

You may alternatively set these environment variables:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `APP_TIMEZONE`
- `APP_DEBUG`

## Common errors

### “A server error occurred”

Confirm that:

- MySQL is running.
- You imported `sql/database.sql`.
- The credentials in `config/database.php` are correct.
- PHP has the `pdo_mysql` extension enabled.

For local debugging only, set `APP_DEBUG=1` so API errors include more detail.

### MySQL starts and immediately stops in XAMPP

Another program may already use port `3306`. Stop the conflicting MySQL service or change the MySQL port in XAMPP and in `config/database.php`.

## Current scope

This version is intentionally a **single-user MVP**. It does not include sign-up/login, shared projects, reminders, recurring tasks, or notifications. The database and API are structured so those features can be added later.
