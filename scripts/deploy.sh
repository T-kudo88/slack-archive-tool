#!/bin/bash
set -euo pipefail

# Production Deployment Script for VPS
# This script handles the complete deployment process with rollback capability

# Configuration
DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_HOST="${DEPLOY_HOST:-your-server.com}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/slack-archive}"
APP_NAME="${APP_NAME:-slack-archive}"
HEALTH_CHECK_URL="${HEALTH_CHECK_URL:-https://your-domain.com/_health}"
ROLLBACK_KEEPS="${ROLLBACK_KEEPS:-5}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
}

# Function to execute commands on remote server
remote_exec() {
    ssh "${DEPLOY_USER}@${DEPLOY_HOST}" "$1"
}

# Function to copy files to remote server
remote_copy() {
    scp -r "$1" "${DEPLOY_USER}@${DEPLOY_HOST}:$2"
}

# Function to create deployment directory structure
prepare_deployment_structure() {
    log "Preparing deployment directory structure..."
    
    remote_exec "mkdir -p ${DEPLOY_PATH}/{releases,shared,shared/storage/app,shared/storage/framework,shared/storage/logs,shared/uploads}"
    remote_exec "mkdir -p ${DEPLOY_PATH}/shared/storage/framework/{sessions,views,cache,testing}"
}

# Function to create new release directory
create_release() {
    RELEASE_NAME=$(date +%Y%m%d_%H%M%S)
    RELEASE_PATH="${DEPLOY_PATH}/releases/${RELEASE_NAME}"
    
    log "Creating new release: ${RELEASE_NAME}"
    remote_exec "mkdir -p ${RELEASE_PATH}"
    
    echo "${RELEASE_NAME}"
}

# Function to upload application code
upload_code() {
    local release_path=$1
    
    log "Uploading application code..."
    
    # Create temporary deployment package
    local temp_dir=$(mktemp -d)
    
    # Copy application files (excluding development files)
    rsync -av --exclude-from=.deployignore . "${temp_dir}/"
    
    # Upload to server
    remote_copy "${temp_dir}/" "${release_path}/"
    
    # Cleanup
    rm -rf "${temp_dir}"
}

# Function to create symbolic links for shared directories
create_shared_links() {
    local release_path=$1
    
    log "Creating symbolic links for shared directories..."
    
    # Remove existing directories and create symlinks
    remote_exec "rm -rf ${release_path}/storage"
    remote_exec "ln -sf ${DEPLOY_PATH}/shared/storage ${release_path}/storage"
    
    remote_exec "rm -rf ${release_path}/public/uploads"
    remote_exec "ln -sf ${DEPLOY_PATH}/shared/uploads ${release_path}/public/uploads"
    
    # Create .env symlink
    remote_exec "ln -sf ${DEPLOY_PATH}/shared/.env ${release_path}/.env"
}

# Function to install dependencies and build assets
install_dependencies() {
    local release_path=$1
    
    log "Installing dependencies and building assets..."
    
    remote_exec "cd ${release_path} && composer install --no-dev --optimize-autoloader --no-interaction"
    remote_exec "cd ${release_path} && npm ci --only=production"
    remote_exec "cd ${release_path} && npm run build"
}

# Function to run Laravel optimization commands
optimize_laravel() {
    local release_path=$1
    
    log "Optimizing Laravel application..."
    
    remote_exec "cd ${release_path} && php artisan config:cache"
    remote_exec "cd ${release_path} && php artisan route:cache"
    remote_exec "cd ${release_path} && php artisan view:cache"
    remote_exec "cd ${release_path} && php artisan event:cache"
    remote_exec "cd ${release_path} && php artisan queue:restart"
}

# Function to run database migrations
run_migrations() {
    local release_path=$1
    
    log "Running database migrations..."
    
    remote_exec "cd ${release_path} && php artisan migrate --force"
}

# Function to update current symlink
update_symlink() {
    local release_path=$1
    
    log "Updating current symlink..."
    
    # Create/update the current symlink atomically
    remote_exec "ln -sfn ${release_path} ${DEPLOY_PATH}/current_tmp"
    remote_exec "mv ${DEPLOY_PATH}/current_tmp ${DEPLOY_PATH}/current"
}

# Function to reload web server
reload_webserver() {
    log "Reloading web server..."
    
    # Reload PHP-FPM
    remote_exec "sudo systemctl reload php8.2-fpm" || warn "Could not reload PHP-FPM"
    
    # Reload Nginx
    remote_exec "sudo systemctl reload nginx" || warn "Could not reload Nginx"
}

# Function to perform health check
health_check() {
    log "Performing health check..."
    
    local max_attempts=10
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -f -s -o /dev/null "${HEALTH_CHECK_URL}"; then
            log "Health check passed!"
            return 0
        fi
        
        warn "Health check attempt ${attempt}/${max_attempts} failed. Retrying in 10 seconds..."
        sleep 10
        ((attempt++))
    done
    
    error "Health check failed after ${max_attempts} attempts"
    return 1
}

# Function to get the previous release for rollback
get_previous_release() {
    remote_exec "ls -1t ${DEPLOY_PATH}/releases | head -n 2 | tail -n 1" 2>/dev/null || echo ""
}

# Function to rollback to previous release
rollback() {
    log "Rolling back to previous release..."
    
    local previous_release
    previous_release=$(get_previous_release)
    
    if [ -z "$previous_release" ]; then
        error "No previous release found for rollback"
        return 1
    fi
    
    log "Rolling back to release: ${previous_release}"
    
    # Update symlink to previous release
    remote_exec "ln -sfn ${DEPLOY_PATH}/releases/${previous_release} ${DEPLOY_PATH}/current"
    
    # Reload web server
    reload_webserver
    
    # Verify rollback
    if health_check; then
        log "Rollback successful!"
        return 0
    else
        error "Rollback failed - health check still failing"
        return 1
    fi
}

# Function to cleanup old releases
cleanup_old_releases() {
    log "Cleaning up old releases..."
    
    remote_exec "cd ${DEPLOY_PATH}/releases && ls -1t | tail -n +$((ROLLBACK_KEEPS + 1)) | xargs -r rm -rf"
}

# Function to send notification
send_notification() {
    local status=$1
    local message=$2
    
    if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"🚀 ${APP_NAME} Deployment ${status}: ${message}\"}" \
            "${SLACK_WEBHOOK_URL}" || warn "Failed to send Slack notification"
    fi
    
    if [ -n "${DISCORD_WEBHOOK_URL:-}" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"content\":\"🚀 ${APP_NAME} Deployment ${status}: ${message}\"}" \
            "${DISCORD_WEBHOOK_URL}" || warn "Failed to send Discord notification"
    fi
}

# Main deployment function
deploy() {
    local start_time=$(date +%s)
    
    log "Starting deployment of ${APP_NAME}..."
    
    # Prepare deployment structure
    prepare_deployment_structure
    
    # Create new release
    local release_name
    release_name=$(create_release)
    local release_path="${DEPLOY_PATH}/releases/${release_name}"
    
    # Upload application code
    upload_code "${release_path}"
    
    # Create shared links
    create_shared_links "${release_path}"
    
    # Install dependencies and build assets
    install_dependencies "${release_path}"
    
    # Run Laravel optimizations
    optimize_laravel "${release_path}"
    
    # Run database migrations
    run_migrations "${release_path}"
    
    # Update current symlink
    update_symlink "${release_path}"
    
    # Reload web server
    reload_webserver
    
    # Health check
    if health_check; then
        local end_time=$(date +%s)
        local duration=$((end_time - start_time))
        
        log "Deployment completed successfully in ${duration} seconds!"
        send_notification "SUCCESS" "Release ${release_name} deployed in ${duration}s"
        
        # Cleanup old releases
        cleanup_old_releases
    else
        error "Deployment failed health check. Rolling back..."
        
        if rollback; then
            send_notification "FAILED" "Release ${release_name} failed health check. Rolled back successfully."
        else
            send_notification "CRITICAL" "Release ${release_name} failed and rollback also failed!"
        fi
        
        return 1
    fi
}

# Pre-deployment checks
pre_deployment_checks() {
    log "Running pre-deployment checks..."
    
    # Check if we can connect to the server
    if ! ssh -o ConnectTimeout=10 "${DEPLOY_USER}@${DEPLOY_HOST}" "echo 'Connection test successful'"; then
        error "Cannot connect to deployment server"
        return 1
    fi
    
    # Check if required environment variables are set
    if [ -z "${DEPLOY_USER}" ] || [ -z "${DEPLOY_HOST}" ] || [ -z "${DEPLOY_PATH}" ]; then
        error "Required environment variables not set"
        return 1
    fi
    
    # Check if .env file exists on server
    if ! remote_exec "test -f ${DEPLOY_PATH}/shared/.env"; then
        error "Environment file not found at ${DEPLOY_PATH}/shared/.env"
        return 1
    fi
    
    log "Pre-deployment checks passed!"
}

# Main script
main() {
    case "${1:-deploy}" in
        "deploy")
            pre_deployment_checks && deploy
            ;;
        "rollback")
            rollback
            ;;
        "health-check")
            health_check
            ;;
        *)
            echo "Usage: $0 {deploy|rollback|health-check}"
            exit 1
            ;;
    esac
}

# Run main function with all arguments
main "$@"