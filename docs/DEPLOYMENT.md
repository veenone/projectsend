# ProjectSend Production Deployment Guide

This guide provides comprehensive instructions for deploying ProjectSend in production environments, covering both traditional server deployments and modern Docker-based containerized deployments.

## Table of Contents

- [Deployment Overview](#deployment-overview)
- [Prerequisites](#prerequisites)
- [Traditional Deployment](#traditional-deployment)
- [Docker Deployment](#docker-deployment)
- [Environment Configuration](#environment-configuration)
- [Database Setup](#database-setup)
- [Security Configuration](#security-configuration)
- [Performance Optimization](#performance-optimization)
- [SSL/TLS Setup](#ssltls-setup)
- [Monitoring and Logging](#monitoring-and-logging)
- [Backup and Maintenance](#backup-and-maintenance)
- [Troubleshooting](#troubleshooting)

## Deployment Overview

ProjectSend supports multiple deployment architectures:

- **Traditional Deployment**: LAMP/LEMP stack with Apache/Nginx + PHP-FPM
- **Docker Deployment**: Containerized deployment with Docker Compose
- **Kubernetes Deployment**: Scalable container orchestration (advanced)
- **Cloud Deployment**: AWS, Azure, GCP with managed services

### Recommended Architectures

**Small to Medium Organizations (< 1000 users):**
- Single server deployment
- Apache/Nginx + PHP-FPM + MySQL
- File storage on local filesystem or NFS

**Large Organizations (1000+ users):**
- Multi-tier deployment
- Load balancer + multiple app servers
- Dedicated database server
- Object storage (S3, Azure Blob, GCS)
- Redis for session management

**Enterprise/High Availability:**
- Kubernetes cluster deployment
- Auto-scaling application pods
- Managed database services
- CDN for file delivery
- Multi-region deployment

## Prerequisites

### System Requirements

**Minimum Production Requirements:**
- **CPU**: 2 cores (4+ recommended for high load)
- **RAM**: 4GB (8GB+ recommended)
- **Storage**: 100GB+ (depends on file storage needs)
- **Network**: 100Mbps+ bandwidth

**Software Requirements:**
- **OS**: Ubuntu 20.04+ LTS, CentOS 8+, RHEL 8+, or Debian 11+
- **PHP**: 7.4+ (PHP 8.1+ recommended)
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 8.0+ or MariaDB 10.6+
- **SSL Certificate**: Let's Encrypt or commercial certificate

### Security Prerequisites

- Firewall configured (only HTTP/HTTPS ports open)
- Regular security updates enabled
- Dedicated system user for application
- Secure file permissions
- Database access restricted

## Traditional Deployment

### 1. Server Preparation

**Update System:**
```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
# or for newer versions
sudo dnf update -y
```

**Install Required Packages:**
```bash
# Ubuntu/Debian
sudo apt install -y apache2 mysql-server php8.1 php8.1-fpm php8.1-mysql \
    php8.1-gd php8.1-mbstring php8.1-zip php8.1-xml php8.1-curl \
    php8.1-json php8.1-intl php8.1-ldap php8.1-openssl php8.1-fileinfo \
    unzip curl wget git

# CentOS/RHEL
sudo dnf install -y httpd mysql-server php php-fpm php-mysqli php-gd \
    php-mbstring php-zip php-xml php-curl php-json php-intl php-ldap \
    unzip curl wget git
```

**Create Application User:**
```bash
sudo useradd -r -s /bin/false -d /var/www/projectsend projectsend
sudo mkdir -p /var/www/projectsend
sudo chown projectsend:projectsend /var/www/projectsend
```

### 2. Application Installation

**Download and Extract:**
```bash
cd /tmp
wget https://github.com/projectsend/projectsend/archive/refs/heads/main.zip
unzip main.zip
sudo mv projectsend-main/* /var/www/projectsend/
sudo chown -R projectsend:www-data /var/www/projectsend
```

**Install Dependencies:**
```bash
cd /var/www/projectsend
sudo -u projectsend composer install --no-dev --optimize-autoloader
sudo -u projectsend npm install --production
sudo -u projectsend gulp build
```

### 3. Apache Configuration

**Create Virtual Host:**
```bash
sudo tee /etc/apache2/sites-available/projectsend.conf << 'EOF'
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/projectsend
    
    <Directory /var/www/projectsend>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Security headers
        Header always set X-Content-Type-Options nosniff
        Header always set X-Frame-Options DENY
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    </Directory>
    
    # Restrict access to sensitive directories
    <Directory /var/www/projectsend/includes>
        Require all denied
    </Directory>
    
    <Directory /var/www/projectsend/vendor>
        Require all denied
    </Directory>
    
    # PHP settings
    php_value upload_max_filesize 500M
    php_value post_max_size 500M
    php_value memory_limit 512M
    php_value max_execution_time 300
    
    ErrorLog ${APACHE_LOG_DIR}/projectsend_error.log
    CustomLog ${APACHE_LOG_DIR}/projectsend_access.log combined
</VirtualHost>
EOF
```

**Enable Site and Modules:**
```bash
sudo a2enmod rewrite headers ssl
sudo a2ensite projectsend.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

### 4. Nginx Configuration (Alternative)

**Create Server Block:**
```bash
sudo tee /etc/nginx/sites-available/projectsend << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/projectsend;
    index index.php index.html;
    
    client_max_body_size 500M;
    client_body_timeout 300s;
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }
    
    # Deny access to sensitive directories
    location ~ ^/(includes|vendor|\.git) {
        deny all;
        return 404;
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
        return 404;
    }
    
    # Logging
    access_log /var/log/nginx/projectsend_access.log;
    error_log /var/log/nginx/projectsend_error.log;
}
EOF
```

**Enable Site:**
```bash
sudo ln -s /etc/nginx/sites-available/projectsend /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5. File Permissions

```bash
# Set proper ownership
sudo chown -R projectsend:www-data /var/www/projectsend

# Set directory permissions
sudo find /var/www/projectsend -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/projectsend -type f -exec chmod 644 {} \;

# Writable directories
sudo chmod -R 775 /var/www/projectsend/upload
sudo chmod -R 775 /var/www/projectsend/logs
sudo chmod 666 /var/www/projectsend/includes/sys.config.php
```

## Docker Deployment

### 1. Docker Compose Configuration

**Create docker-compose.yml:**
```yaml
version: '3.8'

services:
  projectsend-app:
    image: projectsend/projectsend:latest
    container_name: projectsend-app
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    environment:
      - DB_HOST=projectsend-db
      - DB_NAME=projectsend
      - DB_USER=projectsend
      - DB_PASSWORD=secure_password_here
      - SITE_URL=https://yourdomain.com
      - MAX_UPLOAD_SIZE=500M
      - PHP_MEMORY_LIMIT=512M
    volumes:
      - projectsend-data:/var/www/projectsend/upload
      - projectsend-config:/var/www/projectsend/includes
      - ./ssl:/etc/ssl/certs/projectsend:ro
    depends_on:
      - projectsend-db
      - projectsend-redis
    networks:
      - projectsend-network

  projectsend-db:
    image: mysql:8.0
    container_name: projectsend-db
    restart: unless-stopped
    environment:
      - MYSQL_ROOT_PASSWORD=root_password_here
      - MYSQL_DATABASE=projectsend
      - MYSQL_USER=projectsend
      - MYSQL_PASSWORD=secure_password_here
      - MYSQL_CHARACTER_SET_SERVER=utf8mb4
      - MYSQL_COLLATION_SERVER=utf8mb4_unicode_ci
    volumes:
      - projectsend-db-data:/var/lib/mysql
      - ./mysql/conf.d:/etc/mysql/conf.d:ro
    networks:
      - projectsend-network
    command: --sql-mode=""

  projectsend-redis:
    image: redis:7-alpine
    container_name: projectsend-redis
    restart: unless-stopped
    command: redis-server --requirepass redis_password_here
    volumes:
      - projectsend-redis-data:/data
    networks:
      - projectsend-network

  projectsend-nginx:
    image: nginx:alpine
    container_name: projectsend-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./nginx/conf.d:/etc/nginx/conf.d:ro
      - ./ssl:/etc/ssl/certs/projectsend:ro
      - projectsend-data:/var/www/projectsend/upload:ro
    depends_on:
      - projectsend-app
    networks:
      - projectsend-network

volumes:
  projectsend-data:
  projectsend-config:
  projectsend-db-data:
  projectsend-redis-data:

networks:
  projectsend-network:
    driver: bridge
```

### 2. Nginx Proxy Configuration

**Create nginx/conf.d/projectsend.conf:**
```nginx
upstream projectsend {
    server projectsend-app:80;
}

server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    
    ssl_certificate /etc/ssl/certs/projectsend/fullchain.pem;
    ssl_certificate_key /etc/ssl/certs/projectsend/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    client_max_body_size 500M;
    client_body_timeout 300s;
    
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    
    location / {
        proxy_pass http://projectsend;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300s;
        proxy_connect_timeout 75s;
    }
    
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        proxy_pass http://projectsend;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 3. MySQL Configuration

**Create mysql/conf.d/projectsend.cnf:**
```ini
[mysqld]
# Performance settings
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_file_per_table = 1

# Connection settings
max_connections = 200
wait_timeout = 28800
interactive_timeout = 28800

# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Security
bind-address = 0.0.0.0
skip-name-resolve

[client]
default-character-set = utf8mb4
```

### 4. Deploy with Docker Compose

```bash
# Create necessary directories
mkdir -p ssl nginx/conf.d mysql/conf.d

# Deploy the stack
docker-compose up -d

# View logs
docker-compose logs -f

# Scale application (if needed)
docker-compose up -d --scale projectsend-app=3
```

## Environment Configuration

### 1. Production Configuration File

**Create includes/sys.config.php:**
```php
<?php
/**
 * ProjectSend Production Configuration
 */

// Database configuration
define('DB_DRIVER', 'mysql');
define('DB_NAME', 'projectsend');
define('DB_HOST', 'localhost'); // or IP address
define('DB_USER', 'projectsend_user');
define('DB_PASSWORD', 'secure_database_password');
define('DB_PORT', '3306');

// Site configuration
define('SITE_TITLE', 'Your Organization File Sharing');
define('BASE_URL', 'https://files.yourdomain.com/');
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// Security settings
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here');
define('HASH_SALT', 'your-unique-salt-string-here');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_COMPLEXITY', true);
define('FORCE_HTTPS', true);
define('SECURE_COOKIES', true);

// File upload settings
define('MAX_FILESIZE', 500 * 1024 * 1024); // 500MB in bytes
define('ALLOWED_FILE_TYPES', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png,gif,zip,rar');
define('UPLOAD_FOLDER', 'upload/files/');
define('THUMBNAILS_FOLDER', 'upload/thumbnails/');

// Email configuration (SMTP)
define('MAIL_METHOD', 'smtp');
define('SMTP_HOST', 'smtp.yourdomain.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@yourdomain.com');
define('SMTP_PASS', 'smtp_password_here');
define('SMTP_SECURITY', 'tls');

// OIDC Configuration
define('OIDC_ENABLED', true);
define('OIDC_PROVIDER', 'keycloak'); // keycloak, azure, auth0, generic
define('OIDC_CLIENT_ID', 'projectsend');
define('OIDC_CLIENT_SECRET', 'your-oidc-client-secret');
define('OIDC_ISSUER', 'https://keycloak.yourdomain.com/realms/projectsend');
define('OIDC_AUTO_PROVISION', true);
define('OIDC_DEFAULT_ROLE', 'client');

// Performance settings
define('ENABLE_CACHE', true);
define('CACHE_TYPE', 'redis'); // file, redis, memcached
define('REDIS_HOST', 'localhost');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', 'redis_password_here');

// Logging
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', 'logs/projectsend.log');
define('AUDIT_LOG', true);

// Production settings
define('DEBUG', false);
define('DEVELOPMENT_ENV', false);
define('ERROR_REPORTING', false);
```

### 2. Environment Variables (Docker)

**Create .env file:**
```bash
# Database
DB_HOST=projectsend-db
DB_NAME=projectsend
DB_USER=projectsend
DB_PASSWORD=your_secure_db_password

# Application
SITE_URL=https://files.yourdomain.com
ADMIN_EMAIL=admin@yourdomain.com
ENCRYPTION_KEY=your-32-character-encryption-key
SESSION_TIMEOUT=3600

# OIDC
OIDC_ENABLED=true
OIDC_PROVIDER=keycloak
OIDC_CLIENT_ID=projectsend
OIDC_CLIENT_SECRET=your-oidc-client-secret
OIDC_ISSUER=https://keycloak.yourdomain.com/realms/projectsend

# Redis
REDIS_HOST=projectsend-redis
REDIS_PASSWORD=your_redis_password

# File Uploads
MAX_UPLOAD_SIZE=500M
PHP_MEMORY_LIMIT=512M
```

## Database Setup

### 1. MySQL/MariaDB Installation and Configuration

**Create Database and User:**
```sql
-- Connect as root
mysql -u root -p

-- Create database
CREATE DATABASE projectsend CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user
CREATE USER 'projectsend'@'localhost' IDENTIFIED BY 'secure_password_here';
CREATE USER 'projectsend'@'%' IDENTIFIED BY 'secure_password_here';

-- Grant permissions
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP ON projectsend.* TO 'projectsend'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP ON projectsend.* TO 'projectsend'@'%';

-- Apply changes
FLUSH PRIVILEGES;
```

**Optimize MySQL Configuration:**
```ini
# /etc/mysql/mysql.conf.d/projectsend.cnf
[mysqld]
# Performance
innodb_buffer_pool_size = 2G
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
innodb_file_per_table = 1
query_cache_size = 128M
query_cache_type = 1

# Connection limits
max_connections = 500
max_connect_errors = 999999
wait_timeout = 28800

# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Security
bind-address = 127.0.0.1
skip-name-resolve = 1

# Binary logging (for backups/replication)
log-bin = /var/log/mysql/mysql-bin.log
expire_logs_days = 7
max_binlog_size = 500M
```

### 2. Database Migration and Setup

**Run Installation Script:**
```bash
# Via web interface (recommended for first-time setup)
https://yourdomain.com/install/

# Or via CLI
php install/cli-installer.php --db-host=localhost --db-name=projectsend --db-user=projectsend --db-pass=secure_password
```

### 3. Database Backup Strategy

**Automated Backup Script:**
```bash
#!/bin/bash
# /usr/local/bin/projectsend-backup.sh

BACKUP_DIR="/var/backups/projectsend"
DB_NAME="projectsend"
DB_USER="projectsend"
DB_PASS="secure_password"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u $DB_USER -p$DB_PASS --single-transaction --routines --triggers $DB_NAME > $BACKUP_DIR/projectsend_db_$DATE.sql

# File backup
tar -czf $BACKUP_DIR/projectsend_files_$DATE.tar.gz /var/www/projectsend/upload/

# Cleanup old backups (keep 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

# Log backup completion
echo "$(date): Backup completed successfully" >> /var/log/projectsend-backup.log
```

**Set up cron job:**
```bash
# Daily backup at 2 AM
0 2 * * * /usr/local/bin/projectsend-backup.sh
```

## Security Configuration

### 1. File System Security

```bash
# Remove unnecessary files
sudo rm -rf /var/www/projectsend/.git
sudo rm -f /var/www/projectsend/composer.json
sudo rm -f /var/www/projectsend/gulpfile.js

# Secure permissions
sudo chown -R projectsend:www-data /var/www/projectsend
sudo chmod -R 755 /var/www/projectsend
sudo chmod -R 775 /var/www/projectsend/upload
sudo chmod -R 775 /var/www/projectsend/logs
sudo chmod 600 /var/www/projectsend/includes/sys.config.php
```

### 2. Web Server Hardening

**Apache Security Headers (.htaccess):**
```apache
# Security headers
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'"

# Hide server information
ServerTokens Prod
ServerSignature Off

# Prevent access to sensitive files
<Files ~ "^(composer\.|gulpfile\.|\.env|\.git)">
    Require all denied
</Files>

# Block common attack patterns
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{QUERY_STRING} (\<|%3C).*script.*(\>|%3E) [NC,OR]
    RewriteCond %{QUERY_STRING} GLOBALS(=|\[|\%[0-9A-Z]{0,2}) [OR]
    RewriteCond %{QUERY_STRING} _REQUEST(=|\[|\%[0-9A-Z]{0,2}) [OR]
    RewriteRule ^(.*)$ index.php [F,L]
</IfModule>
```

### 3. Database Security

```sql
-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Remove test database
DROP DATABASE IF EXISTS test;

-- Set secure password policy
SET GLOBAL validate_password.policy = 'STRONG';
SET GLOBAL validate_password.length = 12;

-- Enable query logging for security monitoring
SET GLOBAL general_log = 'ON';
SET GLOBAL general_log_file = '/var/log/mysql/query.log';
```

### 4. Firewall Configuration

**UFW (Ubuntu):**
```bash
# Enable firewall
sudo ufw --force enable

# Allow SSH (adjust port as needed)
sudo ufw allow 22/tcp

# Allow HTTP and HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow MySQL only from application server (if separate)
sudo ufw allow from 10.0.0.10 to any port 3306

# Deny all other traffic
sudo ufw default deny incoming
sudo ufw default allow outgoing
```

**iptables (CentOS/RHEL):**
```bash
# Create firewall rules
cat > /etc/iptables.rules << 'EOF'
*filter
:INPUT DROP [0:0]
:FORWARD DROP [0:0]
:OUTPUT ACCEPT [0:0]

# Allow loopback
-A INPUT -i lo -j ACCEPT

# Allow established connections
-A INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT

# Allow SSH
-A INPUT -p tcp --dport 22 -j ACCEPT

# Allow HTTP and HTTPS
-A INPUT -p tcp --dport 80 -j ACCEPT
-A INPUT -p tcp --dport 443 -j ACCEPT

COMMIT
EOF

# Apply rules
iptables-restore < /etc/iptables.rules
```

## Performance Optimization

### 1. PHP Optimization

**PHP-FPM Configuration (/etc/php/8.1/fpm/pool.d/projectsend.conf):**
```ini
[projectsend]
user = projectsend
group = www-data
listen = /var/run/php/php8.1-fpm-projectsend.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10
pm.max_requests = 500

; Performance settings
php_admin_value[memory_limit] = 512M
php_admin_value[upload_max_filesize] = 500M
php_admin_value[post_max_size] = 500M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300

; Security
php_admin_value[open_basedir] = "/var/www/projectsend:/tmp"
php_admin_flag[allow_url_fopen] = off
php_admin_flag[allow_url_include] = off
```

**OPcache Configuration (/etc/php/8.1/fpm/conf.d/10-opcache.ini):**
```ini
zend_extension=opcache.so
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=512
opcache.interned_strings_buffer=64
opcache.max_accelerated_files=32531
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=0
```

### 2. Web Server Optimization

**Apache mod_deflate and mod_expires:**
```apache
# Enable compression
LoadModule deflate_module modules/mod_deflate.so
<Location />
    SetOutputFilter DEFLATE
    SetEnvIfNoCase Request_URI \
        \.(?:gif|jpe?g|png)$ no-gzip dont-vary
    SetEnvIfNoCase Request_URI \
        \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
</Location>

# Enable caching
LoadModule expires_module modules/mod_expires.so
ExpiresActive On
ExpiresByType image/jpg "access plus 1 month"
ExpiresByType image/jpeg "access plus 1 month"
ExpiresByType image/gif "access plus 1 month"
ExpiresByType image/png "access plus 1 month"
ExpiresByType text/css "access plus 1 month"
ExpiresByType application/pdf "access plus 1 month"
ExpiresByType text/javascript "access plus 1 month"
ExpiresByType application/javascript "access plus 1 month"
```

### 3. Database Performance Tuning

**Monitor Performance:**
```sql
-- Check slow queries
SHOW VARIABLES LIKE 'slow_query_log';
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Monitor performance
SHOW PROCESSLIST;
SHOW ENGINE INNODB STATUS;

-- Optimize tables
ANALYZE TABLE tbl_users;
ANALYZE TABLE tbl_files;
OPTIMIZE TABLE tbl_files;
```

### 4. Caching Configuration

**Redis Configuration (/etc/redis/redis.conf):**
```bash
# Memory
maxmemory 1gb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# Security
requirepass redis_password_here
bind 127.0.0.1

# Performance
tcp-keepalive 60
timeout 300
```

## SSL/TLS Setup

### 1. Let's Encrypt Certificate

**Install Certbot:**
```bash
# Ubuntu/Debian
sudo apt install certbot python3-certbot-apache

# CentOS/RHEL
sudo dnf install certbot python3-certbot-apache
```

**Obtain Certificate:**
```bash
# For Apache
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# For Nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Manual certificate only
sudo certbot certonly --standalone -d yourdomain.com -d www.yourdomain.com
```

**Auto-renewal:**
```bash
# Test renewal
sudo certbot renew --dry-run

# Add to cron
echo "0 12 * * * /usr/bin/certbot renew --quiet" | sudo tee -a /var/spool/cron/crontabs/root
```

### 2. Commercial SSL Certificate

**Apache SSL Configuration:**
```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/projectsend
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/yourdomain.com.crt
    SSLCertificateKeyFile /etc/ssl/private/yourdomain.com.key
    SSLCertificateChainFile /etc/ssl/certs/intermediate.crt
    
    # Modern SSL configuration
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder off
    SSLSessionTickets off
    
    # HSTS
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
</VirtualHost>
```

## Monitoring and Logging

### 1. Application Logging

**Configure Logging in sys.config.php:**
```php
// Logging configuration
define('LOG_LEVEL', 'INFO');
define('LOG_FILE', '/var/log/projectsend/application.log');
define('ERROR_LOG_FILE', '/var/log/projectsend/error.log');
define('AUDIT_LOG_FILE', '/var/log/projectsend/audit.log');
define('OIDC_LOG_FILE', '/var/log/projectsend/oidc.log');

// Log rotation
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('LOG_MAX_FILES', 10);
```

**Create Log Directory:**
```bash
sudo mkdir -p /var/log/projectsend
sudo chown projectsend:www-data /var/log/projectsend
sudo chmod 775 /var/log/projectsend
```

**Logrotate Configuration:**
```bash
sudo tee /etc/logrotate.d/projectsend << 'EOF'
/var/log/projectsend/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    copytruncate
    create 644 projectsend www-data
}
EOF
```

### 2. System Monitoring

**Install Monitoring Tools:**
```bash
# Install monitoring stack
sudo apt install -y prometheus node-exporter grafana-server

# Or use external monitoring
# - New Relic
# - DataDog
# - AWS CloudWatch
```

**Health Check Script:**
```bash
#!/bin/bash
# /usr/local/bin/projectsend-health-check.sh

APP_URL="https://yourdomain.com"
DB_HOST="localhost"
DB_NAME="projectsend"
DB_USER="projectsend"
DB_PASS="password"

# Check web server response
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" $APP_URL)
if [ $HTTP_STATUS -ne 200 ]; then
    echo "ERROR: Web server returned $HTTP_STATUS"
    exit 1
fi

# Check database connectivity
mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "SELECT 1" $DB_NAME > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "ERROR: Database connection failed"
    exit 1
fi

# Check file system space
DISK_USAGE=$(df /var/www/projectsend | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 90 ]; then
    echo "WARNING: Disk usage is ${DISK_USAGE}%"
fi

echo "OK: All checks passed"
```

### 3. Security Monitoring

**Fail2ban Configuration:**
```bash
# Install fail2ban
sudo apt install fail2ban

# Configure for ProjectSend
sudo tee /etc/fail2ban/jail.d/projectsend.local << 'EOF'
[projectsend]
enabled = true
port = http,https
filter = projectsend
logpath = /var/log/apache2/projectsend_access.log
maxretry = 5
bantime = 3600
findtime = 600
EOF

# Create filter
sudo tee /etc/fail2ban/filter.d/projectsend.conf << 'EOF'
[Definition]
failregex = ^<HOST> - - \[.*\] "(GET|POST) /login\.php.*" (401|403|404)
            ^<HOST> - - \[.*\] "(GET|POST) /admin/.*" (401|403)
ignoreregex =
EOF
```

## Backup and Maintenance

### 1. Automated Backup System

**Complete Backup Script:**
```bash
#!/bin/bash
# /usr/local/bin/projectsend-backup.sh

# Configuration
BACKUP_DIR="/var/backups/projectsend"
S3_BUCKET="your-backup-bucket"
APP_DIR="/var/www/projectsend"
DB_NAME="projectsend"
DB_USER="projectsend"
DB_PASS="secure_password"
RETENTION_DAYS=30

# Create timestamp
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup with compression
mysqldump -h localhost -u $DB_USER -p$DB_PASS \
    --single-transaction \
    --routines \
    --triggers \
    --add-drop-database \
    --databases $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Application files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
    --exclude='*/cache/*' \
    --exclude='*/logs/*' \
    --exclude='*/tmp/*' \
    $APP_DIR/upload/ \
    $APP_DIR/includes/sys.config.php

# Configuration backup
tar -czf $BACKUP_DIR/config_$DATE.tar.gz \
    /etc/apache2/sites-available/projectsend.conf \
    /etc/php/8.1/fpm/pool.d/projectsend.conf \
    $APP_DIR/includes/

# Upload to S3 (optional)
aws s3 sync $BACKUP_DIR s3://$S3_BUCKET/projectsend/

# Cleanup old backups
find $BACKUP_DIR -name "*.gz" -mtime +$RETENTION_DAYS -delete

# Log backup completion
logger "ProjectSend backup completed: $DATE"
```

### 2. Maintenance Tasks

**Weekly Maintenance Script:**
```bash
#!/bin/bash
# /usr/local/bin/projectsend-maintenance.sh

# Cleanup temporary files
find /var/www/projectsend/upload/temp -name "*.tmp" -mtime +1 -delete

# Optimize database tables
mysql -u projectsend -p"$DB_PASS" projectsend << 'EOF'
OPTIMIZE TABLE tbl_users;
OPTIMIZE TABLE tbl_files;
OPTIMIZE TABLE tbl_downloads;
ANALYZE TABLE tbl_users;
ANALYZE TABLE tbl_files;
EOF

# Cleanup old log files
find /var/log/projectsend -name "*.log.*" -mtime +30 -delete

# Update file permissions
chown -R projectsend:www-data /var/www/projectsend
find /var/www/projectsend -type d -exec chmod 755 {} \;
find /var/www/projectsend -type f -exec chmod 644 {} \;
chmod -R 775 /var/www/projectsend/upload

# Restart services if needed
systemctl reload php8.1-fpm
systemctl reload apache2

logger "ProjectSend maintenance completed"
```

### 3. Update Procedures

**Application Update Script:**
```bash
#!/bin/bash
# /usr/local/bin/projectsend-update.sh

APP_DIR="/var/www/projectsend"
BACKUP_DIR="/var/backups/projectsend/pre-update"
DATE=$(date +%Y%m%d_%H%M%S)

# Create pre-update backup
mkdir -p $BACKUP_DIR
tar -czf $BACKUP_DIR/projectsend_$DATE.tar.gz $APP_DIR

# Put application in maintenance mode
cp $APP_DIR/maintenance.html.sample $APP_DIR/maintenance.html

# Download latest version
cd /tmp
wget https://github.com/projectsend/projectsend/archive/refs/heads/main.zip
unzip main.zip

# Update application files (preserve config)
cp $APP_DIR/includes/sys.config.php /tmp/
rsync -av --exclude='includes/sys.config.php' \
    --exclude='upload/' \
    --exclude='logs/' \
    projectsend-main/ $APP_DIR/

cp /tmp/sys.config.php $APP_DIR/includes/

# Update dependencies
cd $APP_DIR
sudo -u projectsend composer install --no-dev --optimize-autoloader
sudo -u projectsend npm install --production
sudo -u projectsend gulp build

# Run database migrations
php install/migrate.php

# Set permissions
chown -R projectsend:www-data $APP_DIR
chmod 600 $APP_DIR/includes/sys.config.php

# Remove maintenance mode
rm -f $APP_DIR/maintenance.html

# Restart services
systemctl reload php8.1-fpm
systemctl reload apache2

logger "ProjectSend update completed: $DATE"
```

## Troubleshooting

### Common Issues and Solutions

**1. File Upload Issues:**

```bash
# Check PHP settings
php -i | grep -E "(upload_max_filesize|post_max_size|memory_limit|max_execution_time)"

# Check directory permissions
ls -la /var/www/projectsend/upload/
sudo chmod -R 775 /var/www/projectsend/upload/

# Check disk space
df -h /var/www/projectsend/
```

**2. Database Connection Issues:**

```bash
# Test database connection
mysql -h localhost -u projectsend -p projectsend

# Check MySQL service
sudo systemctl status mysql
sudo systemctl restart mysql

# Review MySQL logs
sudo tail -f /var/log/mysql/error.log
```

**3. OIDC Authentication Issues:**

```php
// Enable OIDC debugging in sys.config.php
define('OIDC_DEBUG', true);
define('LOG_LEVEL', 'DEBUG');

// Check OIDC logs
tail -f /var/log/projectsend/oidc.log

// Test provider connection
curl -v https://keycloak.yourdomain.com/realms/projectsend/.well-known/openid_configuration
```

**4. Performance Issues:**

```bash
# Check system resources
top
htop
iotop

# Monitor PHP-FPM
sudo tail -f /var/log/php8.1-fpm.log

# Check slow queries
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
mysql -e "SHOW STATUS LIKE 'Slow_queries';"
```

**5. SSL Certificate Issues:**

```bash
# Check certificate validity
openssl x509 -in /etc/ssl/certs/yourdomain.com.crt -text -noout

# Test SSL configuration
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com

# Check certificate chain
curl -I https://yourdomain.com
```

### Log Analysis

**Common Log Locations:**
- Application: `/var/log/projectsend/`
- Apache: `/var/log/apache2/`
- Nginx: `/var/log/nginx/`
- PHP-FPM: `/var/log/php8.1-fpm.log`
- MySQL: `/var/log/mysql/`
- System: `/var/log/syslog`

**Log Analysis Commands:**
```bash
# Real-time application logs
tail -f /var/log/projectsend/application.log

# Search for errors
grep -i error /var/log/projectsend/* | tail -20

# Analyze access patterns
awk '{print $1}' /var/log/apache2/projectsend_access.log | sort | uniq -c | sort -nr | head -10

# Monitor failed OIDC authentications
grep "OIDC.*failed" /var/log/projectsend/oidc.log
```

This deployment guide provides comprehensive instructions for production deployment of ProjectSend. For development setup and configuration details, refer to DEVELOP.md and CONFIGURATION.md respectively.

---

**Next Steps After Deployment:**

1. Complete the initial setup wizard
2. Configure authentication providers (especially OIDC)
3. Set up monitoring and alerting
4. Implement backup procedures
5. Conduct security audit
6. Performance testing and optimization
7. User training and documentation