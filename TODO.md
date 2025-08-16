# Project TODOs: Style, Security, and Organization Improvements

## 1. Style & Layout Consistency
- Refactor all pages in `app/` (e.g., `privacy.php`, `list_cars.php`, `car_details.php`, `statistics.php`) to use the same header/footer and card/container structure.
- Move inline styles to external CSS (e.g., `app/assets/style.css`).
- Refactor HTML for consistent use of Bootstrap classes.
- Update template files in `usersc/templates/` (e.g., `header.php`, `footer.php`).

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
- Expand `README.md` with setup, usage, and contribution guidelines.
- Add or update doc comments in all PHP files, especially in `app/` and `users/`.

## 5. Testing
- Add or improve automated tests for registration, login, car management, and privacy features in `tests/` (e.g., `CarTest.php`, `test_class.php`).
- Use PHPUnit for PHP tests.

## 6. Dependency & Performance
- Update Composer and JS dependencies (`composer.json`, `composer.lock`).
- Audit for vulnerabilities.
- Optimize images and minify CSS/JS in `app/assets/` and `userimages/`.

---

Prioritize these steps for a more maintainable, secure, and professional project. Tackle each section and mark items as complete when finished.
