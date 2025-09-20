# GitHub Actions CI/CD Pipeline Setup Guide

This guide will help you set up the complete GitHub Actions CI/CD pipeline for the Slack Archive application with automated testing, security scanning, deployment, and notifications.

## 🏗️ Pipeline Overview

The CI/CD pipeline consists of three main workflows:

1. **CI Workflow** (`.github/workflows/ci.yml`) - Automated testing and quality checks
2. **Deployment Workflow** (`.github/workflows/deploy.yml`) - Production deployment
3. **Security Workflow** (`.github/workflows/security.yml`) - Security scanning and vulnerability checks

## 📋 Prerequisites

### Server Requirements
- VPS with Ubuntu 20.04+ or similar Linux distribution
- Docker and Docker Compose installed
- SSH access configured
- Domain name with SSL certificate (recommended)

### Required Tools
- PostgreSQL 16+
- Redis 7+
- Nginx
- Node.js 18+
- PHP 8.2+

## 🔐 GitHub Secrets Configuration

Add the following secrets to your GitHub repository (`Settings > Secrets and variables > Actions`):

### Deployment Secrets
```
DEPLOY_HOST=your-server.com
DEPLOY_USER=deploy
DEPLOY_SSH_KEY=<your-private-ssh-key>
DEPLOY_PATH=/var/www/slack-archive
```

### Database Configuration
```
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=slack_archive_prod
DB_USERNAME=slack_user
DB_PASSWORD=<secure-password>
```

### Application Configuration
```
APP_URL=https://your-domain.com
APP_KEY=<generated-laravel-app-key>
SLACK_CLIENT_ID=<your-slack-app-client-id>
SLACK_CLIENT_SECRET=<your-slack-app-client-secret>
```

### Notification Configuration
```
SLACK_WEBHOOK_URL=<slack-webhook-for-notifications>
DISCORD_WEBHOOK_URL=<discord-webhook-for-notifications>
```

### Security Scanning
```
OWASP_ZAP_API_KEY=<optional-zap-api-key>
```

## 🚀 Initial Server Setup

### 1. Create Deployment User

```bash
# On your server
sudo adduser deploy
sudo usermod -aG docker deploy
sudo usermod -aG www-data deploy

# Create SSH directory
sudo mkdir -p /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
sudo chown deploy:deploy /home/deploy/.ssh

# Add your public key to authorized_keys
sudo nano /home/deploy/.ssh/authorized_keys
sudo chmod 600 /home/deploy/.ssh/authorized_keys
sudo chown deploy:deploy /home/deploy/.ssh/authorized_keys
```

### 2. Create Deployment Directory Structure

```bash
sudo mkdir -p /var/www/slack-archive/{releases,shared,shared/storage/app,shared/storage/framework,shared/storage/logs,shared/uploads}
sudo chown -R deploy:www-data /var/www/slack-archive
sudo chmod -R 755 /var/www/slack-archive
```

### 3. Create Environment File

```bash
# Create production environment file
sudo nano /var/www/slack-archive/shared/.env

# Add your production environment variables
APP_NAME="Slack Archive"
APP_ENV=production
APP_KEY=<your-app-key>
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=slack_archive_prod
DB_USERNAME=slack_user
DB_PASSWORD=<secure-password>

REDIS_HOST=localhost
REDIS_PASSWORD=null
REDIS_PORT=6379

# Add other required environment variables...
```

## ⚙️ Workflow Configuration

### CI Workflow Features

- **PHP Quality & Tests**
  - Laravel Pint code style checking
  - PHPStan static analysis
  - PHPUnit tests with coverage reporting
  
- **Frontend Quality & Tests**
  - ESLint code quality checking
  - Prettier formatting validation
  - TypeScript type checking
  - Vitest unit tests with coverage
  - Asset building verification

- **End-to-End Tests**
  - Playwright browser testing across multiple browsers
  - Mobile viewport testing
  - Cross-browser compatibility

- **Security Scanning**
  - PHP dependency vulnerability scanning
  - Node.js dependency auditing
  - Static code analysis for security issues

### Deployment Workflow Features

- **Pre-deployment Validation**
  - Health checks on current deployment
  - Database backup creation
  - Environment variable validation

- **Build & Test**
  - Complete application build
  - Test suite execution
  - Asset optimization

- **VPS Deployment**
  - Zero-downtime deployment using symlinks
  - Atomic deployment with rollback capability
  - Database migrations
  - Cache optimization

- **Post-deployment**
  - Health checks on new deployment
  - Automatic rollback on failure
  - Slack/Discord notifications

### Security Workflow Features

- **Multi-layer Security Scanning**
  - Dependency vulnerability scanning (PHP & Node.js)
  - Static code analysis (PHPStan, ESLint Security)
  - Container image scanning (Trivy)
  - Infrastructure scanning (Nuclei)
  - Web application penetration testing (OWASP ZAP)

## 🔧 Local Development Setup

### Install Development Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Install testing tools
npm install -g @playwright/test
npx playwright install
```

### Environment Configuration Files

Create the following configuration files:

1. **Vitest Config** (`vitest.config.ts`)
2. **Playwright Config** (`playwright.config.ts`)
3. **ESLint Config** (`.eslintrc.js`)
4. **Laravel Pint Config** (`pint.json`)
5. **PHPStan Config** (`phpstan.neon`)

## 🧪 Testing the Pipeline

### Run Tests Locally

```bash
# PHP tests
php artisan test

# Frontend tests
npm run test:unit

# E2E tests (requires application running)
npm run test:e2e

# Code quality checks
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run lint
```

### Manual Deployment Test

```bash
# Test deployment script
./scripts/deploy.sh

# Test health check
./scripts/health-check.sh

# Test notifications
./scripts/notify.sh deployment success "Test notification"
```

## 📊 Monitoring and Alerts

### Health Check Endpoint

The application includes a health check endpoint at `/_health` that verifies:
- Database connectivity
- Redis connectivity
- Disk space usage
- Memory usage
- Application status

### Notification Channels

Configure notifications for:
- **Slack**: Team updates and alerts
- **Discord**: Community notifications
- **Email**: Critical alerts (optional)
- **Microsoft Teams**: Enterprise notifications (optional)

### Log Files

Monitor these log files:
- Application logs: `storage/logs/laravel.log`
- Deployment logs: Generated during deployment
- Security scan results: Available in GitHub Actions artifacts

## 🔄 Deployment Process

### Automatic Deployment

1. Push to `main` branch triggers deployment
2. CI tests must pass before deployment
3. Security scans must complete successfully
4. Deployment runs with health checks
5. Automatic rollback on failure
6. Notifications sent to configured channels

### Manual Deployment

```bash
# Deploy specific branch
git push origin feature-branch

# Trigger deployment via GitHub Actions
# Go to Actions tab > Deploy to Production > Run workflow
```

### Rollback Process

```bash
# Automatic rollback on health check failure
# Manual rollback using deployment script
./scripts/deploy.sh rollback

# Or via GitHub Actions
# Go to Actions tab > Deploy to Production > Re-run previous successful deployment
```

## 🛡️ Security Considerations

### Environment Variables

- Store all sensitive data in GitHub Secrets
- Never commit `.env` files to the repository
- Use separate environments for staging/production
- Rotate secrets regularly

### Access Control

- Limit SSH access to deployment user
- Use key-based authentication only
- Configure firewall rules appropriately
- Monitor access logs regularly

### SSL/TLS Configuration

- Use Let's Encrypt for SSL certificates
- Configure HTTPS redirects
- Set secure headers in Nginx configuration
- Implement HSTS (HTTP Strict Transport Security)

## 🐛 Troubleshooting

### Common Issues

1. **SSH Connection Failed**
   - Verify SSH key is correctly configured
   - Check server firewall settings
   - Ensure deploy user has correct permissions

2. **Health Check Failures**
   - Check database connectivity
   - Verify Redis service status
   - Check disk space and memory usage
   - Review application logs

3. **Build Failures**
   - Check Node.js and PHP versions
   - Verify all dependencies are available
   - Review build logs for specific errors

4. **Test Failures**
   - Ensure test database is accessible
   - Check for missing test data
   - Verify browser dependencies for E2E tests

### Debug Mode

To enable debug mode for troubleshooting:

```bash
# Set in GitHub Actions environment variables
DEBUG=true

# Or in deployment script
export DEBUG=1
```

## 📝 Maintenance

### Regular Tasks

- Update dependencies monthly
- Review security scan results weekly
- Monitor disk space and logs daily
- Backup database daily (automated)
- Test rollback procedures monthly

### Updates

- Monitor GitHub Actions for security updates
- Keep Docker images updated
- Update SSL certificates before expiry
- Review and update notification channels

## 🤝 Contributing

When contributing to the CI/CD pipeline:

1. Test changes in a fork first
2. Update documentation for any configuration changes
3. Ensure backward compatibility
4. Add appropriate test coverage
5. Follow security best practices

For questions or support, create an issue in the repository with the `ci/cd` label.

---

This pipeline provides enterprise-grade CI/CD capabilities with comprehensive testing, security scanning, and deployment automation. Regular monitoring and maintenance ensure optimal performance and security.