@echo off
echo Resetting database...

REM Run the SQL script to drop all tables
C:\xampp\mysql\bin\mysql -u root -D "dev.store" -e "source C:/xampp/htdocs/laravel/dev.store/drop_tables.sql"

REM Run migrations and seed
php artisan migrate --seed

echo Database reset complete!
