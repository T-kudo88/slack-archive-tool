#!/bin/sh
set -e

# Function to wait for service
wait_for_service() {
    host=$1
    port=$2
    service_name=$3
    
    echo "Waiting for $service_name at $host:$port..."
    while ! nc -z $host $port; do
        echo "Waiting for $service_name to be ready..."
        sleep 2
    done
    echo "$service_name is ready!"
}

# Wait for dependencies
wait_for_service postgres 5432 "PostgreSQL"
wait_for_service redis 6379 "Redis"

# Set correct permissions
chown -R www:www /var/www/html/storage
chown -R www:www /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Run migrations in production (only if needed)
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Clear and cache Laravel configurations
echo "Optimizing Laravel..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Create symbolic link for storage (if not exists)
if [ ! -L "/var/www/html/public/storage" ]; then
    php artisan storage:link
fi

# Container role-based execution
case "${CONTAINER_ROLE:-app}" in
    app)
        echo "Starting PHP-FPM application server..."
        exec php-fpm
        ;;
    queue)
        echo "Starting queue worker..."
        exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=512
        ;;
    scheduler)
        echo "Starting scheduler..."
        while true; do
            php artisan schedule:run
            sleep 60
        done
        ;;
    *)
        echo "Unknown container role: $CONTAINER_ROLE"
        exit 1
        ;;
esac