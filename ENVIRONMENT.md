# Environment Variables Documentation

This document provides comprehensive information about environment variables used in the Elan Registry application.

## Overview

The Elan Registry uses **SecureEnvPHP** to manage encrypted environment variables, providing enhanced security for sensitive configuration data such as database credentials and API keys.

### Encryption System

- **Encrypted Storage**: Environment variables are stored in `.env.enc` (encrypted file)
- **Decryption Key**: Security key stored in `.env.key` 
- **Library**: `johnathanmiller/secure-env-php` handles encryption/decryption
- **Loading**: Variables loaded in `usersc/includes/custom_functions.php:29-31`

### Security Features

- **Encryption at Rest**: All sensitive data encrypted using industry-standard encryption
- **Key Separation**: Encryption key stored separately from encrypted data
- **Access Control**: Environment variables only accessible via `getenv()` in PHP
- **No Plaintext**: No sensitive data stored in plaintext configuration files

## Environment Variables

### Database Configuration

These variables configure the MySQL database connection:

| Variable | Type | Description | Example | Required |
|----------|------|-------------|---------|----------|
| `DB_HOST` | String | Database server hostname/IP | `localhost`, `127.0.0.1` | ✅ Yes |
| `DB_USER` | String | Database username | `elan_registry_user` | ✅ Yes |
| `DB_PASS` | String | Database password | `secure_password_123` | ✅ Yes |
| `DB_NAME` | String | Database name | `elanregi_spice` | ✅ Yes |

**Usage Location**: `users/init.php:40-46`
**Security Note**: These credentials provide full access to the registry database

### Google Services API Keys

These variables enable Google Maps and geocoding functionality:

| Variable | Type | Description | Example | Required |
|----------|------|-------------|---------|----------|
| `MAPS_KEY` | String | Google Maps JavaScript API key | `AIzaSyB...XYZ123` | ✅ Yes |
| `GEO_ENCODE_KEY` | String | Google Geocoding API key | `AIzaSyC...ABC789` | ✅ Yes |

**Usage Location**: `users/init.php:58-59`
**Features Enabled**:
- Interactive maps on statistics page
- Car location visualization
- Address geocoding for user profiles
- Geographic clustering of registry data

**Security Note**: These keys should be restricted to specific domains in Google Cloud Console

## Implementation Details

### Loading Process

1. **Bootstrap**: `usersc/includes/custom_functions.php` loads SecureEnvPHP
2. **Decryption**: `.env.enc` file decrypted using `.env.key`
3. **Availability**: Variables available via `getenv()` throughout application
4. **Configuration**: Database config set in `users/init.php` global array

### Code Example

```php
// Loading encrypted environment variables
use SecureEnvPHP\SecureEnvPHP;
(new SecureEnvPHP())->parse($abs_us_root . $us_url_root . '.env.enc', 
                            $abs_us_root . $us_url_root . '.env.key');

// Using environment variables
$host = getenv('DB_HOST');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$database = getenv('DB_NAME');
```

## Development Setup

### Initial Configuration

1. **Install Dependencies**:
   ```bash
   composer require johnathanmiller/secure-env-php
   ```

2. **Create Environment Files**:
   ```bash
   # Create plaintext .env file (temporary)
   echo "DB_HOST=localhost" > .env
   echo "DB_USER=your_username" >> .env
   echo "DB_PASS=your_password" >> .env
   echo "DB_NAME=your_database" >> .env
   echo "MAPS_KEY=your_maps_key" >> .env
   echo "GEO_ENCODE_KEY=your_geocoding_key" >> .env
   ```

3. **Encrypt Variables**:
   ```bash
   # Use SecureEnvPHP to encrypt (see library documentation)
   # This creates .env.enc and .env.key files
   ```

4. **Cleanup**:
   ```bash
   # Remove plaintext .env file
   rm .env
   ```

### Production Deployment

1. **File Permissions**:
   ```bash
   chmod 600 .env.enc .env.key  # Restrict access
   chown www-data:www-data .env.enc .env.key  # Set correct ownership
   ```

2. **Backup Strategy**:
   - Store `.env.key` securely (separate from application code)
   - Backup `.env.enc` with application deployment
   - Never commit either file to version control

3. **Access Control**:
   - Ensure web server cannot serve `.env*` files directly
   - Use proper `.htaccess` or nginx configuration
   - Monitor file access logs

## Security Best Practices

### Key Management

- **Separation**: Never store `.env.key` in the same location as application code
- **Backup**: Maintain secure backup of encryption key
- **Access**: Limit key access to authorized personnel only
- **Rotation**: Consider periodic key rotation for high-security environments

### API Key Security

- **Google Cloud Console**:
  - Restrict API keys to specific domains/IPs
  - Enable only necessary APIs (Maps JavaScript API, Geocoding API)
  - Monitor API usage and set quotas
  - Use separate keys for development/production

### Database Security

- **Principle of Least Privilege**: Database user should have only necessary permissions
- **Connection Security**: Use SSL/TLS for database connections when possible
- **Network Access**: Restrict database access to application server only
- **Monitoring**: Monitor database connections and unusual activity

## Troubleshooting

### Common Issues

1. **"Failed to decrypt .env.enc"**:
   - Verify `.env.key` file exists and is readable
   - Check file permissions (should be readable by web server)
   - Ensure files haven't been corrupted during deployment

2. **"Database connection failed"**:
   - Verify database credentials in encrypted environment
   - Check database server is running and accessible
   - Confirm database user has necessary permissions

3. **"Google Maps not loading"**:
   - Verify `MAPS_KEY` is correctly set
   - Check Google Cloud Console for API restrictions
   - Ensure billing is enabled for Google Cloud project
   - Monitor API quota usage

### Debugging

```php
// Check if environment variables are loaded
if (getenv('DB_HOST') === false) {
    error_log('Environment variables not loaded');
}

// Verify database configuration
$config = $GLOBALS['config']['mysql'];
error_log('DB Host: ' . $config['host']);
error_log('DB Name: ' . $config['db']);
```

## Migration Notes

### From Plaintext Configuration

If migrating from plaintext configuration files:

1. Identify all sensitive configuration values
2. Create temporary `.env` file with all variables
3. Use SecureEnvPHP to encrypt the file
4. Update code to use `getenv()` instead of direct constants
5. Remove plaintext configuration files
6. Update deployment processes

### Environment Synchronization

- Development, staging, and production should use same variable names
- Values will differ between environments (different databases, API keys)
- Use consistent naming conventions across all environments

## References

- [SecureEnvPHP Documentation](https://github.com/johnathanmiller/secure-env-php)
- [Google Maps API Documentation](https://developers.google.com/maps/documentation)
- [Google Geocoding API Documentation](https://developers.google.com/maps/documentation/geocoding)
- [PHP getenv() Documentation](https://www.php.net/manual/en/function.getenv.php)