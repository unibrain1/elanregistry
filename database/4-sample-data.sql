-- ==================================================================
-- ELAN REGISTRY - SAMPLE USER CONFIGURATION
-- ==================================================================
-- This script adds a sample user and two sample cars (a production Sprint and
-- a 26R race car).
-- Run AFTER provisioning a schema (scripts/provision-schema.sh) — dev/test-only
-- sample data, not part of the standard provisioning path.
-- ==================================================================

-- ==================================================================
-- 1. CREATE SAMPLE USER ACCOUNT
-- ==================================================================

-- Add sample user account (ID 2, since admin gets ID 1 in fresh UserSpice)
-- Based on development admin user but with generic credentials
INSERT INTO `users` (
    `id`, 
    `email`, 
    `username`, 
    `password`, 
    `pin`, 
    `fname`, 
    `lname`, 
    `permissions`, 
    `logins`, 
    `account_owner`, 
    `account_id`, 
    `join_date`, 
    `last_login`, 
    `email_verified`, 
    `vericode`, 
    `active`, 
    `oauth_provider`, 
    `oauth_uid`, 
    `gender`, 
    `locale`, 
    `gpluslink`, 
    `picture`, 
    `created`, 
    `modified`, 
    `fb_uid`, 
    `un_changed`, 
    `msg_exempt`, 
    `protected`, 
    `dev_user`, 
    `msg_notification`, 
    `force_pr`, 
    `cloak_allowed`, 
    `vericode_expiry`, 
    `oauth_tos_accepted`, 
    `language`, 
    `account_mgr`
) VALUES (
    2,  -- Next available user ID
    'sample@elanregistry.org',  -- Generic email
    'sample_user',  -- Generic username
    '$2y$13$GdQC.A9Aa54maEU/v1BRgusWsHwjBjomUhMI4lU0Klnjk0DJOFjie',  -- Password: 'password123'
    '$2y$12$/xAft.7hBI7WCEnDyKaQquxq2QAuNfbpt7h7MEb0P7Vsv6UMLs0.m',  -- PIN hash
    'Sample',  -- First name
    'User',  -- Last name
    1,  -- Standard user permissions
    0,  -- Login count (starts at 0)
    1,  -- Account owner
    0,  -- Account ID
    NOW(),  -- Join date
    NULL,  -- Last login (NULL for new user)
    1,  -- Email verified
    'SampleUserVeriCode123',  -- Verification code
    1,  -- Active account
    NULL,  -- OAuth provider
    NULL,  -- OAuth UID
    NULL,  -- Gender
    NULL,  -- Locale
    NULL,  -- Google+ link
    NULL,  -- Picture
    NOW(),  -- Created timestamp
    NOW(),  -- Modified timestamp
    NULL,  -- Facebook UID
    0,  -- Username unchanged
    0,  -- Message exempt (regular user)
    0,  -- Not protected (not admin)
    1,  -- Development user flag
    1,  -- Message notifications enabled
    0,  -- Force password reset
    1,  -- Cloak allowed
    DATE_ADD(NOW(), INTERVAL 24 HOUR),  -- Verification code expires in 24 hours
    1,  -- OAuth terms accepted
    'en-US',  -- Language
    0  -- Account manager
) ON DUPLICATE KEY UPDATE 
    email = VALUES(email),
    username = VALUES(username),
    fname = VALUES(fname),
    lname = VALUES(lname);

-- ==================================================================
-- 2. CREATE SAMPLE USER PROFILE
-- ==================================================================

-- Add enhanced profile with geographic location data
INSERT INTO `profiles` (
    `id`,
    `user_id`, 
    `bio`, 
    `city`, 
    `state`, 
    `country`, 
    `lat`, 
    `lon`, 
    `website`
) VALUES (
    2,  -- Profile ID matches user ID
    2,  -- User ID reference
    'Sample user for testing and demonstration purposes. This user represents a typical Lotus Elan Registry member.',  -- Generic bio
    'Portland',  -- City
    'Oregon',  -- State  
    'United States',  -- Country
    45.51,  -- Latitude
    -122.68,  -- Longitude
    'https://www.elanregistry.org/'  -- Generic website
) ON DUPLICATE KEY UPDATE 
    bio = VALUES(bio),
    city = VALUES(city),
    state = VALUES(state),
    country = VALUES(country),
    lat = VALUES(lat),
    lon = VALUES(lon),
    website = VALUES(website);

-- ==================================================================
-- 3. SAMPLE USER PERMISSIONS
-- ==================================================================

-- Add user to standard User permission (permission_id = 1)
-- This is typically handled automatically by UserSpice, but we'll ensure it's set
INSERT INTO `user_permission_matches` (`user_id`, `permission_id`) VALUES 
(2, 1)  -- Standard User permission
ON DUPLICATE KEY UPDATE 
    user_id = VALUES(user_id),
    permission_id = VALUES(permission_id);

-- ==================================================================
-- 4. SAMPLE CAR RECORD
-- ==================================================================

-- Add sample cars owned by sample user.
--
-- `model` is a pipe-delimited "series|variant|type" string, not a free-text
-- name — it mirrors `car_models.model_value`. CarValidator::parseModel()
-- splits it apart and validateAndSanitizeFields() reassembles it after
-- checking the combination against `car_models`. `type` is the Lotus type
-- number in a char(3) column (these two cars use 36 and 26R; the reference
-- table also has 26, 45, and 50); the body style (FHC/DHC/Roadster) belongs
-- in `variant`. Both combinations below exist in the `car_models` reference
-- table and pass ChassisValidator for their year.
--
-- Car 1: 1973 Sprint FHC (production car, post-1970 YYMMBBXXXXC chassis)
INSERT INTO `cars` (
    `id`,
    `user_id`,
    `model`,
    `series`,
    `variant`,
    `year`,
    `type`,
    `chassis`,
    `color`,
    `engine`,
    `purchasedate`,
    `comments`,
    `image`,
    `fname`,
    `lname`,
    `email`,
    `city`,
    `state`,
    `country`,
    `lat`,
    `lon`,
    `website`,
    `ctime`,
    `mtime`,
    `vericode`,
    `last_verified`
) VALUES (
    1,  -- Car ID 1
    2,  -- Owned by sample_user (ID 2)
    'Sprint|FHC|36',
    'Sprint',
    'FHC',
    1973,
    '36',
    '7301019999B',  -- Post-1970 format: YYMMBBXXXXC
    'Signal Red',
    'ABC123',
    '2020-01-15',
    'Beautiful restored example with matching numbers. Recent full restoration completed in 2019. Engine rebuilt with unleaded head conversion. Transmission rebuilt. All suspension bushings replaced. New interior and soft top. Car shows excellent and drives beautifully.',
    '["img_5ff391578e2475.05615196.jpg","img_601c1c88b856c9.16445155.jpg","img_602018826108e6.23256961.jpg"]',  -- Real files in userimages/1/, each with all 5 resized variants
    'Sample',    -- Duplicated from user profile for performance
    'User',
    'sample@elanregistry.org',
    'Portland',
    'Oregon',
    'United States',
    45.51,
    -122.68,
    'https://www.elanregistry.org/',
    NOW(),
    NOW(),
    'SampleCarVeriCode123',
    NOW()
) ON DUPLICATE KEY UPDATE
    model = VALUES(model),
    series = VALUES(series),
    variant = VALUES(variant),
    year = VALUES(year),
    type = VALUES(type),
    chassis = VALUES(chassis),
    color = VALUES(color),
    engine = VALUES(engine);

-- Car 2: 1963 26R (race car). Gives the registry a Race-variant car, which
-- the statistics map classifies into the "26R" bucket — markerClassForSeries()
-- in app/assets/js/statistics.js keys on variant === 'race', and the map query
-- (StatisticsDataService::getMapPins) only returns rows with non-zero lat/lon.
-- Details are invented; real 26R records carry named owners and racing
-- provenance that must not be copied into a public fixture.
INSERT INTO `cars` (
    `id`,
    `user_id`,
    `model`,
    `series`,
    `variant`,
    `year`,
    `type`,
    `chassis`,
    `color`,
    `engine`,
    `purchasedate`,
    `comments`,
    `image`,
    `fname`,
    `lname`,
    `email`,
    `city`,
    `state`,
    `country`,
    `lat`,
    `lon`,
    `website`,
    `ctime`,
    `mtime`,
    `vericode`,
    `last_verified`
) VALUES (
    2,  -- Car ID 2
    2,  -- Owned by sample_user (ID 2)
    'S1|Race|26R',
    'S1',
    'Race',
    1963,
    '26R',
    '26-R-07',  -- 1963 race format: 26-R-xx
    'British Racing Green',
    'LP1234',
    '2018-06-01',
    'Sample competition car for demonstrating the 26R category. Twin-cam engine on Weber 45 DCOE carburettors, close-ratio gearbox, and period-correct knock-off wheels. Used here to exercise race-variant filtering and reporting; not a record of a real vehicle.',
    '',  -- No sample images for this car
    'Sample',
    'User',
    'sample@elanregistry.org',
    'Portland',
    'Oregon',
    'United States',
    45.51,
    -122.68,
    'https://www.elanregistry.org/',
    NOW(),
    NOW(),
    'SampleCarVeriCode26R',
    NOW()
) ON DUPLICATE KEY UPDATE
    model = VALUES(model),
    series = VALUES(series),
    variant = VALUES(variant),
    year = VALUES(year),
    type = VALUES(type),
    chassis = VALUES(chassis),
    color = VALUES(color),
    engine = VALUES(engine);

-- ==================================================================
-- 5. SAMPLE CAR OWNERSHIP RELATIONSHIP
-- ==================================================================

-- Ownership is recorded via cars.user_id (set during car INSERT above).
-- The car_user junction table was removed in migration 20260711000000.

-- ==================================================================
-- 6. SAMPLE CAR HISTORY RECORD
-- ==================================================================

-- Nothing to do here. The `cars_insert` AFTER INSERT trigger writes the
-- matching `cars_hist` row automatically for every car inserted above —
-- inserting one by hand would duplicate the audit trail.

-- ==================================================================
-- 7. RECORD SUCCESSFUL COMPLETION
-- ==================================================================

-- Record this sample user and car creation
INSERT INTO `fix_script_runs` (`script_name`)
VALUES ('database/4-sample-data.sql')
ON DUPLICATE KEY UPDATE
    completed_at = CURRENT_TIMESTAMP;

-- ==================================================================
-- 8. VERIFICATION QUERIES
-- ==================================================================

-- Display sample user information
SELECT 'Sample user created:' as status;
SELECT 
    id,
    email,
    username,
    fname,
    lname,
    join_date,
    active,
    email_verified,
    permissions
FROM users WHERE id = 2;

-- Display profile information  
SELECT 'Sample user profile:' as status;
SELECT 
    user_id,
    city,
    state,
    country,
    website,
    CONCAT(ROUND(lat, 2), ', ', ROUND(lon, 2)) as coordinates
FROM profiles WHERE user_id = 2;

-- Display sample car information
SELECT 'Sample cars created:' as status;
SELECT
    id,
    model,
    series,
    variant,
    year,
    type,
    chassis,
    color,
    engine,
    purchasedate,
    SUBSTRING(comments, 1, 50) as comments_preview,
    image
FROM cars WHERE id IN (1, 2);

-- Display car ownership
SELECT 'Sample car ownership:' as status;
SELECT
    c.id AS car_id,
    c.user_id AS userid,
    u.username
FROM cars c
JOIN users u ON c.user_id = u.id
WHERE c.id IN (1, 2);

-- Display car history (written by the cars_insert trigger, not by this file)
SELECT 'Sample car history:' as status;
SELECT
    car_id,
    operation,
    timestamp,
    model,
    series,
    chassis,
    color
FROM cars_hist WHERE car_id IN (1, 2);

-- Display permissions
SELECT 'Sample user permissions:' as status;
SELECT 
    u.username,
    p.name as permission_name,
    p.descrip as permission_description
FROM users u
JOIN user_permission_matches upm ON u.id = upm.user_id
JOIN permissions p ON upm.permission_id = p.id
WHERE u.id = 2;

-- ==================================================================
-- MANUAL CONFIGURATION NOTES:
-- ==================================================================

/*
SAMPLE USER CREDENTIALS:

Username: sample_user
Email: sample@elanregistry.org
Password: password123
PIN: (matches original admin PIN hash)

IMPORTANT NOTES:

1. **Change Default Password**: 
   - The sample user uses a default password 'password123'
   - Change this immediately after first login
   - Consider forcing password reset on first login

2. **Update Email Address**:
   - Replace sample@elanregistry.org with a real email for testing
   - Ensure email domain is properly configured for notifications

3. **Geographic Location**:
   - Sample user is located in Portland, Oregon
   - Coordinates: 45.51, -122.68
   - This provides a reference point for testing location features

4. **Permission Level**:
   - Sample user has standard User permissions (level 1)
   - Can register cars, edit own cars, use contact features
   - Cannot access administrative functions

5. **Development Flag**:
   - User has dev_user = 1 for development identification
   - Set to 0 for production environments if needed

6. **Sample Car Details**:
   - Car ID 1: 1973 Lotus Elan Sprint FHC (model 'Sprint|FHC|36')
     - Chassis: 7301019999B (post-1970 YYMMBBXXXXC format)
     - Color: Signal Red, Engine: ABC123
     - Complete restoration story with detailed comments
     - 3 real images from userimages/1/, each with all 5 resized variants
   - Car ID 2: 1963 Lotus Elan 26R race car (model 'S1|Race|26R')
     - Chassis: 26-R-07 (1963 race format, 26-R-xx)
     - Color: British Racing Green, Engine: LP1234
     - No images; invented details, not a real vehicle
     - Provides a Race-variant car for the statistics map's 26R filter
   - History rows for both are created by the cars_insert trigger

7. **Testing Purposes**:
   - Use for testing car registration and editing workflows
   - Test location-based features and mapping
   - Verify user permission restrictions
   - Test contact and communication features
   - Test car image display and management
   - Test car sharing and ownership features

8. **Security Considerations**:
   - Sample user is created with email_verified = 1 
   - Account is immediately active for testing
   - Two-factor authentication is disabled by default
   - Consider enabling 2FA for production testing

RECOMMENDED TESTING WORKFLOW:
1. Login as sample_user with password 'password123'
2. View and edit the sample cars (Car IDs 1 and 2)
3. Test car image display and upload functionality
4. Verify location features work correctly  
5. Test contact forms and owner communication
6. Verify permission restrictions (cannot access admin features)
7. Test profile updates and geographic data sync
8. Test car sharing functionality between users
*/