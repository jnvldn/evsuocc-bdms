# 🩸 EVSU-OCC Blood Donation Management System

This project is a web application built using **PHP and MySQL**. Follow the steps below to set up the project and run the server locally.

---

## ## Requirements

Make sure the following are installed on your system:

- PHP 8.1 or newer
- MySQL (XAMPP / Laragon)
- Composer
- Git

## Clone the Repository
Bash
git clone [https://github.com/yourusername/evsu-occ-blood-donation.git](https://github.com/yourusername/evsu-occ-blood-donation.git)
cd evsu-occ-blood-donation
## Database Configuration
Open phpMyAdmin.

Create a new database named blood_donation_db.

Import the database.sql file found in the project root.

## Run the Development Server
Start the local PHP development server:

Bash
php -S localhost:8000
By default, the application will be available at:

http://localhost:8000

## Access the Admin Panel
To manage donors and inventory, visit:

http://localhost:8000/admin

Login using the administrator credentials created during setup.
