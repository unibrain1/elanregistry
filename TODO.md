# Project TODOs: Security and Organization Improvements

## 1. Style & Layout Consistency ✅ **COMPLETED (August 2025)**
+ Refactored all pages in `app/` to use consistent header/footer and card/container structure. **(Complete)**
+ Standardized Bootstrap classes and card structure across all pages. **(Complete)**
+ Fixed page wrapper classes and container structure. **(Complete)**
+ Applied consistent header formatting (`h2 class="mb-0"`) throughout. **(Complete)**
+ Added `h-100` classes for equal card heights in grid layouts. **(Complete)**
+ No inline styles found - external CSS already properly organized. **(Complete)**

## 2. Security Hardening
- Add input validation and sanitization for all forms and URL parameters in all PHP files handling user input (`app/`, `users/`, `app/action/`, `app/verify/`).
- Ensure CSRF tokens are used in all forms and AJAX endpoints.
- Use prepared statements for all SQL queries.
- Review password hashing (should use bcrypt or similar) in authentication logic (`users/init.php`, `users/login.php`, `users/register.php`).
- Set secure session cookie flags in `init.php`.

## 3. Organization & Clean-up
- Group files by function (e.g., move all car-related logic to `app/cars/`).
- Rename files for clarity (e.g., `edit_car.php` → `car_edit.php`).
- Remove unused or commented-out code throughout the project.
- Add comments to complex logic in PHP and JS files.

## 4. Documentation
+ Expand `README.md` with setup, usage, and contribution guidelines. **(Complete)**
+ Add or update doc comments in all PHP files, especially in `app/` and `users/`. **(Complete)**
+ Document the use of SecureEnvPHP and all environment variables (see `usersc/includes/custom_functions.php`).

## 5. Testing
- Add or improve automated tests for registration, login, car management, and privacy features in `tests/` (e.g., `CarTest.php`, `test_class.php`).
- Use PHPUnit for PHP tests.

## 6. Dependency & Performance
- Update Composer and JS dependencies (`composer.json`, `composer.lock`).
- Audit for vulnerabilities.
- Optimize images and minify CSS/JS in `app/assets/` and `userimages/`.

---

## **Project Status Summary**

### ✅ **Completed (August 2025)**
- **Documentation** - Comprehensive doc comments added to all major PHP files
- **Style & Layout Consistency** - Full standardization of page structure, Bootstrap classes, and card layouts

### 🔄 **Current Priorities**
1. **Security Hardening** - Critical for production safety
2. **Organization & Clean-up** - Improve maintainability
3. **Testing** - Ensure reliability and prevent regressions

### 📋 **Future Development**
- **Dependency & Performance** - Keep project modern and optimized

**Next Focus:** Security hardening is the top priority to ensure the application is safe for production use.
