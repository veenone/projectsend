# ProjectSend Development Guide

This guide provides comprehensive instructions for setting up a ProjectSend development environment and contributing to the project.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Development Environment Setup](#development-environment-setup)
- [Installation](#installation)
- [Build Process](#build-process)
- [Database Setup](#database-setup)
- [Development Server](#development-server)
- [Architecture Overview](#architecture-overview)
- [Code Structure](#code-structure)
- [Contributing Guidelines](#contributing-guidelines)
- [Testing](#testing)
- [Debugging](#debugging)
- [Troubleshooting](#troubleshooting)

## Prerequisites

### System Requirements

**PHP Requirements:**
- PHP 7.4 or higher (PHP 8.1+ recommended)
- Required PHP extensions:
  - `mysqli` or `pdo_mysql` - Database connectivity
  - `gd` or `imagick` - Image processing
  - `mbstring` - Multi-byte string handling
  - `zip` - Archive handling
  - `xml` - XML processing
  - `curl` - HTTP client functionality
  - `json` - JSON handling
  - `openssl` - Encryption and HTTPS
  - `fileinfo` - File type detection
  - `intl` - Internationalization
  - `ldap` - LDAP authentication (optional)

**Database:**
- MySQL 5.7+ or MariaDB 10.3+
- Database user with CREATE, ALTER, INSERT, UPDATE, DELETE, SELECT privileges

**Node.js & Build Tools:**
- Node.js 14+ and npm
- Gulp CLI globally installed

**Web Server:**
- Apache 2.4+ with mod_rewrite enabled
- OR Nginx 1.18+
- OR PHP built-in server for development

### Development Tools (Recommended)

- **IDE/Editor:** VS Code, PHPStorm, or similar with PHP support
- **Version Control:** Git
- **Database Management:** phpMyAdmin, Adminer, or MySQL Workbench
- **API Testing:** Postman or similar for testing OIDC integrations

## Development Environment Setup

### 1. Clone the Repository

```bash
git clone https://github.com/projectsend/projectsend.git
cd projectsend
```

### 2. Environment Configuration

Create a local environment configuration:

```bash
# Copy the example environment file
cp includes/sys.config.sample.php includes/sys.config.php
```

Edit `includes/sys.config.php` with your local development settings:

```php
<?php
// Database configuration
define('DB_DRIVER', 'mysql');
define('DB_NAME', 'projectsend_dev');
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASSWORD', 'your_db_password');

// Development settings
define('DEBUG', true);
define('DEVELOPMENT_ENV', true);

// Base URL for development
define('BASE_URL', 'http://localhost/projectsend/');
```

## Installation

### 1. Install PHP Dependencies

```bash
# Install Composer if not already installed
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install PHP dependencies
composer install --dev
```

### 2. Install Frontend Dependencies

```bash
# Install Node.js dependencies
npm install

# Install Gulp globally if not already installed
npm install -g gulp-cli
```

### 3. Build Frontend Assets

```bash
# Build CSS and JavaScript assets
gulp build

# For development with file watching
gulp watch

# Clean build artifacts
gulp clean
```

## Build Process

ProjectSend uses Gulp for frontend asset management:

### Available Gulp Tasks

```bash
# Build all assets (CSS, JS, images)
gulp build

# Development build with source maps
gulp dev

# Watch files for changes during development
gulp watch

# Minify and optimize for production
gulp production

# Clean build directory
gulp clean

# Lint JavaScript and CSS
gulp lint

# Compile SASS to CSS
gulp styles

# Process and minify JavaScript
gulp scripts

# Optimize images
gulp images
```

### Asset Structure

```
assets/
├── src/
│   ├── scss/          # SASS source files
│   ├── js/            # JavaScript source files
│   └── images/        # Source images
├── dist/              # Built assets (auto-generated)
│   ├── css/
│   ├── js/
│   └── images/
└── gulpfile.js        # Build configuration
```

## Database Setup

### 1. Create Development Database

```sql
CREATE DATABASE projectsend_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'projectsend_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON projectsend_dev.* TO 'projectsend_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Run Database Installation

Access the installation wizard:

```
http://localhost/projectsend/install/
```

Or use the command-line installer:

```bash
php install/cli-installer.php
```

### 3. Development Data

For development, you can use the included sample data:

```bash
# Import sample data (if available)
mysql -u projectsend_user -p projectsend_dev < install/sample_data.sql
```

## Development Server

### Option 1: PHP Built-in Server

```bash
# Start development server
php -S localhost:8000 -t .

# Access application at http://localhost:8000
```

### Option 2: Apache/Nginx Setup

**Apache Configuration (VirtualHost):**

```apache
<VirtualHost *:80>
    ServerName projectsend.local
    DocumentRoot /path/to/projectsend
    
    <Directory /path/to/projectsend>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Enable rewrite module
        RewriteEngine On
    </Directory>
    
    # PHP configuration
    php_value upload_max_filesize 100M
    php_value post_max_size 100M
    php_value memory_limit 256M
</VirtualHost>
```

**Nginx Configuration:**

```nginx
server {
    listen 80;
    server_name projectsend.local;
    root /path/to/projectsend;
    index index.php index.html;
    
    client_max_body_size 100M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\. {
        deny all;
    }
}
```

## Architecture Overview

### Application Structure

ProjectSend follows a modular PHP architecture with clear separation of concerns:

```
projectsend/
├── admin/                 # Administrative interface
├── assets/               # Frontend assets (CSS, JS, images)
├── clients/              # Client-facing interface
├── includes/             # Core PHP classes and functions
│   ├── classes/          # Object-oriented components
│   ├── functions/        # Helper functions
│   ├── oauth/           # OAuth/OIDC integration
│   └── sys.config.php   # System configuration
├── install/              # Installation scripts
├── templates/            # Email and UI templates
├── upload/              # File storage (configurable)
├── vendor/              # Composer dependencies
└── lang/                # Language files
```

### Key Components

**Core Classes (`includes/classes/`):**
- `Users.php` - User management and authentication
- `Files.php` - File handling and permissions
- `Database.php` - Database abstraction layer
- `Email.php` - Email functionality
- `Logger.php` - Logging and audit trails
- `OIDCAuth.php` - OIDC authentication handler

**Authentication Flow:**
1. Request authentication
2. Provider validation (Local/Social/LDAP/OIDC)
3. User synchronization and role mapping
4. Session establishment
5. Authorization checks

**OIDC Integration Architecture:**
- Provider abstraction layer supports multiple OIDC providers
- Claims mapping for user attributes
- Automatic user provisioning
- Role-based access control integration
- Token refresh and validation

## Code Structure

### PHP Coding Standards

ProjectSend follows PSR-12 coding standards with additional conventions:

**File Organization:**
```php
<?php
/**
 * Class description
 * 
 * @package ProjectSend
 * @author Your Name
 */

declare(strict_types=1);

namespace ProjectSend\Components;

class ExampleClass
{
    private string $property;
    
    public function __construct(string $property)
    {
        $this->property = $property;
    }
    
    public function getProperty(): string
    {
        return $this->property;
    }
}
```

**Database Interactions:**
```php
// Use prepared statements for all database queries
$stmt = $database->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

**Error Handling:**
```php
try {
    // Risky operation
    $result = $service->performAction();
} catch (Exception $e) {
    error_log("Error in performAction: " . $e->getMessage());
    throw new ServiceException("Action failed", 0, $e);
}
```

### Frontend Code Structure

**JavaScript Organization:**
```javascript
// Use modern ES6+ syntax
const ProjectSend = {
    init() {
        this.bindEvents();
        this.loadModules();
    },
    
    bindEvents() {
        document.addEventListener('DOMContentLoaded', () => {
            this.setupFormValidation();
        });
    }
};
```

**SASS Structure:**
```scss
// variables.scss
$primary-color: #2c3e50;
$secondary-color: #3498db;

// components.scss
.btn {
    @include button-base;
    
    &--primary {
        background-color: $primary-color;
    }
}
```

## Contributing Guidelines

### Git Workflow

1. **Fork and Clone:**
   ```bash
   git clone https://github.com/yourusername/projectsend.git
   cd projectsend
   git remote add upstream https://github.com/projectsend/projectsend.git
   ```

2. **Create Feature Branch:**
   ```bash
   git checkout -b feature/oidc-enhancement
   ```

3. **Make Changes and Commit:**
   ```bash
   git add .
   git commit -m "Add OIDC user synchronization feature"
   ```

4. **Push and Create Pull Request:**
   ```bash
   git push origin feature/oidc-enhancement
   ```

### Code Review Process

**Before Submitting:**
- [ ] Code follows PSR-12 standards
- [ ] All tests pass
- [ ] Documentation is updated
- [ ] Security considerations addressed
- [ ] Performance impact assessed

**Pull Request Requirements:**
- Clear description of changes
- Screenshots for UI changes
- Test coverage for new features
- Migration scripts for database changes
- Updated CHANGELOG.md

### Commit Message Format

```
type(scope): short description

Longer description explaining the change and why it was made.

Closes #123
```

**Types:**
- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation changes
- `style` - Code style changes
- `refactor` - Code refactoring
- `test` - Adding or updating tests
- `security` - Security improvements

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit
composer test:integration
composer test:security

# Run tests with coverage
composer test:coverage
```

### Writing Tests

**Unit Tests (`tests/unit/`):**
```php
<?php
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation()
    {
        $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
    }
}
```

**Integration Tests (`tests/integration/`):**
```php
<?php
class OIDCAuthTest extends TestCase
{
    public function testKeycloakAuthentication()
    {
        $auth = new OIDCAuth('keycloak');
        $result = $auth->authenticate($this->getValidToken());
        $this->assertTrue($result->isSuccess());
    }
}
```

### Test Database

Use a separate database for testing:

```php
// tests/config/database.php
return [
    'host' => 'localhost',
    'database' => 'projectsend_test',
    'username' => 'test_user',
    'password' => 'test_password'
];
```

## Debugging

### Development Debugging

**Enable Debug Mode:**
```php
// includes/sys.config.php
define('DEBUG', true);
define('LOG_LEVEL', 'DEBUG');
```

**Logging:**
```php
// Use built-in logger
$logger = new ProjectSend\Logger();
$logger->debug('Debug message', ['context' => $data]);
$logger->error('Error occurred', ['exception' => $e]);
```

**Database Query Debugging:**
```php
// Enable query logging
define('LOG_QUERIES', true);

// View slow queries
$database->getSlowQueries();
```

### OIDC Debugging

**Token Inspection:**
```php
// Debug OIDC tokens
$oidc = new OIDCAuth('keycloak');
$claims = $oidc->validateToken($token);
error_log('OIDC Claims: ' . json_encode($claims));
```

**Provider Communication:**
```php
// Enable HTTP request logging
define('LOG_HTTP_REQUESTS', true);

// Check provider endpoints
$oidc->testConnection();
```

### Browser Debugging

**JavaScript Console:**
```javascript
// Enable debug mode in browser
localStorage.setItem('projectsend_debug', 'true');

// View API requests
console.log('API Response:', response);
```

## Troubleshooting

### Common Development Issues

**Database Connection Issues:**
```bash
# Check MySQL service
sudo systemctl status mysql

# Test connection
mysql -u projectsend_user -p -h localhost projectsend_dev
```

**Permission Issues:**
```bash
# Fix file permissions
chmod -R 755 projectsend/
chmod -R 777 upload/
chown -R www-data:www-data projectsend/
```

**Build Process Issues:**
```bash
# Clear npm cache
npm cache clean --force

# Reinstall dependencies
rm -rf node_modules package-lock.json
npm install

# Rebuild assets
gulp clean && gulp build
```

**OIDC Integration Issues:**
```php
// Check provider configuration
$oidc = new OIDCAuth('keycloak');
if (!$oidc->testConfiguration()) {
    throw new Exception('OIDC configuration invalid');
}

// Verify SSL certificates
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Development only
```

### Performance Issues

**Database Optimization:**
```sql
-- Check slow queries
SHOW FULL PROCESSLIST;
SHOW STATUS LIKE 'Slow_queries';

-- Analyze tables
ANALYZE TABLE tbl_users, tbl_files;
```

**Asset Loading:**
```bash
# Optimize images
gulp images:optimize

# Check bundle size
npm run analyze
```

### Security Considerations for Development

**Sensitive Data:**
- Never commit real credentials
- Use environment variables for secrets
- Rotate development keys regularly

**HTTPS in Development:**
```bash
# Generate self-signed certificate
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes
```

**Input Validation:**
```php
// Always validate and sanitize input
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    throw new InvalidArgumentException('Invalid email format');
}
```

This development guide provides a comprehensive foundation for contributing to ProjectSend. For production deployment and configuration details, refer to DEPLOY.md and CONFIGURATION.md respectively.