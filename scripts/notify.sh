#!/bin/bash
set -euo pipefail

# Notification Script for CI/CD Pipeline
# Supports Slack and Discord webhooks

# Configuration
NOTIFICATION_TYPE="${1:-deployment}"
STATUS="${2:-success}"
MESSAGE="${3:-Notification}"
ENVIRONMENT="${ENVIRONMENT:-production}"
APP_NAME="${APP_NAME:-Slack Archive}"

# Colors for console output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%H:%M:%S')] ERROR: $1${NC}"
}

# Function to get emoji based on status
get_emoji() {
    local status=$1
    case "$status" in
        "success"|"passed"|"completed") echo "✅" ;;
        "failure"|"failed"|"error") echo "❌" ;;
        "warning"|"warn") echo "⚠️" ;;
        "info"|"information") echo "ℹ️" ;;
        "deployment") echo "🚀" ;;
        "rollback") echo "🔄" ;;
        "security") echo "🔒" ;;
        "testing") echo "🧪" ;;
        *) echo "📢" ;;
    esac
}

# Function to get color based on status
get_color() {
    local status=$1
    case "$status" in
        "success"|"passed"|"completed") echo "#28a745" ;;  # Green
        "failure"|"failed"|"error") echo "#dc3545" ;;      # Red  
        "warning"|"warn") echo "#ffc107" ;;                # Yellow
        "info"|"information") echo "#17a2b8" ;;            # Blue
        *) echo "#6c757d" ;;                               # Gray
    esac
}

# Function to send Slack notification
send_slack_notification() {
    if [ -z "${SLACK_WEBHOOK_URL:-}" ]; then
        log "Slack webhook URL not configured, skipping Slack notification"
        return 0
    fi
    
    local emoji
    emoji=$(get_emoji "$STATUS")
    local color
    color=$(get_color "$STATUS")
    
    # Prepare additional fields
    local fields="[]"
    if [ -n "${GITHUB_REPOSITORY:-}" ]; then
        fields=$(jq -n \
            --arg repo "${GITHUB_REPOSITORY}" \
            --arg branch "${GITHUB_REF_NAME:-main}" \
            --arg commit "${GITHUB_SHA:-unknown}" \
            --arg actor "${GITHUB_ACTOR:-system}" \
            --arg run_id "${GITHUB_RUN_ID:-}" \
            --arg run_url "${GITHUB_SERVER_URL:-}/${GITHUB_REPOSITORY:-}/actions/runs/${GITHUB_RUN_ID:-}" \
            '[
                {
                    "title": "Repository",
                    "value": $repo,
                    "short": true
                },
                {
                    "title": "Branch",
                    "value": $branch,
                    "short": true
                },
                {
                    "title": "Commit",
                    "value": $commit[:8],
                    "short": true
                },
                {
                    "title": "Triggered by",
                    "value": $actor,
                    "short": true
                }
            ] + (if $run_id != "" then [{
                "title": "Action Run",
                "value": "<\($run_url)|#\($run_id)>",
                "short": false
            }] else [] end)'
        )
    fi
    
    local payload
    payload=$(jq -n \
        --arg emoji "$emoji" \
        --arg app_name "$APP_NAME" \
        --arg notification_type "$NOTIFICATION_TYPE" \
        --arg status "$STATUS" \
        --arg message "$MESSAGE" \
        --arg environment "$ENVIRONMENT" \
        --arg color "$color" \
        --argjson fields "$fields" \
        '{
            "username": "CI/CD Bot",
            "icon_emoji": ":robot_face:",
            "attachments": [
                {
                    "color": $color,
                    "title": "\($emoji) \($app_name) - \($notification_type | ascii_upcase)",
                    "text": $message,
                    "fields": $fields,
                    "footer": "CI/CD Pipeline",
                    "ts": now
                }
            ]
        }'
    )
    
    if curl -X POST -H 'Content-type: application/json' \
            --data "$payload" \
            --max-time 10 \
            --silent \
            --fail \
            "${SLACK_WEBHOOK_URL}"; then
        log "Slack notification sent successfully"
    else
        error "Failed to send Slack notification"
        return 1
    fi
}

# Function to send Discord notification
send_discord_notification() {
    if [ -z "${DISCORD_WEBHOOK_URL:-}" ]; then
        log "Discord webhook URL not configured, skipping Discord notification"
        return 0
    fi
    
    local emoji
    emoji=$(get_emoji "$STATUS")
    local color_hex
    color_hex=$(get_color "$STATUS")
    # Convert hex color to decimal for Discord
    local color_decimal
    color_decimal=$((16#${color_hex#\#}))
    
    # Prepare embed fields
    local fields="[]"
    if [ -n "${GITHUB_REPOSITORY:-}" ]; then
        fields=$(jq -n \
            --arg repo "${GITHUB_REPOSITORY}" \
            --arg branch "${GITHUB_REF_NAME:-main}" \
            --arg commit "${GITHUB_SHA:-unknown}" \
            --arg actor "${GITHUB_ACTOR:-system}" \
            --arg run_id "${GITHUB_RUN_ID:-}" \
            --arg run_url "${GITHUB_SERVER_URL:-}/${GITHUB_REPOSITORY:-}/actions/runs/${GITHUB_RUN_ID:-}" \
            '[
                {
                    "name": "Repository",
                    "value": $repo,
                    "inline": true
                },
                {
                    "name": "Branch", 
                    "value": $branch,
                    "inline": true
                },
                {
                    "name": "Commit",
                    "value": "`\($commit[:8])`",
                    "inline": true
                },
                {
                    "name": "Triggered by",
                    "value": $actor,
                    "inline": true
                }
            ] + (if $run_id != "" then [{
                "name": "Action Run",
                "value": "[\($run_id)](\($run_url))",
                "inline": false
            }] else [] end)'
        )
    fi
    
    local payload
    payload=$(jq -n \
        --arg emoji "$emoji" \
        --arg app_name "$APP_NAME" \
        --arg notification_type "$NOTIFICATION_TYPE" \
        --arg status "$STATUS" \
        --arg message "$MESSAGE" \
        --arg environment "$ENVIRONMENT" \
        --argjson color "$color_decimal" \
        --argjson fields "$fields" \
        '{
            "username": "CI/CD Bot",
            "avatar_url": "https://cdn-icons-png.flaticon.com/512/25/25231.png",
            "embeds": [
                {
                    "title": "\($emoji) \($app_name) - \($notification_type | ascii_upcase)",
                    "description": $message,
                    "color": $color,
                    "fields": $fields,
                    "footer": {
                        "text": "CI/CD Pipeline"
                    },
                    "timestamp": (now | todate)
                }
            ]
        }'
    )
    
    if curl -X POST -H 'Content-type: application/json' \
            --data "$payload" \
            --max-time 10 \
            --silent \
            --fail \
            "${DISCORD_WEBHOOK_URL}"; then
        log "Discord notification sent successfully"
    else
        error "Failed to send Discord notification"
        return 1
    fi
}

# Function to send email notification (optional)
send_email_notification() {
    if [ -z "${EMAIL_RECIPIENT:-}" ] || [ -z "${EMAIL_SMTP_HOST:-}" ]; then
        log "Email configuration not found, skipping email notification"
        return 0
    fi
    
    local emoji
    emoji=$(get_emoji "$STATUS")
    local subject="${emoji} ${APP_NAME} - ${NOTIFICATION_TYPE} ${STATUS}"
    
    local body
    body=$(cat <<EOF
${APP_NAME} Notification

Type: ${NOTIFICATION_TYPE}
Status: ${STATUS}
Environment: ${ENVIRONMENT}
Message: ${MESSAGE}

$(if [ -n "${GITHUB_REPOSITORY:-}" ]; then
cat <<GITHUB_EOF

GitHub Details:
- Repository: ${GITHUB_REPOSITORY}
- Branch: ${GITHUB_REF_NAME:-main}
- Commit: ${GITHUB_SHA:-unknown}
- Actor: ${GITHUB_ACTOR:-system}
- Run ID: ${GITHUB_RUN_ID:-N/A}
GITHUB_EOF
fi)

Timestamp: $(date)
EOF
)
    
    # Using mail command (if available)
    if command -v mail >/dev/null 2>&1; then
        echo "$body" | mail -s "$subject" "${EMAIL_RECIPIENT}"
        log "Email notification sent successfully"
    else
        log "Mail command not available, skipping email notification"
    fi
}

# Function to log notification to file
log_notification() {
    local log_file="${NOTIFICATION_LOG_FILE:-/tmp/notifications.log}"
    local log_entry
    
    log_entry=$(jq -n \
        --arg timestamp "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
        --arg app_name "$APP_NAME" \
        --arg notification_type "$NOTIFICATION_TYPE" \
        --arg status "$STATUS" \
        --arg message "$MESSAGE" \
        --arg environment "$ENVIRONMENT" \
        --arg github_repo "${GITHUB_REPOSITORY:-}" \
        --arg github_run_id "${GITHUB_RUN_ID:-}" \
        '{
            timestamp: $timestamp,
            app_name: $app_name,
            type: $notification_type,
            status: $status,
            message: $message,
            environment: $environment,
            github: {
                repository: $github_repo,
                run_id: $github_run_id
            }
        }'
    )
    
    echo "$log_entry" >> "$log_file"
    log "Notification logged to $log_file"
}

# Function to send MS Teams notification
send_teams_notification() {
    if [ -z "${TEAMS_WEBHOOK_URL:-}" ]; then
        log "Teams webhook URL not configured, skipping Teams notification"
        return 0
    fi
    
    local emoji
    emoji=$(get_emoji "$STATUS")
    local color
    color=$(get_color "$STATUS")
    
    local payload
    payload=$(jq -n \
        --arg emoji "$emoji" \
        --arg app_name "$APP_NAME" \
        --arg notification_type "$NOTIFICATION_TYPE" \
        --arg status "$STATUS" \
        --arg message "$MESSAGE" \
        --arg color "$color" \
        '{
            "@type": "MessageCard",
            "@context": "http://schema.org/extensions",
            "themeColor": $color,
            "summary": "\($emoji) \($app_name) - \($notification_type)",
            "sections": [
                {
                    "activityTitle": "\($emoji) \($app_name)",
                    "activitySubtitle": "\($notification_type | ascii_upcase) - \($status | ascii_upcase)",
                    "text": $message,
                    "markdown": true
                }
            ]
        }'
    )
    
    if curl -X POST -H 'Content-type: application/json' \
            --data "$payload" \
            --max-time 10 \
            --silent \
            --fail \
            "${TEAMS_WEBHOOK_URL}"; then
        log "Teams notification sent successfully"
    else
        error "Failed to send Teams notification"
        return 1
    fi
}

# Main notification function
main() {
    log "Sending ${NOTIFICATION_TYPE} notification with status: ${STATUS}"
    
    local success_count=0
    local total_count=0
    
    # Send to configured channels
    if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
        ((total_count++))
        if send_slack_notification; then
            ((success_count++))
        fi
    fi
    
    if [ -n "${DISCORD_WEBHOOK_URL:-}" ]; then
        ((total_count++))
        if send_discord_notification; then
            ((success_count++))
        fi
    fi
    
    if [ -n "${TEAMS_WEBHOOK_URL:-}" ]; then
        ((total_count++))
        if send_teams_notification; then
            ((success_count++))
        fi
    fi
    
    if [ -n "${EMAIL_RECIPIENT:-}" ]; then
        ((total_count++))
        if send_email_notification; then
            ((success_count++))
        fi
    fi
    
    # Always log notification
    log_notification
    
    log "Notification sent to ${success_count}/${total_count} configured channels"
    
    if [ $success_count -eq 0 ] && [ $total_count -gt 0 ]; then
        error "Failed to send notifications to any channel"
        return 1
    fi
}

# Help function
show_help() {
    cat <<EOF
Usage: $0 <type> <status> <message>

Arguments:
  type      Type of notification (deployment, testing, security, etc.)
  status    Status (success, failure, warning, info)
  message   Notification message

Environment Variables:
  SLACK_WEBHOOK_URL     Slack webhook URL
  DISCORD_WEBHOOK_URL   Discord webhook URL
  TEAMS_WEBHOOK_URL     Microsoft Teams webhook URL
  EMAIL_RECIPIENT       Email recipient address
  EMAIL_SMTP_HOST       SMTP server host
  APP_NAME             Application name (default: Slack Archive)
  ENVIRONMENT          Environment name (default: production)
  NOTIFICATION_LOG_FILE Log file path (default: /tmp/notifications.log)

Examples:
  $0 deployment success "Application deployed successfully"
  $0 testing failure "Unit tests failed"
  $0 security warning "Security vulnerabilities detected"
EOF
}

# Command line argument handling
case "${1:-}" in
    "-h"|"--help"|"help")
        show_help
        exit 0
        ;;
    "")
        error "Missing arguments. Use --help for usage information."
        exit 1
        ;;
    *)
        main "$@"
        ;;
esac