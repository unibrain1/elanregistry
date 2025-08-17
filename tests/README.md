# Elan Registry Test Suite

This directory contains automated test cases for the Elan Registry car update functionality.

## Test Coverage

### CarUpdateTest.php
Tests core car update functionality including:
- ✅ **Car Creation** - Adding new cars with validation
- ✅ **Car Updates** - Modifying existing car information  
- ✅ **Input Validation** - Required fields, data formatting
- ✅ **CSRF Protection** - Token validation for security
- ✅ **Date Handling** - Date parsing and formatting
- ✅ **Engine Number Formatting** - Uppercase, space removal
- ✅ **Chassis Validation** - Pre-1970 4-digit rule, race car exception
- ✅ **Image Management** - Fetch and remove car images
- ✅ **Error Handling** - Invalid actions and missing data

### FileUploadSecurityTest.php  
Tests file upload security enhancements including:
- 🔒 **Secure Filename Generation** - Cryptographic randomness
- 🔒 **MIME Type Validation** - Strict allowlisting 
- 🔒 **File Size Limits** - Maximum and minimum size enforcement
- 🔒 **Upload Error Handling** - Proper error code processing
- 🔒 **Directory Traversal Prevention** - Path validation
- 🔒 **Malicious File Protection** - Polyglot and script injection prevention
- 🔒 **Entropy Testing** - Filename randomness quality

## Security Validations

The test suite validates protection against:

- **SQL Injection** - All database operations use prepared statements
- **File Upload Attacks** - MIME validation, size limits, secure naming
- **Directory Traversal** - Path sanitization and validation
- **CSRF Attacks** - Token verification for all state-changing operations
- **Input Validation** - Comprehensive field validation and sanitization

## Running Tests

### Option 1: PHPUnit (Recommended)
```bash
# Install PHPUnit if not already installed
composer require --dev phpunit/phpunit

# Run all tests
./vendor/bin/phpunit tests/

# Run specific test class
./vendor/bin/phpunit tests/CarUpdateTest.php

# Run with coverage report
./vendor/bin/phpunit --coverage-html coverage/ tests/
```

### Option 2: Custom Test Runner
```bash
# Run the custom test runner script
php tests/run_tests.php
```

### Option 3: Individual Test Files
```bash
# Run individual test classes
php tests/CarUpdateTest.php
php tests/FileUploadSecurityTest.php
```

## Test Requirements

- **PHP 7.4+** with PHPUnit framework
- **Database connection** to test database (configured in phpunit.xml)
- **File system permissions** for temporary file creation during upload tests
- **UserSpice framework** properly initialized

## Test Data

Tests use:
- **Temporary test data** - Created and cleaned up automatically
- **Mock file uploads** - Simulated file upload scenarios
- **Isolated database operations** - Tests don't affect production data

## Test Configuration

See `phpunit.xml` for test configuration including:
- Bootstrap file (`../users/init.php`)
- Test directories and file patterns
- Coverage settings
- Environment variables for testing

## Expected Results

When all tests pass, you should see:
```
✅ All tests passed! The car update functionality is working correctly.

Security validations tested:
✅ File upload security (MIME validation, size limits, secure filenames)
✅ Input validation and sanitization  
✅ CSRF token protection
✅ Directory traversal prevention
✅ Data validation and formatting
```

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Ensure test database is properly configured
   - Check database credentials in test environment

2. **File Permission Errors**
   - Ensure web server has write access to temp directories
   - Check upload directory permissions (755 recommended)

3. **Missing Dependencies**
   - Install PHPUnit: `composer require --dev phpunit/phpunit`
   - Ensure UserSpice framework is properly initialized

4. **CSRF Token Errors**
   - Tests may fail if session handling is not properly mocked
   - Ensure Token class is available and functioning

### Debugging Failed Tests

1. **Run tests with verbose output**: `phpunit --verbose`
2. **Check error logs** for detailed failure information
3. **Verify test database** has proper schema and permissions
4. **Run individual test methods** to isolate issues

## Adding New Tests

To add new test cases:

1. **Extend existing test classes** for related functionality
2. **Create new test classes** for new features
3. **Follow naming conventions**: `TestClassName.php`
4. **Include setUp/tearDown** for proper test isolation
5. **Add security-focused tests** for any new functionality

## Security Note

These tests validate critical security measures. If any security tests fail:

1. **Do NOT deploy to production** until issues are resolved
2. **Review the specific security validation** that failed
3. **Fix the underlying security issue** before proceeding
4. **Re-run all tests** to ensure no regressions

The test suite serves as both validation and documentation of the security measures implemented in the car update system.