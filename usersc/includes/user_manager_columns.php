<?php
/**
 * User Manager Columns Configuration
 *
 * This file allows you to customize the columns and query used in the admin users table.
 * You can add, remove, or modify columns as needed for your application.
 * Most of the time, you just want to show something extra from the users table, so you can
 * do that by adding to the $user_manager_columns array and updating the switch statement if
 * you want to do something special
 */

use ElanRegistry\OwnerView;

// Define the column headers for the table
$user_manager_columns = [
    'id' => 'ID',
    'force_pr' => '', // Lock icon column
    'username' => 'Username',
    'name' => 'Name',
    'email' => 'Email',
    'last_login' => 'Last Sign In',
    'perms' => 'Permissions',
    'status' => 'Status',
];

// Define column data handlers
// This function takes a user object and column name and returns the HTML for that cell
// Only define special cases here - standard columns will be handled by the default case
//
// SECURITY (#1499): the perms and default cases below escape their values because
// _admin_users.php echoes this closure's return value directly into a <td> with no
// escaping of its own — this closure is the last line of defense against stored XSS
// from usernames/emails/permission names. Do not remove even if this file is refactored.
//
// Note: values written via legacy UserSpice \Input::get() (e.g. core registration/
// admin-create flows) are already htmlspecialchars-encoded at storage time, so those
// rows will render double-encoded here (e.g. O'Brien -> O&#039;Brien on screen). This
// is a known cosmetic tradeoff, not a security regression — escaping stays because
// ElanRegistry-side writes use ElanRegistry\Input::raw() and store unescaped text.
//
// INCLUDE CONTRACT: $act, $uCount and $maxUsers come from the including scope —
// this file is never executed standalone. Both known loaders set all three
// before loading it:
//   - users/views/_admin_users.php (assigns them near the top, then includes
//     this file behind a file_exists() guard, falling back to
//     users/includes/user_manager_columns.php when it is absent). Note this
//     file is gitignored upstream UserSpice: it exists in a real install but
//     not in a fresh clone or CI checkout, so verify against an install and
//     grep for the three assignments rather than trusting line numbers.
//   - tests/unit/security/UserManagerColumnsXssTest::loadColumnDataClosure(),
//     which require()s this file with all three seeded as parameters.
// PHPStan analyses this file standalone and cannot see either, hence the
// ignores below.
//
// Prefer fixing a future caller over adding ??= defaults here: a default lets a
// caller that forgot to set these render silently wrong instead of failing
// loudly. $act is the site-wide email-activation setting, not per-user status,
// so defaulting it to 0 hides the verified-email icon even for users whose
// email_verified is 1; a defaulted $uCount/$maxUsers pair silently decides
// whether the perms column renders at all.
/**
 * @phpstan-ignore variable.undefined, variable.undefined, variable.undefined
 */
$user_manager_column_data = function($user, $column) use ($act, $uCount, $maxUsers) {
    switch($column) {
        case 'id':
            return '<span class="hideMe">' . sprintf('%08d', $user->id) . '</span>
                <a class="nounderline text-body" href="admin.php?view=user&id=' . $user->id . '">' . $user->id . '</a>';

        case 'force_pr':
            if ($user->force_pr == 1) {
                return '<a class="nounderline text-danger" href="admin.php?view=user&id=' . $user->id . '"><i class="fa fa-lock"></i></a>';
            }
            return '';

        case 'name':
            return '<a class="nounderline text-body" href="admin.php?view=user&id=' . $user->id . '">' . OwnerView::displayName($user) . '</a>';

        case 'last_login':
            if ($user->last_login != "0000-00-00 00:00:00") {
                return $user->last_login;
            } else {
                return '<i>Never</i>';
            }

        case 'perms':
            if ($uCount < $maxUsers) {
                return htmlspecialchars((string) ($user->perms ?? ''), ENT_QUOTES, 'UTF-8');
            }
            return null; // Don't show this column

        case 'status':
            $html = '';
            if($user->permissions == 0) {
                $html .= '<i class="fa fa-fw fa-lock text-danger" data-bs-toggle="tooltip" title="The users\'s account locked (banned)"></i>';
            } else {
                $html .= '<i class="fa fa-fw fa-unlock" data-bs-toggle="tooltip" title="The users\'s account unlocked (active)"></i>';
            }

            if ($act == 1 && $user->email_verified == 1) {
                $html .= ' <i class="fa fa-envelope" data-bs-toggle="tooltip" title="User email is verified"></i>';
            }
            return $html;

        default:
            return isset($user->$column) ? htmlspecialchars((string) $user->$column, ENT_QUOTES, 'UTF-8') : '';
    }
};

// Define the query function based on the search mode
$user_manager_get_data = function($settings, $db, $uCount, $maxUsers) {
    if($settings->uman_search == 0) {
        $showAllUsers = Input::get('showAllUsers');

        if ($showAllUsers == 1) {
            if ($uCount < $maxUsers) {
                $userData = $db->query("SELECT
                    u.*,
                    group_concat(p.name SEPARATOR ', ') AS perms
                    FROM users AS u
                    JOIN user_permission_matches AS upm ON u.id = upm.user_id
                    LEFT OUTER JOIN permissions AS p ON p.id = upm.permission_id
                    GROUP BY u.id
                ")->results();
            } else {
                $userData = fetchAllUsers('permissions DESC,id', false, true);
            }
        } else {
            if ($uCount < $maxUsers) {
                $userData = $db->query("SELECT
                    u.*,
                    group_concat(p.name SEPARATOR ', ') AS perms
                    FROM users AS u
                    JOIN user_permission_matches AS upm ON u.id = upm.user_id
                    LEFT OUTER JOIN permissions AS p ON p.id = upm.permission_id
                    WHERE u.active = 1
                    GROUP BY u.id
                ")->results();
            } else {
                $userData = fetchAllUsers('permissions DESC,id', false, false);
            }
        }
    } else {
        // Search using the search form
        if(!empty($_POST['search'])) {
            $search = Input::get('searchTerm');
            $userData = $db->query("SELECT
                u.*,
                group_concat(p.name SEPARATOR ', ') AS perms
                FROM users AS u
                JOIN user_permission_matches AS upm ON u.id = upm.user_id
                LEFT OUTER JOIN permissions AS p ON p.id = upm.permission_id
                WHERE fname LIKE ? OR lname LIKE ? OR username LIKE ? OR email LIKE ?
                GROUP BY u.id
            ", ["%$search%", "%$search%", "%$search%", "%$search%"])->results();
        } else {
            $userData = new stdClass();
        }
    }

    return $userData;
};

/**
 * CUSTOMIZATION EXAMPLES:
 *
 * 1. To add a simple column from the users table (e.g., phone):
 *
 * $user_manager_columns['phone'] = 'Phone Number';
 *
 * That's it! The default case will automatically display $user->phone if it exists.
 *
 *
 * 2. To add a column with custom formatting:
 *
 * $user_manager_columns['created_date'] = 'Member Since';
 *
 * Then in the switch statement in $user_manager_column_data, add:
 *
 * case 'created_date':
 *     return date('M j, Y', strtotime($user->join_date));
 *
 *
 * 3. To remove a column:
 *
 * Just remove it from the $user_manager_columns array.
 *
 *
 * 4. To modify the query to include additional data from other tables:
 *
 * Modify the SELECT statements in $user_manager_get_data to include your fields.
 * For example, to join with a profile table:
 *
 * SELECT u.*, up.bio, group_concat(p.name SEPARATOR ', ') AS perms
 * FROM users AS u
 * LEFT JOIN user_profiles AS up ON u.id = up.user_id
 * JOIN user_permission_matches AS upm ON u.id = upm.user_id
 * ...
 *
 * Then add to the columns array:
 * $user_manager_columns['bio'] = 'Biography';
 */
