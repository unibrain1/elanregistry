# Comprehensive Test Plan - Style Refactor Branch

## Overview
This test plan covers all major changes made in the style-refactor branch to ensure no functionality has been broken during:
- Style consistency refactoring
- Security hardening implementation
- File reorganization by functional groups
- AJAX endpoint updates
- Database security improvements

## Pre-Test Setup
1. **Environment**: Test on localhost:9999/elan_registry
2. **Browser Testing**: Chrome, Firefox, Safari, Mobile viewport
3. **User Accounts**: Ensure test accounts with different permission levels
4. **Test Data**: Verify cars with images, different types, and ownership scenarios

---

## TEST 1: Authentication and User Management
**Priority**: CRITICAL

### Test Cases:
1. **Login Process**
   - [ ] Navigate to `/users/login.php`
   - [ ] Test valid credentials login
   - [ ] Test invalid credentials (should show error)
   - [ ] Test session persistence after login
   - [ ] Test "Remember Me" functionality

2. **Registration Process**
   - [ ] Navigate to `/users/join.php`
   - [ ] Test new user registration
   - [ ] Verify email validation requirements
   - [ ] Test password strength requirements
   - [ ] Check CSRF token validation on forms

3. **Account Management**
   - [ ] Navigate to `/usersc/account.php`
   - [ ] Verify user profile display
   - [ ] Test profile editing functionality
   - [ ] Check password change process
   - [ ] Verify account deletion (if applicable)

4. **Session Security**
   - [ ] Verify secure session cookies are set
   - [ ] Test session timeout behavior
   - [ ] Check logout functionality clears session

---

## TEST 2: Car Registration and Editing Workflow
**Priority**: CRITICAL

### Test Cases:
1. **Car Registration (Add New)**
   - [ ] Navigate to `/app/cars/edit.php` (previously edit_car.php)
   - [ ] Test form progression through all 4 steps
   - [ ] Verify year/model dropdown population works
   - [ ] Test chassis number validation for different years
   - [ ] Check chassis availability checking (AJAX call)
   - [ ] Test date picker functionality
   - [ ] Verify all form validation icons work
   - [ ] Test CSRF token validation

2. **Car Editing (Update Existing)**
   - [ ] Navigate to edit existing car
   - [ ] Verify form pre-population with existing data
   - [ ] Test updating car information
   - [ ] Check that validation still works during editing
   - [ ] Verify update saves correctly

3. **Car Details Display**
   - [ ] Navigate to `/app/cars/details.php?car_id=X` (previously car_details.php)
   - [ ] Verify all car information displays correctly
   - [ ] Check car images display properly
   - [ ] Test Google Maps integration shows location
   - [ ] Verify edit button functionality (if owner)
   - [ ] Test contact owner functionality

4. **Image Upload and Management**
   - [ ] Test Dropzone file upload functionality
   - [ ] Verify drag and drop works
   - [ ] Test image sorting/reordering
   - [ ] Check image validation (file types, sizes)
   - [ ] Verify image resizing works
   - [ ] Test removing images
   - [ ] Check existing images load for editing

---

## TEST 3: File Upload and Image Processing
**Priority**: HIGH

### Test Cases:
1. **Image Upload Security**
   - [ ] Test only image files are accepted
   - [ ] Verify file size limits are enforced
   - [ ] Test malicious file rejection
   - [ ] Check proper MIME type validation

2. **Image Processing**
   - [ ] Verify images are resized to multiple formats
   - [ ] Test storage in correct directory structure
   - [ ] Check image display in various contexts
   - [ ] Test image deletion

---

## TEST 4: Search and DataTables Functionality
**Priority**: CRITICAL

### Test Cases:
1. **Car Listing Pages**
   - [ ] Navigate to `/app/cars/index.php` (previously list_cars.php)
   - [ ] Verify DataTables loads correctly
   - [ ] Test search functionality
   - [ ] Check sorting by different columns
   - [ ] Test pagination
   - [ ] Verify export functionality (if applicable)

2. **Factory Listing**
   - [ ] Navigate to `/app/cars/factory.php` (previously list_factory.php)
   - [ ] Test DataTables functionality
   - [ ] Verify factory-specific data display

3. **AJAX Endpoints**
   - [ ] Check `/app/cars/actions/datatables.php` responds correctly
   - [ ] Verify search queries are properly sanitized
   - [ ] Test server-side processing performance
   - [ ] Check error handling for malformed requests

---

## TEST 5: Maps and Statistics Display
**Priority**: HIGH

### Test Cases:
1. **Statistics Page**
   - [ ] Navigate to `/app/reports/statistics.php` (previously statistics.php)
   - [ ] Verify Google Charts load correctly
   - [ ] Test all chart types display (pie, bar, timeline)
   - [ ] Check Google Maps shows car locations
   - [ ] Test map marker clicking
   - [ ] Verify statistics data accuracy

2. **Individual Car Maps**
   - [ ] Check maps on car detail pages
   - [ ] Verify location markers display
   - [ ] Test map interactivity

3. **Map Data Endpoints**
   - [ ] Test `/app/cars/mapmarkers.xml.php` returns valid XML
   - [ ] Verify no PHP errors in XML output
   - [ ] Check XML structure is valid

---

## TEST 6: Contact and Email Systems
**Priority**: MEDIUM

### Test Cases:
1. **Contact Forms**
   - [ ] Navigate to `/app/contact/index.php` (previously contact.php)
   - [ ] Test contact form submission
   - [ ] Verify CSRF protection works
   - [ ] Check email delivery

2. **Owner Contact System**
   - [ ] Test contact owner functionality from car details
   - [ ] Verify email templates work
   - [ ] Check privacy protection (no direct email exposure)

3. **Verification System**
   - [ ] Test car verification emails
   - [ ] Check verification links work
   - [ ] Verify CSRF protection on verification endpoints
   - [ ] Test different verification actions (verify, edit, sold)

---

## TEST 7: Security and CSRF Protection
**Priority**: CRITICAL

### Test Cases:
1. **CSRF Token Validation**
   - [ ] Test all forms include CSRF tokens
   - [ ] Verify form submission fails without valid token
   - [ ] Check AJAX requests include CSRF validation
   - [ ] Test token regeneration

2. **Input Sanitization**
   - [ ] Test Input::get() is used instead of direct $_POST
   - [ ] Verify SQL injection protection
   - [ ] Check XSS prevention
   - [ ] Test malicious input handling

3. **Access Control**
   - [ ] Test unauthorized access to protected pages
   - [ ] Verify user can only edit their own cars
   - [ ] Check admin-only functionality restrictions

---

## TEST 8: Navigation and Redirects
**Priority**: HIGH

### Test Cases:
1. **Menu Navigation**
   - [ ] Test all main menu links work
   - [ ] Verify breadcrumb navigation
   - [ ] Check user-specific menu items

2. **Redirect Functionality**
   - [ ] Test backward compatibility redirects work
   - [ ] Verify 301 redirects for moved files
   - [ ] Check redirect after login
   - [ ] Test error page redirects

3. **URL Structure**
   - [ ] Verify all new URLs are functional
   - [ ] Test old URLs redirect properly
   - [ ] Check deep linking works

---

## TEST 9: Mobile Responsiveness and UI Consistency
**Priority**: MEDIUM

### Test Cases:
1. **Responsive Design**
   - [ ] Test on mobile viewport (320px+)
   - [ ] Check tablet viewport (768px+)
   - [ ] Verify desktop display (1200px+)
   - [ ] Test form functionality on mobile

2. **UI Consistency**
   - [ ] Verify consistent card layouts
   - [ ] Check header/footer consistency
   - [ ] Test button styling consistency
   - [ ] Verify color scheme consistency

3. **Accessibility**
   - [ ] Check form labels and ARIA attributes
   - [ ] Test keyboard navigation
   - [ ] Verify contrast ratios

---

## TEST 10: Performance and Load Testing
**Priority**: LOW

### Test Cases:
1. **Page Load Performance**
   - [ ] Test statistics page with large datasets
   - [ ] Check DataTables performance with many records
   - [ ] Verify image loading optimization

2. **Database Performance**
   - [ ] Test search queries with large datasets
   - [ ] Check index usage in new queries
   - [ ] Verify prepared statement performance

---

## Automated Testing
**Priority**: HIGH

### PHPUnit Tests:
1. **Run Existing Test Suite**
   ```bash
   vendor/bin/phpunit tests/
   ```
   - [ ] Verify all tests pass
   - [ ] Check test coverage reports
   - [ ] Review any failing tests

2. **Security Tests**
   - [ ] Run VerificationSecurityTest
   - [ ] Check InputSanitizationTest
   - [ ] Verify CarManagementTest

---

## Browser Console Checks
**Priority**: MEDIUM

### JavaScript Error Monitoring:
1. **Check for Console Errors**
   - [ ] Monitor browser console on all pages
   - [ ] Verify no JavaScript errors on form submissions
   - [ ] Check AJAX request success/failure
   - [ ] Test Google Maps/Charts loading

2. **Network Tab Analysis**
   - [ ] Verify all resources load successfully (200 status)
   - [ ] Check no 404 errors for moved files
   - [ ] Monitor AJAX request/response times

---

## Database Integrity Checks
**Priority**: HIGH

### Test Cases:
1. **Data Validation**
   - [ ] Verify car records are inserted correctly
   - [ ] Check audit trail (cars_hist) is maintained
   - [ ] Test foreign key constraints
   - [ ] Verify user-car relationships

2. **Security Validation**
   - [ ] Check prepared statements are used
   - [ ] Verify no direct SQL concatenation
   - [ ] Test input parameter binding

---

## Post-Test Validation
**Priority**: CRITICAL

### Required Actions:
1. **Document Issues**
   - [ ] Log any bugs found during testing
   - [ ] Create GitHub issues for critical problems
   - [ ] Prioritize fixes based on severity

2. **Performance Baseline**
   - [ ] Record page load times
   - [ ] Document any performance regressions
   - [ ] Note improvements from refactoring

3. **Security Verification**
   - [ ] Confirm all CSRF protections work
   - [ ] Verify input sanitization is effective
   - [ ] Check session security improvements

---

## Test Execution Plan

### Phase 1: Critical Functionality (Day 1)
- Tests 1, 2, 4, 7 (Authentication, Car Management, Search, Security)

### Phase 2: Core Features (Day 2)  
- Tests 3, 5, 6, 8 (Uploads, Statistics, Contact, Navigation)

### Phase 3: Polish & Performance (Day 3)
- Tests 9, 10, Automated Tests, Console Checks

### Phase 4: Documentation & Sign-off
- Document results, create bug reports, get approval for merge

---

## Success Criteria
- [ ] All critical functionality tests pass
- [ ] No JavaScript console errors
- [ ] All PHPUnit tests pass
- [ ] Performance is equal or better than before
- [ ] Security improvements are validated
- [ ] Mobile responsiveness maintained

## Risk Assessment
- **HIGH RISK**: Car editing workflow, search functionality, security endpoints
- **MEDIUM RISK**: Statistics display, file uploads, navigation
- **LOW RISK**: UI consistency, performance optimizations

---

*This test plan should be executed systematically before merging the style-refactor branch to main.*