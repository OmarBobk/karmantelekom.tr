@echo off
echo Starting Laravel Reverb Server for Local Development...
echo.
echo Configuration:
echo - Hostname: localhost
echo - Port: 6001
echo - Scheme: http (no TLS for local development)
echo.
echo Press Ctrl+C to stop the server
echo.

php artisan reverb:start --hostname=localhost --port=6001 --debug

pause
