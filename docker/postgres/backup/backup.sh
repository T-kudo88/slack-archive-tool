#!/bin/bash
set -e

# PostgreSQL Backup Script for Production
# Automated daily backups with retention

# Configuration
DB_HOST="postgres"
DB_PORT="5432"
DB_NAME="${POSTGRES_DB:-slack_archive}"
DB_USER="${POSTGRES_USER:-slack_user}"
BACKUP_DIR="/backups"
RETENTION_DAYS=30
LOG_FILE="$BACKUP_DIR/backup.log"

# Ensure backup directory exists
mkdir -p "$BACKUP_DIR"

# Logging function
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Function to perform backup
perform_backup() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="$BACKUP_DIR/${DB_NAME}_${timestamp}.sql.gz"
    local backup_info_file="$BACKUP_DIR/${DB_NAME}_${timestamp}.info"
    
    log "Starting backup of database $DB_NAME"
    
    # Create backup info file
    cat > "$backup_info_file" << EOF
Backup Information
==================
Database: $DB_NAME
Host: $DB_HOST
Port: $DB_PORT
User: $DB_USER
Timestamp: $(date '+%Y-%m-%d %H:%M:%S')
Backup File: $(basename "$backup_file")
EOF
    
    # Perform the backup
    if pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" \
        --verbose --clean --no-owner --no-privileges | gzip > "$backup_file"; then
        
        local file_size=$(du -h "$backup_file" | cut -f1)
        log "Backup completed successfully: $backup_file ($file_size)"
        
        # Add size info to backup info file
        echo "File Size: $file_size" >> "$backup_info_file"
        
        # Verify backup integrity
        if gzip -t "$backup_file"; then
            log "Backup integrity check passed"
            echo "Integrity: PASSED" >> "$backup_info_file"
        else
            log "ERROR: Backup integrity check failed"
            echo "Integrity: FAILED" >> "$backup_info_file"
            exit 1
        fi
        
    else
        log "ERROR: Backup failed"
        rm -f "$backup_file" "$backup_info_file"
        exit 1
    fi
}

# Function to cleanup old backups
cleanup_old_backups() {
    log "Cleaning up backups older than $RETENTION_DAYS days"
    
    # Remove old backup files
    find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -type f -mtime +$RETENTION_DAYS -exec rm -f {} \;
    find "$BACKUP_DIR" -name "${DB_NAME}_*.info" -type f -mtime +$RETENTION_DAYS -exec rm -f {} \;
    
    # Remove old log entries (keep last 1000 lines)
    if [ -f "$LOG_FILE" ]; then
        tail -n 1000 "$LOG_FILE" > "${LOG_FILE}.tmp" && mv "${LOG_FILE}.tmp" "$LOG_FILE"
    fi
    
    log "Cleanup completed"
}

# Function to check database connectivity
check_connectivity() {
    log "Checking database connectivity"
    
    if pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t 30; then
        log "Database is accessible"
    else
        log "ERROR: Cannot connect to database"
        exit 1
    fi
}

# Function to get database statistics
get_db_stats() {
    log "Collecting database statistics"
    
    local stats_file="$BACKUP_DIR/db_stats_$(date +%Y%m%d).txt"
    
    psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c "
        SELECT 
            schemaname,
            tablename,
            n_tup_ins as inserts,
            n_tup_upd as updates,
            n_tup_del as deletes,
            n_live_tup as live_rows,
            n_dead_tup as dead_rows
        FROM pg_stat_user_tables 
        ORDER BY n_live_tup DESC;
    " > "$stats_file"
    
    log "Database statistics saved to $stats_file"
}

# Function to monitor disk space
check_disk_space() {
    local available_space=$(df "$BACKUP_DIR" | tail -1 | awk '{print $4}')
    local available_gb=$(($available_space / 1024 / 1024))
    
    log "Available disk space: ${available_gb}GB"
    
    if [ $available_gb -lt 5 ]; then
        log "WARNING: Low disk space (less than 5GB available)"
        # Optionally send alert or cleanup more aggressively
    fi
}

# Main backup routine
main() {
    log "=== Backup routine started ==="
    
    # Check if we're in a container
    if [ ! -f /.dockerenv ]; then
        log "ERROR: This script should run inside a Docker container"
        exit 1
    fi
    
    # Check disk space
    check_disk_space
    
    # Check database connectivity
    check_connectivity
    
    # Perform backup
    perform_backup
    
    # Get database statistics (weekly)
    if [ $(date +%u) -eq 1 ]; then  # Monday
        get_db_stats
    fi
    
    # Cleanup old backups
    cleanup_old_backups
    
    log "=== Backup routine completed ==="
}

# Run backup every 6 hours in a loop
while true; do
    main
    
    # Wait 6 hours
    sleep 21600
done