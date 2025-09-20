#!/bin/bash
set -euo pipefail

# Health Check Script for Slack Archive Application
# This script performs comprehensive health checks on the application

# Configuration
APP_URL="${APP_URL:-http://localhost:8000}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
REDIS_HOST="${REDIS_HOST:-localhost}"
REDIS_PORT="${REDIS_PORT:-6379}"
TIMEOUT=${TIMEOUT:-30}

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Logging functions
log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[$(date +'%H:%M:%S')] WARNING: $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%H:%M:%S')] ERROR: $1${NC}"
}

# Health check results
HEALTH_STATUS=0
HEALTH_RESULTS=()

# Function to add health check result
add_result() {
    local service=$1
    local status=$2
    local message=$3
    
    HEALTH_RESULTS+=("$service:$status:$message")
    
    if [ "$status" = "FAIL" ]; then
        HEALTH_STATUS=1
    fi
}

# Check web application response
check_web_app() {
    log "Checking web application..."
    
    local response
    local http_code
    
    if response=$(curl -s -w "HTTPSTATUS:%{http_code}" --max-time $TIMEOUT "${APP_URL}/_health" 2>/dev/null); then
        http_code=$(echo "$response" | tr -d '\n' | sed -e 's/.*HTTPSTATUS://')
        response=$(echo "$response" | sed -e 's/HTTPSTATUS.*//g')
        
        if [ "$http_code" -eq 200 ]; then
            add_result "web_app" "OK" "HTTP 200 - Application responding"
            
            # Check if response contains expected content
            if echo "$response" | grep -q "healthy"; then
                add_result "web_app_content" "OK" "Health endpoint returning expected content"
            else
                add_result "web_app_content" "WARN" "Health endpoint not returning expected content"
            fi
        else
            add_result "web_app" "FAIL" "HTTP $http_code - Application not responding correctly"
        fi
    else
        add_result "web_app" "FAIL" "Cannot connect to application"
    fi
}

# Check database connectivity
check_database() {
    log "Checking database connectivity..."
    
    if command -v pg_isready >/dev/null 2>&1; then
        if pg_isready -h "$DB_HOST" -p "$DB_PORT" -t 10 >/dev/null 2>&1; then
            add_result "database" "OK" "PostgreSQL is accepting connections"
            
            # Check if we can perform a simple query (if env vars are available)
            if [ -n "${DB_DATABASE:-}" ] && [ -n "${DB_USERNAME:-}" ]; then
                if PGPASSWORD="${DB_PASSWORD:-}" psql -h "$DB_HOST" -p "$DB_PORT" -U "${DB_USERNAME}" -d "${DB_DATABASE}" -c "SELECT 1;" >/dev/null 2>&1; then
                    add_result "database_query" "OK" "Database queries working"
                else
                    add_result "database_query" "WARN" "Cannot execute test query"
                fi
            fi
        else
            add_result "database" "FAIL" "Cannot connect to PostgreSQL"
        fi
    else
        if nc -z -w5 "$DB_HOST" "$DB_PORT" 2>/dev/null; then
            add_result "database" "OK" "Database port is open"
        else
            add_result "database" "FAIL" "Cannot connect to database port"
        fi
    fi
}

# Check Redis connectivity
check_redis() {
    log "Checking Redis connectivity..."
    
    if command -v redis-cli >/dev/null 2>&1; then
        if redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" ping | grep -q "PONG"; then
            add_result "redis" "OK" "Redis is responding to ping"
        else
            add_result "redis" "FAIL" "Redis not responding to ping"
        fi
    else
        if nc -z -w5 "$REDIS_HOST" "$REDIS_PORT" 2>/dev/null; then
            add_result "redis" "OK" "Redis port is open"
        else
            add_result "redis" "FAIL" "Cannot connect to Redis port"
        fi
    fi
}

# Check disk space
check_disk_space() {
    log "Checking disk space..."
    
    local usage
    usage=$(df / | tail -1 | awk '{print $5}' | sed 's/%//g')
    
    if [ "$usage" -lt 85 ]; then
        add_result "disk_space" "OK" "Disk usage: ${usage}%"
    elif [ "$usage" -lt 95 ]; then
        add_result "disk_space" "WARN" "Disk usage high: ${usage}%"
    else
        add_result "disk_space" "FAIL" "Disk usage critical: ${usage}%"
    fi
}

# Check memory usage
check_memory() {
    log "Checking memory usage..."
    
    local mem_info
    if mem_info=$(free | grep '^Mem:'); then
        local total used available
        total=$(echo "$mem_info" | awk '{print $2}')
        used=$(echo "$mem_info" | awk '{print $3}')
        available=$(echo "$mem_info" | awk '{print $7}')
        
        local usage_percent=$((used * 100 / total))
        
        if [ "$usage_percent" -lt 85 ]; then
            add_result "memory" "OK" "Memory usage: ${usage_percent}%"
        elif [ "$usage_percent" -lt 95 ]; then
            add_result "memory" "WARN" "Memory usage high: ${usage_percent}%"
        else
            add_result "memory" "FAIL" "Memory usage critical: ${usage_percent}%"
        fi
    else
        add_result "memory" "WARN" "Cannot determine memory usage"
    fi
}

# Check Laravel application health
check_laravel_health() {
    log "Checking Laravel application health..."
    
    # Check if we can run artisan commands (only if we're in the app directory)
    if [ -f "artisan" ]; then
        if php artisan --version >/dev/null 2>&1; then
            add_result "laravel_artisan" "OK" "Laravel artisan commands working"
            
            # Check if caches are working
            if php artisan config:cache --quiet 2>/dev/null; then
                add_result "laravel_config" "OK" "Laravel configuration cache working"
            else
                add_result "laravel_config" "WARN" "Laravel configuration cache not working"
            fi
        else
            add_result "laravel_artisan" "FAIL" "Laravel artisan commands not working"
        fi
    else
        add_result "laravel_artisan" "SKIP" "Not in Laravel application directory"
    fi
}

# Check SSL certificate (if HTTPS)
check_ssl_certificate() {
    if [[ "$APP_URL" == https://* ]]; then
        log "Checking SSL certificate..."
        
        local domain
        domain=$(echo "$APP_URL" | sed -e 's|^https://||' -e 's|/.*||' -e 's|:.*||')
        
        local expiry_date
        if expiry_date=$(echo | openssl s_client -servername "$domain" -connect "$domain:443" 2>/dev/null | openssl x509 -noout -dates 2>/dev/null | grep notAfter | cut -d= -f2); then
            local expiry_timestamp
            expiry_timestamp=$(date -d "$expiry_date" +%s 2>/dev/null || date -j -f "%b %d %H:%M:%S %Y %Z" "$expiry_date" +%s 2>/dev/null)
            local current_timestamp
            current_timestamp=$(date +%s)
            local days_until_expiry
            days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))
            
            if [ "$days_until_expiry" -gt 30 ]; then
                add_result "ssl_certificate" "OK" "SSL certificate valid for $days_until_expiry days"
            elif [ "$days_until_expiry" -gt 7 ]; then
                add_result "ssl_certificate" "WARN" "SSL certificate expires in $days_until_expiry days"
            else
                add_result "ssl_certificate" "FAIL" "SSL certificate expires in $days_until_expiry days"
            fi
        else
            add_result "ssl_certificate" "FAIL" "Cannot verify SSL certificate"
        fi
    fi
}

# Generate health report
generate_report() {
    echo
    echo "=== HEALTH CHECK REPORT ==="
    echo "Timestamp: $(date)"
    echo "App URL: $APP_URL"
    echo
    
    local ok_count=0
    local warn_count=0
    local fail_count=0
    local skip_count=0
    
    printf "%-20s %-6s %s\n" "SERVICE" "STATUS" "MESSAGE"
    printf "%-20s %-6s %s\n" "-------" "------" "-------"
    
    for result in "${HEALTH_RESULTS[@]}"; do
        IFS=':' read -r service status message <<< "$result"
        
        local color=""
        case "$status" in
            "OK") color="$GREEN"; ((ok_count++)) ;;
            "WARN") color="$YELLOW"; ((warn_count++)) ;;
            "FAIL") color="$RED"; ((fail_count++)) ;;
            "SKIP") color="$NC"; ((skip_count++)) ;;
        esac
        
        printf "${color}%-20s %-6s %s${NC}\n" "$service" "$status" "$message"
    done
    
    echo
    echo "Summary: ${ok_count} OK, ${warn_count} WARN, ${fail_count} FAIL, ${skip_count} SKIP"
    
    if [ $HEALTH_STATUS -eq 0 ]; then
        echo -e "${GREEN}Overall Status: HEALTHY${NC}"
    else
        echo -e "${RED}Overall Status: UNHEALTHY${NC}"
    fi
    
    return $HEALTH_STATUS
}

# Main health check function
main() {
    log "Starting health check..."
    
    check_web_app
    check_database
    check_redis
    check_disk_space
    check_memory
    check_laravel_health
    check_ssl_certificate
    
    generate_report
}

# Run main function
main "$@"