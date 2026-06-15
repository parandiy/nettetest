Support panel
=================

Requirements
------------

This Web Project is compatible with Nette 3.2 and requires PHP 8.2.


Installation
------------

1. Clone this repository.
2. Install dependencies: composer install
3. Configure database connection in `app/config/common.neon`
4. mysql -u root -p -e "CREATE DATABASE support_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
5. mysql -u root -p support_app < db/schema.sql
6. from project root run: php db/seed.php --nette
