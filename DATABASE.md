# Database Schema Documentation

## Database Overview
* **Database Name:** `unibrain_registry`
* **Generated:** August 12, 2025
* **MySQL Version:** `8.0.39-cll-lve`
* **Character Set:** `UTF-8/Latin1` (mixed)

---

## Current Documentation Includes:

### **Core User Management**
- `users` table with authentication and profile information
- `profiles` table with extended user data including geographic coordinates

### **Car Registry System** 
- `cars` table for vehicle records with comprehensive details
- `cars_hist` for complete audit trail of all car changes
- `car_user` junction table enabling car sharing between users
- `car_user_hist` for audit trail of sharing relationships
- `elan_factory_info` for Lotus Elan factory reference data

### **Database Views**
- `usersview` - combines user and profile information
- `users_carsview` - comprehensive view of car ownership and sharing

### **Key Relationships**
- Users have profiles (one-to-one)
- Users can own cars directly (one-to-many)
- Users can share cars with other users (many-to-many via `car_user`)
- Complete audit trails for all car-related changes

---

## Key Relationships

### User Management
* **`users` ↔ `profiles`**: One-to-one relationship

### Car Registry
* **`users` ↔ `cars`**: One-to-many (direct ownership via `user_id`)
* **`users` ↔ `cars`**: Many-to-many (shared access via `car_user`)
* **`cars` → `cars_hist`**: One-to-many audit trail
* **`car_user` → `car_user_hist`**: One-to-many audit trail

---

## User Deletion Process

### Automatic History Tracking
Database triggers automatically capture all car ownership changes in `cars_hist`:

* **`cars_insert`** - Records new car creation (AFTER INSERT)
* **`cars_update`** - Records ownership transfers and modifications (AFTER UPDATE)
  - Uses `@disable_triggers` variable to allow bypassing when needed
  - Captures OLD values before changes for complete audit trail
* **`cars_delete`** - Records car deletion (AFTER DELETE)

All ownership changes during user deletion are automatically logged without additional code.

### User Deletion Cleanup Process
When users are deleted via `deleteUsers()` function in `/users/helpers/users.php`, the cleanup process:

1. **Profile Cleanup**: Remove orphaned records from `profiles` table
2. **Car Access Cleanup**: Remove user's records from `car_user` junction table  
3. **Car Reassignment**: Transfer car ownership to `noowner` user (preserves car data)
4. **Automatic Audit**: All changes logged in `cars_hist` via database triggers

**Implementation**: `/usersc/scripts/after_user_deletion.php`
- Dynamically finds `noowner` user (no hardcoded IDs)
- Preserves car-user relationships by reassigning to `noowner`
- Includes fallback handling if `noowner` user missing
- Comprehensive GDPR-compliant audit logging

### Maintenance Scripts
Administrative cleanup utilities in `/FIX/` directory:

* **`/FIX/02-Cleanup-Orphaned-Profiles.php`** - Cleanup utility for orphaned records
  - Identifies and removes orphaned `profiles` records
  - Cleans up orphaned `car_user` relationships  
  - Reassigns ownerless cars to `noowner` user
  - Real-time progress display with before/after statistics

---

## Core Tables

### User Management

#### `users`
Primary user account table containing authentication and profile information.

##### Special System Accounts

The registry uses special system accounts for critical operations:

| Username | ID | Purpose | Description |
| :--- | :--- | :--- | :--- |
| `noowner` | 83 | Car Ownership Fallback | Receives ownership of cars when users are deleted for GDPR compliance |
| `admin` | 1 | System Administration | Primary administrative account with full system access |

**Important Notes:**
- **Dynamic Lookup**: The `noowner` user is located dynamically by username, not hardcoded ID
- **GDPR Compliance**: Cars are transferred to `noowner` instead of being deleted to preserve registry data
- **Fallback Handling**: System gracefully handles missing `noowner` user with appropriate logging

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | `int` | PRIMARY KEY, AUTO_INCREMENT | Unique user identifier |
| `email` | `varchar(155)` | NOT NULL, INDEX | User's email address |
| `email_new` | `varchar(155)` | NULL | New email during change process |
| `username` | `varchar(255)` | NOT NULL | Display username |
| `password` | `varchar(255)` | NULL | Encrypted password |
| `pin` | `varchar(255)` | NULL | Security PIN |
| `fname` | `varchar(255)` | NOT NULL | First name |
| `lname` | `varchar(255)` | NOT NULL | Last name |
| `permissions` | `int` | NOT NULL | Permission level |
| `logins` | `int UNSIGNED` | NOT NULL | Login count |
| `account_owner` | `tinyint` | NOT NULL, DEFAULT 1 | Account ownership flag |
| `account_id` | `int` | NOT NULL, DEFAULT 0 | Associated account ID |
| `company` | `text` | NULL | Company information |
| `join_date` | `datetime` | NOT NULL | Registration date |
| `last_login` | `datetime` | NOT NULL | Last login timestamp |
| `email_verified` | `tinyint` | NOT NULL, DEFAULT 0 | Email verification status |
| `vericode` | `varchar(15)` | NOT NULL | Verification code |
| `active` | `int` | NOT NULL | Account active status |
| `oauth_provider` | `text` | NULL | OAuth provider name |
| `oauth_uid` `oauth_uid` | `text` | NULL | OAuth user ID |
| `twoEnabled` | `int` | DEFAULT 0 | Two-factor authentication enabled |
| `twoKey` | `varchar(16)` | NULL | 2FA key |
| `language` | `varchar(15)` | DEFAULT 'en-US' | User language preference |

#### `profiles`
Extended user profile information including location data.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | PRIMARY KEY |
| `user_id` | `int` | Foreign key to `users.id` |
| `bio` | `text` | User biography |
| `city` | `varchar(100)` | City |
| `state` | `varchar(100)` | State/Province |
| `country` | `varchar(100)` | Country |
| `lat` | `float` | Latitude coordinate |
| `lon` | `float` | Longitude coordinate |
| `website` | `varchar(100)` | Personal website URL |

---

### Car Registry System

#### `cars`
Main car registry table storing vehicle information and ownership.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `int UNSIGNED` | PRIMARY KEY, AUTO_INCREMENT |
| `username` | `varchar(30)` | Owner username |
| `ctime` | `timestamp` | Creation time |
| `mtime` | `timestamp` | Last modification time |
| `vericode` | `varchar(32)` | Verification code |
| `last_verified` | `timestamp` | Last verification date |
| `ModifiedBy` | `varchar(30)` | Last modifier |
| `model` | `varchar(30)` | Car model |
| `series` | `varchar(12)` | Car series |
| `variant` | `varchar(15)` | Car variant |
| `year` | `varchar(4)` | Manufacturing year |
| `type` | `char(3)` | Vehicle type code |
| `chassis` | `varchar(15)` | Chassis number |
| `color` | `varchar(25)` | Vehicle color |
| `engine` | `varchar(15)` | Engine specification |
| `purchasedate` | `date` | Purchase date |
| `solddate` | `date` | Sale date |
| `comments` | `text` | Additional notes |
| `image` | `text` | Image data/URL |
| `user_id` | `int` | Associated user ID |
| `email` | `varchar(155)` | Owner email |
| `fname` | `varchar(155)` | Owner first name |
| `lname` | `varchar(155)` | Owner last name |

#### `cars_hist`
Audit trail for car record changes with operation tracking.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | PRIMARY KEY |
| `operation` | `varchar(32)` | Operation type (INSERT/UPDATE/DELETE) |
| `car_id` | `int UNSIGNED` | Original car ID |
| `timestamp` | `timestamp` | Change timestamp |
| *(All other columns mirror `cars` table)* | | |

#### `car_user`
Junction table for car-user relationships (many-to-many).

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | PRIMARY KEY |
| `userid` | `int` | User ID |
| `carid` | `int` | Car ID |
| `mtime` | `timestamp` | Relationship modification time |

#### `car_user_hist`
Audit trail for car-user relationship changes.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | PRIMARY KEY |
| `operation` | `varchar(32)` | Operation type (INSERT/UPDATE/DELETE) |
| `carid` | `int` | Car ID |
| `userid` | `int` | User ID |
| `timestamp` | `timestamp` | Change timestamp |

#### `elan_factory_info`
Factory information for Elan vehicles.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | PRIMARY KEY |
| `year` | `varchar(4)` | Manufacturing year |
| `month` | `varchar(2)` | Manufacturing month |
| `batch` | `varchar(4)` | Production batch |
| `type` | `varchar(2)` | Vehicle type |
| `serial` | `varchar(5)` | Serial number |
| `suffix` | `varchar(1)` | Serial suffix (after 1970) |
| `engineletter` | `varchar(3)` | Engine designation letter |
| `enginenumber` | `varchar(10)` | Engine number |
| `gearbox` | `varchar(1)` | Gearbox type |
| `color` | `varchar(256)` | Factory color |
| `builddate` | `date` | Build/invoice/registration date |
| `note` | `text` | Additional notes |

---

### Reference Tables

#### `country`
Country reference data.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `int` | PRIMARY KEY |
| `name` | `varchar(100)` | Country name |

---
## Database Triggers

### Car Audit Triggers
* `cars_insert`: Logs new car registrations to `cars_hist`
* `cars_update`: Logs car modifications to `cars_hist` (with trigger disable option)
* `cars_delete`: Logs car deletions to `cars_hist`

### Car-User Relationship Triggers
* `car_user_insert/update/delete`: Maintains audit trail in `car_user_hist`


## Database Views

### `users_carsview`
Complex view combining car, user, and profile information with user-car relationships.

* Includes cars linked through `car_user` junction table
* Also includes orphaned cars (no user associations)
* Provides comprehensive car ownership and user details

#### `usersview`
Combines user and profile data for convenient querying.

```sql
SELECT users.id, email, fname, lname, username, join_date,
       last_login, logins, force_pr, email_verified, permissions,
       city, state, country, lat, lon, website
FROM users JOIN profiles ON users.id = profiles.user_id
```