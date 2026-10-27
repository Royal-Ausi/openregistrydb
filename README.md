# Open Registry

A web application for managing institutional records. It handles the master register, pensioners, contract staff, semi-current records, the file diary and the subject index, with separate access for administrators and regular users.

Built with PHP and PostgreSQL as a records management system for a government office.

# Features

- Spreadsheet-style entry and editing of register records
- Search and sorting on every register
- Import from CSV or Excel, and export to CSV
- Role-based access: anyone logged in can view all registers, but users can only edit the registers assigned to them
- Admin tools for creating users, assigning registers and managing record statuses
- Help center where users can send questions to the system administrator

# Requirements

- PHP 7.4 or newer with the `pgsql` extension enabled
- PostgreSQL version -------
- Apache (XAMPP works fine for local use)
- Composer, only needed for Excel import. CSV import works without it.

# Installation

1. Put the project folder in your web root (for XAMPP: `C:\xampp\htdocs\openregistrydb`).

2. Create an empty PostgreSQL database.

3. Copy the config templates and fill in your own details:

   cp config.example.php config.php
   cp includes/system_admin.example.php includes/system_admin.local.php

   `config.php` holds the database connection. `system_admin.local.php` holds the system operator login. Neither file is tracked by Git.

4. Open `install.php` in your browser to create the tables.

5. (Optional) Install the Excel import library:

   composer install

6. Open `index.php` and log in.
   After installing, restrict or remove `install.php` so it cannot be run again on a live server.

# User roles

Role What they can do

- User - View all registers, edit assigned registers only
- Administrator - Everything a user can do, plus manage users, import, export and delete records

Import and export can also be switched on for individual users by the system operator.

# Project structure

index.php login page
user_dashboard.php dashboard for regular users
admin_dashboard.php dashboard for administrators
viewDatabase.php read-only view of any register
edit_register.php spreadsheet-style editor
includes/ database connection, authentication, shared helpers
modules/ edit and save scripts for each register
migrations/ database migration scripts
docs/ user guide
css/, js/ front-end assets

# Documentation

A step-by-step guide for staff and administrators is in [docs/USER_GUIDE.md](docs/USER_GUIDE.md).

# Notes

- Table names are checked against a fixed list in each script. If you add a new register, add it to those lists.
- All database queries use prepared statements.
- Passwords are stored as hashes using PHP's `password_hash()`.
- Make sure `uploads/` is writable by the web server and not publicly browsable.

# Author

ROYAL AUSI - LEAD DEVELOPER
