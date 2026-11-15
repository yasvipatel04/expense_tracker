# Personal Expense Tracker

A simple PHP and MySQL application for recording and organizing personal expenses.

## Features

- Add and view expenses
- Edit and delete expenses
- Organize expenses by category
- Add a custom category
- Search and filter by category, amount, date, or month
- Browse results with pagination

## Technology

- PHP
- MySQL
- HTML and CSS
- XAMPP
- MySQL Workbench

## Requirements

- XAMPP with Apache, PHP, and MySQL
- MySQL Workbench
- A web browser

## Setup

1. Copy this repository into XAMPP's `htdocs` folder.
2. Run `database/schema.sql` in MySQL Workbench.
3. Copy `config/db_connection.example.php` to `config/db_connection.php`.
4. Set the local MySQL username and password in `config/db_connection.php`.
5. Start Apache and MySQL in XAMPP.
6. Open `http://localhost/personal-expense-tracker/` in a browser.

## Project Structure

- `config/` contains database connection settings.
- `database/` contains the SQL needed to recreate the database.
- `includes/` contains shared page layout.
- `assets/` contains the stylesheet.
- `index.php` adds and displays expenses.
- `edit_expense.php` edits an expense.
- `delete_expense.php` deletes an expense.
- `screenshots/` contains screenshots of the application.

## Screenshots

Add screenshots of the running application to the `screenshots/` folder and link them here.

![Expense tracker dashboard](screenshots/dashboard.png)