#!/bin/bash
# Production Deployment Script for Slack Archive
# VPS Docker Compose Production Environment

set -e

# Configuration
PROJECT_NAME="slack-archive"
BACKUP_DIR="/opt/slack-archive/backups"
ENV_FILE=".env.production"
COMPOSE_FILES="-f docker-compose.production.yml -f docker-security.yml"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        error "This script must be run as root"
        exit 1
    fi
}

# Check system requirements
check_requirements() {
    log "Checking system requirements..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed"
        exit 1
    fi
    
    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        error "Docker Compose is not installed"
        exit 1
    fi
    
    # Check disk space (at least 10GB free)
    available_space=$(df / | tail -1 | awk '{print $4}')
    available_gb=$((available_space / 1024 / 1024))
    
    if [ $available_gb -lt 10 ]; then
        error "Insufficient disk space. At least 10GB required, ${available_gb}GB available"
        exit 1
    fi
    
    success "System requirements check passed"
}

# Setup directories
setup_directories() {
    log "Setting up directories..."
    
    mkdir -p /opt/slack-archive/{data/{postgres,redis},storage,bootstrap/cache,backups,logs,cache/nginx}
    mkdir -p /opt/slack-archive/ssl
    
    # Set permissions
    chown -R 1000:1000 /opt/slack-archive/storage
    chown -R 1000:1000 /opt/slack-archive/bootstrap/cache
    chown -R 999:999 /opt/slack-archive/data/postgres
    chown -R 999:999 /opt/slack-archive/data/redis
    
    chmod -R 755 /opt/slack-archive
    chmod -R 775 /opt/slack-archive/storage
    chmod -R 775 /opt/slack-archive/bootstrap/cache
    
    success "Directories setup completed"
}

# Setup environment file
setup_environment() {
    log "Setting up environment configuration..."
    
    if [ ! -f "$ENV_FILE" ]; then
        if [ -f ".env.production.example" ]; then
            cp .env.production.example "$ENV_FILE"
            warning "Environment file created from example. Please update with your production values."
            echo "Edit $ENV_FILE with your production settings, then run this script again."
            exit 1
        else
            error "Environment example file not found"
            exit 1
        fi
    fi
    
    # Validate critical environment variables
    source "$ENV_FILE"
    
    if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:YOUR_32_CHARACTER_SECRET_KEY_HERE" ]; then
        error "APP_KEY not configured. Generate with: php artisan key:generate"
        exit 1
    fi
    
    if [ -z "$DB_PASSWORD" ] || [ "$DB_PASSWORD" = "YOUR_STRONG_DATABASE_PASSWORD_HERE" ]; then
        error "Database password not configured"
        exit 1
    fi
    
    success "Environment configuration validated"
}

# Generate SSL certificates with Let's Encrypt
setup_ssl() {
    log "Setting up SSL certificates..."
    
    if [ -z "$APP_URL" ] || [ "$APP_URL" = "https://your-domain.com" ]; then
        warning "Domain not configured in APP_URL. SSL setup skipped."
        return
    fi
    
    domain=$(echo "$APP_URL" | sed 's|https\?://||' | sed 's|/.*||')
    
    # Check if certificates already exist
    if [ -f "/opt/slack-archive/ssl/fullchain.pem" ]; then
        log "SSL certificates already exist"
        return
    fi
    
    log "Setting up SSL certificates for domain: $domain"
    
    # Install Certbot if not present
    if ! command -v certbot &> /dev/null; then
        log "Installing Certbot..."
        if command -v apt-get &> /dev/null; then
            apt-get update
            apt-get install -y certbot
        elif command -v yum &> /dev/null; then
            yum install -y certbot
        else
            warning "Could not install Certbot automatically. Please install manually."
            return
        fi
    fi
    
    # Generate certificates
    certbot certonly --standalone --preferred-challenges http -d "$domain" \
        --email "admin@${domain}" --agree-tos --non-interactive
    
    # Copy certificates to Docker volume
    cp "/etc/letsencrypt/live/${domain}/fullchain.pem" /opt/slack-archive/ssl/
    cp "/etc/letsencrypt/live/${domain}/privkey.pem" /opt/slack-archive/ssl/
    cp "/etc/letsencrypt/live/${domain}/chain.pem" /opt/slack-archive/ssl/
    
    # Set permissions
    chmod 644 /opt/slack-archive/ssl/*.pem
    
    success "SSL certificates generated successfully"
}

# Build and deploy containers
deploy_containers() {
    log "Building and deploying containers..."
    
    # Pull latest images
    docker-compose $COMPOSE_FILES pull
    
    # Build custom images
    docker-compose $COMPOSE_FILES build --no-cache
    
    # Start containers
    docker-compose $COMPOSE_FILES up -d
    
    # Wait for services to be ready
    log "Waiting for services to start..."
    sleep 30
    
    # Check service health
    check_services_health
    
    success "Containers deployed successfully"
}

# Run Laravel setup commands
setup_laravel() {
    log "Setting up Laravel application..."
    
    # Wait for database to be ready
    docker-compose $COMPOSE_FILES exec -T php php artisan migrate --force
    
    # Clear and optimize caches
    docker-compose $COMPOSE_FILES exec -T php php artisan config:clear
    docker-compose $COMPOSE_FILES exec -T php php artisan config:cache
    docker-compose $COMPOSE_FILES exec -T php php artisan route:cache
    docker-compose $COMPOSE_FILES exec -T php php artisan view:cache
    docker-compose $COMPOSE_FILES exec -T php php artisan event:cache
    
    # Create storage symlink
    docker-compose $COMPOSE_FILES exec -T php php artisan storage:link
    
    success "Laravel setup completed"
}

# Check services health
check_services_health() {
    log "Checking services health..."
    
    local failed_services=""
    
    # Check each service
    services=("nginx" "php" "postgres" "redis")
    
    for service in "${services[@]}"; do
        if ! docker-compose $COMPOSE_FILES ps "$service" | grep -q "Up"; then
            failed_services="$failed_services $service"
        fi
    done
    
    if [ -n "$failed_services" ]; then
        error "Failed services:$failed_services"
        log "Showing logs for failed services..."
        for service in $failed_services; do
            echo "=== Logs for $service ==="
            docker-compose $COMPOSE_FILES logs --tail=20 "$service"
        done
        exit 1
    fi
    
    success "All services are healthy"
}

# Setup firewall rules
setup_firewall() {
    log "Setting up firewall rules..."
    
    # Check if UFW is available
    if command -v ufw &> /dev/null; then
        # Enable UFW
        ufw --force enable
        
        # Default policies
        ufw default deny incoming
        ufw default allow outgoing
        
        # SSH access
        ufw allow ssh
        
        # HTTP/HTTPS
        ufw allow 80/tcp
        ufw allow 443/tcp
        
        # Block direct access to database and Redis
        ufw deny 5432/tcp
        ufw deny 6379/tcp
        
        success "UFW firewall configured"
    else
        warning "UFW not available. Please configure firewall manually."
    fi
}

# Setup monitoring and logging
setup_monitoring() {
    log "Setting up monitoring and logging..."
    
    # Setup log rotation
    cat > /etc/logrotate.d/slack-archive << EOF
/opt/slack-archive/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 1000 1000
    postrotate
        docker-compose $COMPOSE_FILES restart nginx php queue scheduler
    endscript
}
EOF
    
    # Setup system monitoring script
    cat > /opt/slack-archive/monitor.sh << 'EOF'
#!/bin/bash
# System monitoring script for Slack Archive

# Check Docker services
if ! docker-compose -f /opt/slack-archive/docker-compose.production.yml -f /opt/slack-archive/docker-security.yml ps | grep -q "Up"; then
    echo "$(date): Some Docker services are down" >> /opt/slack-archive/logs/monitor.log
    # Send alert (implement your preferred alerting method)
fi

# Check disk space
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 90 ]; then
    echo "$(date): Disk usage is ${DISK_USAGE}%" >> /opt/slack-archive/logs/monitor.log
fi

# Check memory usage
MEM_USAGE=$(free | grep Mem | awk '{print ($3/$2) * 100.0}' | cut -d. -f1)
if [ $MEM_USAGE -gt 90 ]; then
    echo "$(date): Memory usage is ${MEM_USAGE}%" >> /opt/slack-archive/logs/monitor.log
fi
EOF
    
    chmod +x /opt/slack-archive/monitor.sh
    
    # Setup crontab for monitoring
    (crontab -l 2>/dev/null; echo "*/5 * * * * /opt/slack-archive/monitor.sh") | crontab -
    
    success "Monitoring setup completed"
}

# Setup backup automation
setup_backup_automation() {
    log "Setting up backup automation..."
    
    # Create backup script
    cat > /opt/slack-archive/backup-wrapper.sh << 'EOF'
#!/bin/bash
cd /opt/slack-archive
docker-compose -f docker-compose.production.yml -f docker-security.yml exec -T postgres_backup /scripts/backup.sh
EOF
    
    chmod +x /opt/slack-archive/backup-wrapper.sh
    
    # Setup daily backup cron job
    (crontab -l 2>/dev/null; echo "0 2 * * * /opt/slack-archive/backup-wrapper.sh >> /opt/slack-archive/logs/backup.log 2>&1") | crontab -
    
    success "Backup automation setup completed"
}

# Create maintenance script
create_maintenance_script() {
    log "Creating maintenance script..."
    
    cat > /opt/slack-archive/maintenance.sh << 'EOF'
#!/bin/bash
# Slack Archive Maintenance Script

COMPOSE_FILES="-f docker-compose.production.yml -f docker-security.yml"
cd /opt/slack-archive

case "$1" in
    start)
        echo "Starting Slack Archive..."
        docker-compose $COMPOSE_FILES up -d
        ;;
    stop)
        echo "Stopping Slack Archive..."
        docker-compose $COMPOSE_FILES down
        ;;
    restart)
        echo "Restarting Slack Archive..."
        docker-compose $COMPOSE_FILES restart
        ;;
    logs)
        docker-compose $COMPOSE_FILES logs -f "${2:-}"
        ;;
    status)
        docker-compose $COMPOSE_FILES ps
        ;;
    update)
        echo "Updating Slack Archive..."
        git pull
        docker-compose $COMPOSE_FILES pull
        docker-compose $COMPOSE_FILES build --no-cache
        docker-compose $COMPOSE_FILES up -d
        docker-compose $COMPOSE_FILES exec -T php php artisan migrate --force
        docker-compose $COMPOSE_FILES exec -T php php artisan config:cache
        ;;
    backup)
        echo "Running manual backup..."
        docker-compose $COMPOSE_FILES exec -T postgres_backup /scripts/backup.sh
        ;;
    cleanup)
        echo "Cleaning up Docker resources..."
        docker system prune -f
        docker volume prune -f
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|logs|status|update|backup|cleanup}"
        exit 1
        ;;
esac
EOF
    
    chmod +x /opt/slack-archive/maintenance.sh
    ln -sf /opt/slack-archive/maintenance.sh /usr/local/bin/slack-archive
    
    success "Maintenance script created at /usr/local/bin/slack-archive"
}

# Main deployment function
main() {
    echo "=========================================="
    echo "Slack Archive Production Deployment"
    echo "=========================================="
    
    check_root
    check_requirements
    setup_directories
    setup_environment
    setup_ssl
    setup_firewall
    deploy_containers
    setup_laravel
    setup_monitoring
    setup_backup_automation
    create_maintenance_script
    
    echo ""
    success "Deployment completed successfully!"
    echo ""
    echo "Next steps:"
    echo "1. Configure your domain DNS to point to this server"
    echo "2. Update Slack OAuth settings with your domain"
    echo "3. Test the application: $APP_URL"
    echo "4. Monitor logs: slack-archive logs"
    echo "5. Check status: slack-archive status"
    echo ""
    echo "Management commands:"
    echo "  slack-archive start|stop|restart|status"
    echo "  slack-archive logs [service]"
    echo "  slack-archive update"
    echo "  slack-archive backup"
    echo ""
}

# Handle script arguments
case "${1:-deploy}" in
    deploy)
        main
        ;;
    update)
        log "Running update..."
        docker-compose $COMPOSE_FILES pull
        docker-compose $COMPOSE_FILES up -d
        setup_laravel
        success "Update completed"
        ;;
    *)
        echo "Usage: $0 [deploy|update]"
        exit 1
        ;;
esac