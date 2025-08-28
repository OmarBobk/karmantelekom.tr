# Laravel Reverb Troubleshooting Guide

## Common Issues and Solutions

### 1. "Origin not allowed" Error (Code 4009)

**Problem**: Reverb server rejects connections due to CORS restrictions.

**Solution**:
1. Start Reverb with the correct hostname:
   ```bash
   php artisan reverb:start --hostname=localhost --port=6001 --debug
   ```

2. Use the provided batch file:
   ```bash
   start-reverb.bat
   ```

3. Ensure JavaScript configuration uses correct settings:
   ```javascript
   window.Echo = new Echo({
       broadcaster: 'reverb',
       key: '1xvd7kdthpfbqt0a0roj',
       wsHost: 'localhost',
       wsPort: 6001,
       wssPort: 6001,
       forceTLS: false, // Disable TLS for local development
       enabledTransports: ['ws', 'wss'],
       disableStats: true,
   });
   ```

### 2. Connection Refused Errors

**Problem**: Cannot connect to Reverb server.

**Solutions**:
1. **Check if Reverb is running**:
   ```bash
   php artisan reverb:start --debug
   ```

2. **Verify port availability**:
   - Default port: 6001
   - Check if port is not used by another service
   - Use different port if needed: `--port=6002`

3. **Check firewall settings**:
   - Ensure port 6001 is not blocked
   - Add exception for localhost

### 3. Events Not Broadcasting

**Problem**: Events are dispatched but not received by clients.

**Solutions**:
1. **Verify event implements ShouldBroadcast**:
   ```php
   class ShopCreated implements ShouldBroadcast
   ```

2. **Check channel authorization**:
   ```php
   // routes/channels.php
   Broadcast::channel('shops', function ($user) {
       return true; // Allow public access
   });
   ```

3. **Test broadcasting manually**:
   ```bash
   php artisan test:broadcasting --event=shop-created
   ```

### 4. Frontend Not Receiving Events

**Problem**: Backend events work but frontend doesn't receive them.

**Solutions**:
1. **Check JavaScript console for errors**
2. **Verify Echo initialization**:
   ```javascript
   console.log('Echo status:', window.Echo);
   ```

3. **Check channel subscriptions**:
   ```javascript
   window.Echo.channel('shops')
       .listen('.shop.created', (e) => {
           console.log('Shop created:', e);
       });
   ```

4. **Verify user authentication** (for private channels):
   ```javascript
   window.Echo.private('admin.dashboard')
       .listen('.shop.created', (e) => {
           console.log('Admin notification:', e);
       });
   ```

### 5. Production Deployment Issues

**Problem**: Broadcasting works locally but not in production.

**Solutions**:
1. **Update environment variables**:
   ```env
   REVERB_HOST=your-domain.com
   REVERB_PORT=443
   REVERB_SCHEME=https
   ```

2. **Configure SSL/TLS**:
   ```javascript
   window.Echo = new Echo({
       broadcaster: 'reverb',
       key: 'your-app-key',
       wsHost: 'your-domain.com',
       wsPort: 443,
       wssPort: 443,
       forceTLS: true,
       enabledTransports: ['wss'],
   });
   ```

3. **Set up reverse proxy** (nginx example):
   ```nginx
   location /apps/ {
       proxy_pass http://localhost:6001;
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "upgrade";
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
       proxy_cache_bypass $http_upgrade;
   }
   ```

## Debugging Steps

### 1. Enable Debug Mode
```bash
php artisan reverb:start --debug
```

### 2. Check Browser Console
- Look for connection errors
- Verify event reception
- Check for JavaScript errors

### 3. Monitor Network Tab
- Check WebSocket connection status
- Verify event payloads
- Look for failed requests

### 4. Test Broadcasting Commands
```bash
# Test all events
php artisan test:broadcasting

# Test specific event
php artisan test:broadcasting --event=shop-created

# Test with custom data
php artisan test:broadcasting --event=order-created --data='{"custom": "data"}'
```

## Environment Configuration

### Local Development (.env)
```env
BROADCAST_CONNECTION=reverb
REVERB_APP_KEY=1xvd7kdthpfbqt0a0roj
REVERB_APP_SECRET=ecaf5yubqx91kr9c27gr
REVERB_APP_ID=326352
REVERB_HOST=localhost
REVERB_PORT=6001
REVERB_SCHEME=http
APP_ENV=local
```

### Production (.env)
```env
BROADCAST_CONNECTION=reverb
REVERB_APP_KEY=your-production-key
REVERB_APP_SECRET=your-production-secret
REVERB_APP_ID=your-production-app-id
REVERB_HOST=your-domain.com
REVERB_PORT=443
REVERB_SCHEME=https
APP_ENV=production
```

## Useful Commands

```bash
# Start Reverb server
php artisan reverb:start --hostname=localhost --port=6001 --debug

# Test broadcasting
php artisan test:broadcasting

# Clear configuration cache
php artisan config:clear

# View broadcasting configuration
php artisan config:show broadcasting

# Check Reverb status
php artisan reverb:status
```

## Support

If you continue to experience issues:

1. Check Laravel Reverb documentation
2. Review Laravel Broadcasting documentation
3. Check browser console for detailed error messages
4. Verify network connectivity and firewall settings
5. Test with a simple event first before complex implementations
