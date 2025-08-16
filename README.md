
# The Lotus Elan Registry

This is code for the Lotus Elan Registry hosted at https://elanregistry.org. It is used in conjunction with Userspice (https://userspice.com) to build an online database for Lotus Elan and Lotus Elan +2 cars.

This is the Registry for the 1963 thru 1973 Lotus Elan and the 1967 thru 1974 Lotus Elan Plus 2. The purpose of the registry is to keep a history of the cars, trace the evolution of the Lotus Elan and to facilitate owner communication.

## Privacy & GDPR Compliance

- The registry is GDPR-compliant and has a clear privacy policy.
- See `PRIVACY.md` for full details on data collection, protection, and user rights.
- The privacy policy is also available as a webpage at `/app/privacy.php`.
- Admins do their best to keep names and emails private; location data is intentionally imprecise for privacy.
- Google Analytics is used for admin/statistics purposes only.

## Project Improvements & TODOs

- See `TODO.md` for a prioritized list of style, security, and organization improvements.
- The project is actively being refactored for better layout consistency, security, and maintainability.

Some History on the Registry
-----------------------------
The Lotus Elan Registry started in January 2003. A thread on LotusElan.net asked the question, Does anybody know if there is a Lotus Elan register? (http://www.lotuselan.net/forums/lotus-elan-f19/lotus-elan-register-t349.html) I bashed together a registry and a few years later we have over 300 cars accounted for with more added every month.  That was Version 1 and had some very serious pitfalls.  


Many people on the Elan mailing list and the Elan forums have helped with the registry. Little things like asking for there to be a registry, helping test, providing pictures and feedback on what should be included. This is their work. I am just the one who assembled the pieces.

Special thanks to Ross, Tim, Gary, Ed, Terry, Peter, Jeff, Nicholas, Alan, Christian, Michael, Stan, Jason and everyone else who has contributed and will continue to make the registry what it is. A place for us to obsess over little British cars.

## **Current Documentation Includes:**

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


# Database Schema Documentation

## Database Overview
* **Database Name:** `unibrain_registry`
* **Generated:** August 12, 2025
* **MySQL Version:** `8.0.39-cll-lve`
* **Character Set:** `UTF-8/Latin1` (mixed)

---

## Key Relationships

### User Management
* **`users` ↔ `profiles`**: One-to-one relationship

### Car Registry
* **`users` ↔ `cars`**: One-to-many (direct ownership via `user_id`)
* **`users` ↔ `cars`**: Many-to-many (shared access via `car_user`)
* **`cars` → `cars_hist`**: One-to-many audit trail
* **`car_user` → `car_user_hist`**: One-to-many audit trail


## Core Tables

### User Management

#### `users`
Primary user account table containing authentication and profile information.

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

