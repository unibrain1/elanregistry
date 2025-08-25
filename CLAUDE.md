# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture Overview

This is a PHP web application for the Lotus Elan Registry hosted at https://elanregistry.org. It's built on top of UserSpice (userspice.com) for user authentication and management, with custom car registry functionality.

### Core Application Structure
- `/app/` - Main application pages (car listings, details, forms, actions)
- `/users/` - UserSpice authentication system 
- `/usersc/` - UserSpice customizations (templates, plugins, overrides)
- `/userimages/` - User-uploaded car images organized by car ID
- `/docs/` - Documentation and reference materials
- `/tests/` - PHPUnit and Playwright test files

### Database Architecture
- MySQL database with comprehensive car registry schema
- `cars` table for vehicle records with full audit trail via `cars_hist`
- `car_user` junction table for car sharing between users
- Views: `usersview`, `users_carsview` for complex queries
- Database triggers automatically maintain audit trails

### Key Application Files
- `app/cars/index.php` - Searchable car listing with DataTables
- `app/cars/details.php` - Individual car detail pages
- `app/cars/edit.php` - Car editing forms
- `app/reports/statistics.php` - Registry statistics with Google Charts
- `app/contact/send-owner-email.php` - Owner contact functionality

## Development Commands

### Testing
```bash
# Run PHPUnit tests
vendor/bin/phpunit tests/

# Run Playwright browser tests
npm test

# Run specific test suites
npm run test:security      # Security-focused tests
npm run test:ui           # UI consistency tests
npm run test:navigation   # Navigation and redirects
npm run test:functionality # Core functionality
npm run test:maps         # Maps and charts
npm run test:csp          # CSP validation tests
```

### Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node dependencies (for testing)
npm install
```

## Development Guidelines

### Security Requirements
- All forms must use CSRF tokens
- Use prepared statements for SQL queries
- Input validation and sanitization required for all user inputs
- Password hashing uses bcrypt
- Secure session handling implemented
- **CRITICAL**: Never commit credentials, API keys, or sensitive data to git
- Use environment variables for all sensitive configuration
- Test credentials must be in `.env.local` (git-ignored) or environment variables

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
- Custom branding assets in `usersc/templates/ElanRegistry/assets/images/`
  - Lotus-logo-3000x3000.png (main logo)
  - logo-72x72.png (small logo)  
  - favicon.ico (browser tab icon)
- Template system via UserSpice with custom overrides
- Card-based layout for consistent UI

### Custom Branding
- ElanRegistry template includes custom Lotus Elan Registry branding
- Logo files are self-contained within the template directory
- Favicon automatically uses template-specific icon instead of generic UserSpice favicon
- Template uses CDN-based asset loading for Bootstrap, jQuery, and FontAwesome

## Production Deployment

### Production Environment
- **Hosting**: A2 Hosting with git deployment hooks
- **Remote**: `prod` remote configured for direct deployment to production server
- **Auto-deployment**: Master branch deploys automatically when pushed to prod remote
- **Version Display**: Uses VERSION file modification time for deployment timestamp

### Deployment Commands
When deploying to production, always push both code and tags:

```bash
# Push code to production
git push prod main

# Push version tags to production  
git push prod --tags
```

### Complete Production Deployment Process
1. **Commit changes** with updated VERSION file and git tag
2. **Push to origin** for GitHub repository updates: `git push origin main && git push origin --tags`
3. **Deploy to production** with both code and tags: `git push prod main && git push prod --tags`
4. **Verify deployment** by checking version display on production site
5. **Complete post-deployment verification** (see checklist below)

### Post-Deployment Configuration Requirements

**CRITICAL:** After deploying code changes to production, always verify and update:

#### Google Maps API Configuration
- **Problem:** File reorganization affects API referrer restrictions
- **Solution:** Update Google Cloud Console API restrictions to include new file paths
- **Check:** Verify maps display correctly on statistics and detail pages
- **Location:** Google Cloud Console → APIs & Services → Credentials → API Keys

#### UserSpice Page Permissions
- **Problem:** New pages and redirects need proper access permissions configured
- **Solution:** Update page permissions in UserSpice admin panel
- **Required for:** Both redirect pages AND new destination pages
- **Access:** Admin Panel → Manage Pages → Set appropriate permission levels
- **Test:** Verify all user roles can access reorganized pages correctly

#### Deployment Verification Checklist
- [ ] Google Maps display correctly on all pages
- [ ] All redirected pages work and maintain proper permissions
- [ ] New pages have appropriate UserSpice permission levels
- [ ] Contact forms send to correct email addresses
- [ ] Version information displays correctly in footer
- [ ] Test critical user workflows (car registration, editing, contact forms)

## Content Security Policy (CSP) Management

The application implements a comprehensive Content Security Policy to prevent XSS attacks and unauthorized resource loading while supporting all required external services.

### CSP Configuration Location
**File:** `usersc/includes/security_headers.php`

### Supported External Services
- **Google Services**: Maps, Charts, Analytics, reCAPTCHA, Tag Manager
- **Cloudflare**: Analytics with wildcard pattern support for versioned scripts
- **CDN Resources**: JSDelivr, Cloudflare CDN, Bootstrap CDN, jQuery, DataTables
- **Font Services**: Google Fonts, FontAwesome (including kit support)

### CSP Validation & Testing

#### Automated Testing Tools
1. **Playwright CSP Tests**: `tests/playwright/csp-validation.spec.js`
   - Browser-based CSP violation detection
   - Tests critical pages: statistics, car details, listing, login
   - Validates external resource loading (Google Charts, Cloudflare Analytics)

2. **Static Policy Validator**: `tests/validate-csp-policy.php`
   - Command-line tool: `php tests/validate-csp-policy.php`
   - Validates all required domains are present
   - Checks security best practices
   - Generates detailed validation reports

#### Running CSP Tests
```bash
# Static policy validation
php tests/validate-csp-policy.php

# Browser-based violation testing
npm run test:csp

# Full security test suite (includes CSP tests)
npm run test:security
```

### CSP Troubleshooting

#### Common Issues
1. **Google Charts CSS blocked**: Ensure `www.gstatic.com/charts/*` in style-src
2. **Cloudflare Analytics blocked**: Verify `static.cloudflareinsights.com/*` in script-src  
3. **FontAwesome issues**: Check kit.fontawesome.com domains in script-src/style-src
4. **Maps not loading**: Validate maps.googleapis.com in all relevant directives

#### Adding New External Resources
1. Add domains to appropriate CSP directive in `security_headers.php`
2. Update required domains list in `tests/validate-csp-policy.php`
3. Run validation tests to ensure no regressions
4. Test on actual pages with browser console monitoring

## Git & Version Control

### Branch Management Strategy
- `main` branch always contains production-ready code
- All development work happens on feature/phase branches
- Direct commits to main are discouraged

### Branch Naming Convention
- Feature branches: `feature/issue-{number}-brief-description`
- Phase branches: `phase-{number}-{name}`
- Hotfix branches: `hotfix/issue-{number}-brief-description`

### Version Management
- Version information stored in `/VERSION` file in project root
- `ApplicationVersion::get()` reads from this file (no git dependencies)
- Production deployment timestamp shows file modification time
- **REQUIRED:** All merged branches must update VERSION file and create matching git tag

## Environment & Configuration

### System Requirements
- PHP 7.4+ required
- MySQL 8.0+ 
- Uses `johnathanmiller/secure-env-php` for encrypted environment variable handling
- Google Analytics integration for statistics

### Environment Variables
See comprehensive documentation in `ENVIRONMENT.md`:
- Database credentials (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`)
- Google API keys (`MAPS_KEY`, `GEO_ENCODE_KEY`)
- All variables encrypted at rest using SecureEnvPHP

### UserSpice Plugins
- `cms` - Content management
- `recaptcha` - Spam protection  
- `reports` - Data reporting
- `hooker` - Custom hooks system

## Code Quality Requirements

**ALWAYS run the following commands before completing any task:**

- Run `mcp__ide__getDiagnostics` to check all files for diagnostics
- Fix any linting or type errors before considering the task complete
- Run appropriate test suites for modified functionality

This is a CRITICAL step that must NEVER be skipped when working on any code-related task.

## Current Development Status

### ✅ Production Ready Features
- **Security**: Enterprise-grade security implementation with comprehensive CSRF protection, prepared statements, and secure session handling
- **Testing**: 35/35 Playwright browser tests passing (100% success rate) plus comprehensive PHPUnit security test suite
- **Organization**: Complete file reorganization by function with backward-compatible redirects
- **CSP Management**: Comprehensive Content Security Policy with automated validation tools
- **Documentation**: Complete setup, development, and deployment documentation

### 📋 Active Development Areas
Current GitHub Issues are organized into development phases:
- **Phase 1 Critical Issues** - Bug fixes and stability improvements
- **Phase 2-5** - Core enhancements, UX improvements, and optional features

See GitHub Issues for detailed development roadmap and current work items.