<?php
declare(strict_types=1);

use ElanRegistry\ApiResponse;
use ElanRegistry\Exceptions\LocationServiceException;
use ElanRegistry\LocationService;
use ElanRegistry\LogCategories;

/**
 * AJAX Endpoint: Location Reverse Geocoding
 *
 * Converts GPS coordinates (lat/lon) to standardized address format
 * using Nominatim API via LocationService.
 *
 * Used for HTML5 Geolocation (GPS) feature in location picker.
 *
 * @package ElanRegistry
 * @version 2.12.0
 * @since 2.11.0
 * @link https://github.com/unibrain1/elanregistry/issues/245
 */

require_once '../../../users/init.php';

// Only allow POST requests
if ($method !== 'POST') {
    ApiResponse::error('Method not allowed', 405)->send();
}

// Get user ID (null for anonymous users during registration)
$userId = $user->isLoggedIn() ? (int)$user->data()->id : null;
$logUserId = $userId ?? 0;

// Verify CSRF token (required for all requests)
if (!Token::check(Input::get('csrf'))) {
    ApiResponse::forbidden('Invalid CSRF token')
        ->withLogging($logUserId, LogCategories::LOG_CATEGORY_SECURITY, 'Invalid CSRF token in location reverse')
        ->send();
}

try {
    // Get coordinates
    $lat = Input::get('lat');
    $lon = Input::get('lon');

    if (!is_numeric($lat) || !is_numeric($lon)) {
        throw new LocationServiceException('Latitude and longitude are required');
    }

    // Convert to float
    $lat = (float)$lat;
    $lon = (float)$lon;

    // Create LocationService instance and reverse geocode
    $locationService = new LocationService();
    $result = $locationService->reverseGeocode($lat, $lon, $userId);

    // Return result
    ApiResponse::success('Reverse geocoding completed')
        ->withData('location', $result)
        ->send();

} catch (LocationServiceException $e) {
    // Location service specific error (rate limit, API failure, invalid coordinates)
    ApiResponse::error($e->getMessage(), 400)
        ->withLogging($logUserId, LogCategories::LOG_CATEGORY_LOCATION_REVERSE, 'Reverse geocoding failed: ' . $e->getMessage())
        ->send();

} catch (\Throwable $e) {
    // Catch-all for unexpected errors (database, file system, etc.)
    ApiResponse::serverError('An error occurred while reverse geocoding coordinates')
        ->withLogging($logUserId, LogCategories::LOG_CATEGORY_SYSTEM_ERROR, 'Reverse geocoding error: ' . $e->getMessage())
        ->send();
}
