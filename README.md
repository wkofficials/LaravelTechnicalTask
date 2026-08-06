
# LaravelTechnicalTask
DEPLOYMENT NOTES - Laravel Technical Task
===========================================

REQUIREMENTS
- PHP 8.2+
- MySQL/MariaDB (required - FULLTEXT search on the records table needs InnoDB;
  SQLite will not work in production)
- Composer, Node.js/npm

1. GET THE CODE ON THE SERVER
   git clone <repo>
   cd LaravelTechnicalTask

2. INSTALL DEPENDENCIES
   composer install --no-dev --optimize-autoloader
   npm install
   npm run build

3. ENVIRONMENT
   cp .env.example .env
   php artisan key:generate
   Edit .env:
     APP_ENV=production
     APP_DEBUG=false
     APP_URL=https://your-domain.com
     DB_CONNECTION=mysql
     DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD = your real values
     QUEUE_CONNECTION=database
     DB_QUEUE_RETRY_AFTER=3900   (keep this high - see note below)

4. DATABASE
   php artisan migrate --force
   php artisan db:seed --force
     -> creates the 3 roles/permissions (admin, manager, viewer)
     -> creates 3 demo users (admin@example.com / manager@example.com /
        viewer@example.com, all password: "password")
     -> change or remove these demo accounts before going live

5. STORAGE
   php artisan storage:link
   Make sure storage/ and bootstrap/cache/ are writable by the web server user

6. CACHE FOR PRODUCTION
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache

7. WEB SERVER
   Document root must point to the /public folder, not the project root.

8. QUEUE WORKER - CRITICAL, DO NOT SKIP
   Excel imports only work if a queue worker is actually running. Nothing
   processes uploaded files otherwise - they just sit as "pending" forever.

   Run under a process supervisor (systemd or Supervisor), not just in a
   terminal, so it restarts automatically:

     php artisan queue:work --tries=1 --timeout=3600

   Example Supervisor config (recommended):
     [program:laravel-queue-worker]
     command=php /path/to/app/artisan queue:work --tries=1 --timeout=3600
     autostart=true
     autorestart=true
     user=www-data
     numprocs=1

   Restart the worker after every deploy (it caches loaded code in memory):
     php artisan queue:restart

9. SCHEDULER (only if/when cron jobs are added later)
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1

NOTES
- Uploaded import files are stored under storage/app/private/imports and are
  NOT web-accessible - that's intentional.
- If deploying behind a load balancer with multiple app servers, all servers
  must share the same DB (already required) and file storage should move to
  S3/shared storage instead of local disk if imports are uploaded on one
  server and processed by a worker on another.