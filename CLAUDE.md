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
- `app/cars/index.php` - Searchable car listing with DataTables
- `app/cars/details.php` - Individual car detail pages
- `app/cars/edit.php` - Car editing forms
- `app/cars/identify.php` - Car identification/registration
- `app/cars/actions/` - AJAX endpoints for car operations

**User Features:**
- `app/reports/statistics.php` - Registry statistics
- `app/contact/send-owner-email.php` - Owner contact functionality
- `app/privacy.php` - GDPR-compliant privacy policy

## Development Commands

### Testing
```bash
# Run PHPUnit tests
vendor/bin/phpunit tests/

# Run Playwright browser tests
npm test

# Run specific Playwright test suites
npm run test:security      # Security-focused tests
npm run test:ui           # UI consistency tests
npm run test:navigation   # Navigation and redirects
npm run test:functionality # Core functionality
npm run test:maps         # Maps and charts
```

### Frontend Testing Policy
**IMPORTANT**: Anytime we make changes to the frontend, we should run the appropriate Playwright test. If a test is not available, develop a test and execute it before considering the work complete.

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
- Car-related logic in `/app/cars/`
- Contact forms and email handling in `/app/contact/`
- Statistics and reporting in `/app/reports/`
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

## EXTREMELY IMPORTANT: Code Quality Checks

**ALWAYS run the following commands before completing any task:**

Automatically use the IDE's built-in diagnostics tool to check for linting and type errors:
   - Run `mcp__ide__getDiagnostics` to check all files for diagnostics
   - Fix any linting or type errors before considering the task complete
   - Do this for any file you create or modify

This is a CRITICAL step that must NEVER be skipped when working on any code-related task.

## Git & Version Control

- Add and commit automatically whenever an entire task is finished
- Use descriptive commit messages that capture the full scope of changes

## Rule Improvement Triggers

- New code patterns not covered by existing rules
- Repeated similar implementations across files
- Common error patterns that could be prevented
- New libraries or tools being used consistently
- Emerging best practices in the codebase

# Analysis Process:
- Compare new code with existing rules
- Identify patterns that should be standardized
- Look for references to external documentation
- Check for consistent error handling patterns
- Monitor test patterns and coverage

# Rule Updates:

- **Add New Rules When:**
  - A new technology/pattern is used in 3+ files
  - Common bugs could be prevented by a rule
  - Code reviews repeatedly mention the same feedback
  - New security or performance patterns emerge

- **Modify Existing Rules When:**
  - Better examples exist in the codebase
  - Additional edge cases are discovered
  - Related rules have been updated
  - Implementation details have changed

- **Example Pattern Recognition:**

  ```typescript
  // If you see repeated patterns like:
  const data = await prisma.user.findMany({
    select: { id: true, email: true },
    where: { status: 'ACTIVE' }
  });

  // Consider adding to [prisma.mdc](mdc:shipixen/.cursor/rules/prisma.mdc):
  // - Standard select fields
  // - Common where conditions
  // - Performance optimization patterns
  ```

- **Rule Quality Checks:**
- Rules should be actionable and specific
- Examples should come from actual code
- References should be up to date
- Patterns should be consistently enforced

## Continuous Improvement:

- Monitor code review comments
- Track common development questions
- Update rules after major refactors
- Add links to relevant documentation
- Cross-reference related rules

## Rule Deprecation

- Mark outdated patterns as deprecated
- Remove rules that no longer apply
- Update references to deprecated rules
- Document migration paths for old patterns

## Documentation Updates:

- Keep examples synchronized with code
- Update references to external docs
- Maintain links between related rules
- Document breaking changes