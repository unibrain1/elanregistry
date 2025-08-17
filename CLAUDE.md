# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture Overview

This is a PHP web application for the Lotus Elan Registry hosted at https://elanregistry.org. It's built on top of UserSpice (userspice.com) for user authentication and management, with custom car registry functionality.

### Key Components

**Core Application Structure:**
- `/app/` - Main application pages (car listings, details, forms, actions)
- `/users/` - UserSpice authentication system 
- `/usersc/` - UserSpice customizations (templates, plugins, overrides)
- `/userimages/` - User-uploaded car images organized by car ID
- `/docs/` - Documentation and reference materials
- `/tests/` - PHPUnit test files

**Database Architecture:**
- MySQL database with comprehensive car registry schema
- `cars` table for vehicle records with full audit trail via `cars_hist`
- `car_user` junction table for car sharing between users
- Views: `usersview`, `users_carsview` for complex queries
- Database triggers automatically maintain audit trails

### Core Application Files

**Car Management:**
- `app/list_cars.php` - Searchable car listing with DataTables
- `app/car_details.php` - Individual car detail pages
- `app/edit_car.php` - Car editing forms
- `app/identification.php` - Car identification/registration
- `app/action/` - AJAX endpoints for car operations

**User Features:**
- `app/statistics.php` - Registry statistics
- `app/contact_owner.php` - Owner contact functionality
- `app/privacy.php` - GDPR-compliant privacy policy

## Development Commands

### Testing
```bash
# Run PHPUnit tests
vendor/bin/phpunit tests/
```

### Dependencies
```bash
# Install PHP dependencies
composer install

# Update dependencies
composer update
```

### Database
- Database schema is documented in README.md
- Uses MySQL with comprehensive audit trails
- SQL files in `/SQL/` for schema updates

## Development Guidelines

### Security Requirements
- All forms must use CSRF tokens
- Use prepared statements for SQL queries
- Input validation and sanitization required for all user inputs
- Password hashing uses bcrypt
- Secure session handling implemented

### File Organization
- Car-related logic in `/app/`
- Authentication handled by UserSpice in `/users/`
- Custom UserSpice modifications in `/usersc/`
- User uploads organized by car ID in `/userimages/`

### Templates & Styling
- Uses Bootstrap 4/5 for responsive layout
- Custom CSS in `usersc/templates/ElanRegistry/assets/css/`
- Template system via UserSpice with custom overrides
- Card-based layout for consistent UI

### Image Handling
- Images auto-resized to multiple formats (100, 300, 600, 1024, 2048px)
- Storage path: `userimages/{car_id}/`
- Resize class available at `usersc/classes/Resize.php`

### Privacy & GDPR
- Location data intentionally imprecise for privacy
- Email verification system for car registrations
- User consent tracking implemented
- Data retention policies documented

## Current Priorities (from TODO.md)

1. **Style & Layout Consistency** - Refactor pages to use consistent header/footer and card structure
2. **Security Hardening** - Add comprehensive input validation and CSRF protection
3. **Organization** - Group files by function, improve naming conventions
4. **Testing** - Expand PHPUnit test coverage for core functionality

## Plugin System

UserSpice plugins provide extended functionality:
- `cms` - Content management
- `recaptcha` - Spam protection  
- `reports` - Data reporting
- `hooker` - Custom hooks system

## Environment
- PHP 7.4+ required
- MySQL 8.0+ 
- Uses `johnathanmiller/secure-env-php` for environment variable handling
- Google Analytics integration for statistics