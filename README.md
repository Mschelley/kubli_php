# KUBLI Setup

A simple guide to run KUBLI on your machine.

## Requirements

1. PHP 8.1 or newer with the pdo_mysql extension
2. A running MySQL or MariaDB server

## Steps

1. Create the database and load the schema.

mysql -u root -p -e "CREATE DATABASE kubli CHARACTER SET utf8mb4;"
mysql -u root -p kubli < database/schema.sql

2. Open config.php and enter your database credentials.

define('DB_HOST', 'localhost');
define('DB_NAME', 'kubli');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

3. Make the uploads folder writable.

chmod -R 775 public/uploads

4. Start the app using the built in PHP server.

php -S localhost:8000 -t public

5. Open http://localhost:8000 in your browser.

6. Create an account through Create Account. Every new account starts as a Citizen.

7. Since only an Admin can manage accounts, and no Admin exists on a fresh database, promote your first account directly through SQL.

UPDATE users u JOIN roles r ON r.name = 'Admin' SET u.role_id = r.id WHERE u.email = 'youremail@example.com';

8. Sign back in with that account. You will now see the Admin tabs, including User Accounts, where you can create and manage every other account from the browser.

## Resetting all data

Drop and recreate the database, then reload the schema.

mysql -u root -p -e "DROP DATABASE kubli; CREATE DATABASE kubli CHARACTER SET utf8mb4;"
mysql -u root -p kubli < database/schema.sql