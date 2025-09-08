# V1 Production TODO Checklist

Legend: [ ] = todo, [x] = done. Use priorities P0 (blocker), P1 (high), P2 (normal). Add Owner and Due where relevant.

## 0) Security
- [x] P1 Block routes like `/login`, `/register`, `/checkout` in production using BlockedRoutes Middleware.
- [x] P1 Hides the user icon that leads to signup/login.
- [x] P1 replaces the checkout button with the order now button that redirects to WhatsApp.
- [x] P1 if the order total is less than X, don't let the user complete the order.
- [x] P0 Translates the App into Turkish.


## 1) Social Media
- [ ] P1 Instagram.
- [ ] P1 Facebook.
- [ ] P2 Tiktok.
- [ ] P0 Email or Gmail.


## 2) Products 50–100 product
- [ ] P0 Collect Data (1- pick normal images from Google, when you have 50–100 product 2- create JSON file with the product's data).
- [ ] P0 Design Products Images.
- [ ] P0 Adds Products to the app.


## 3) Homepage Design
- [ ] P2


---

### Commands cheat sheet (reference)

```bash
# Composer (production)
composer install --no-dev --prefer-dist --optimize-autoloader

# Build assets
npm ci && npm run build

# Migrations & caches
php artisan migrate --force
php artisan cache:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache

# Queue & scheduler (examples)
php artisan queue:work --queue=default,emails --max-time=3600 --stop-when-empty
# Cron: * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```
