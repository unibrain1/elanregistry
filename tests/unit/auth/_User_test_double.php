<?php

declare(strict_types=1);

/**
 * Test double for \User, used only by RegistrationRecoveryNotifierTest.
 *
 * The real users/classes/User.php is not loaded in the unit-test environment
 * (its constructor calls the live DB::getInstance() singleton and is not
 * mockable via constructor injection), and it is not part of this project's
 * PSR-4 autoload map, so \User does not exist here unless something defines
 * it. That makes a plain stand-in class named `User` the simplest and most
 * robust option — it satisfies the \User type hint directly with no
 * reflection or subclassing tricks needed. It implements only the two
 * methods RegistrationRecoveryNotifier::notifyIfAccountExists() calls:
 * exists() and data().
 *
 * IMPORTANT: Kept in a separate file (#1566), not inline in the test class.
 * PHP has no per-file scoping for global classes. `tests/` entered PHPStan's
 * scan path in #1555, and PHPStan has no other way to learn what \User looks
 * like (users/ is excluded from phpstan.neon's `paths`) — so when this
 * declaration lived inside RegistrationRecoveryNotifierTest.php, PHPStan
 * adopted this double's narrow 2-method surface as *the* definition of
 * \User project-wide, misreporting real usages elsewhere (usersc/join.php,
 * usersc/login.php, tests/integration/*.php calling the real User::find(),
 * etc.) against a shape that was never meant to represent the whole class.
 * This file is listed in phpstan.neon's `excludePaths`, so PHPStan never
 * sees this declaration at all and \User goes back to being genuinely
 * unanalyzable everywhere — covered by the same `ignoreErrors` patterns
 * already relied on for DB/Token/Input, instead of resolving to a
 * misleadingly narrow double. This is the same class-shape-adoption problem
 * phpstan.neon's `ignoreErrors` comment block describes for DB/Token/Input's
 * call-site mismatches — here it manifests as PHPStan defining \User's
 * shape outright, rather than misreporting individual method calls.
 */
if (!class_exists('User')) {
    class User {
        private bool $_exists;
        private object $_data;

        public function __construct(bool $exists = false, ?object $data = null) {
            $this->_exists = $exists;
            $this->_data = $data ?? new \stdClass();
        }

        public function exists(): bool {
            return $this->_exists;
        }

        public function data(): object {
            return $this->_data;
        }
    }
}
