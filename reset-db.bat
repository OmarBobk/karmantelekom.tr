@echo off
echo Resetting database...

REM Recreate the database so every table is dropped, including ones added later.
REM (migrate:fresh can't be used: the dot in "dev.store" breaks Laravel's drop-all-tables query.)
C:\xampp\mysql\bin\mysql -u root -e "DROP DATABASE IF EXISTS `dev.store`; CREATE DATABASE `dev.store` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

REM Run migrations and seed
php artisan migrate --seed --force

echo Database reset complete!
