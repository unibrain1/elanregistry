<?php
/**
 * Secure DataTables server-side processing endpoint
 * 
 * Replaces the vulnerable getList.php with secure implementation using Car class.
 * Uses prepared statements and input validation to prevent SQL injection.
 * 
 * @author Elan Registry Security Team
 * @copyright 2025
 */

require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/classes/Car.php';

// Security: Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// Security: Verify CSRF token
if (Input::exists('post')) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        http_response_code(403);
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
        exit();
    }
    
    try {
        // Handle complex DataTables parameters more carefully
        $draw = (int) Input::get('draw');
        $start = (int) Input::get('start');
        $length = (int) Input::get('length');
        $table = Input::get('table');
        
        // Get search value from nested array
        $searchValue = '';
        $searchData = Input::get('search');
        if (is_array($searchData) && isset($searchData['value'])) {
            $searchValue = htmlspecialchars(strip_tags($searchData['value']), ENT_QUOTES, 'UTF-8');
        }
        
        $request = [
            'draw' => $draw,
            'start' => $start,
            'length' => $length,
            'search' => [
                'value' => $searchValue
            ],
            'order' => Input::get('order') ?? [],
            'columns' => Input::get('columns') ?? []
        ];
        
        // Validate table parameter
        if (!in_array($table, ['cars', 'factory'], true)) {
            throw new InvalidArgumentException('Invalid table parameter: ' . $table);
        }
        
        // Use Car class for secure data retrieval
        $car = new Car();
        $response = $car->getDataTablesData($request, $table);
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Log error for debugging (don't expose to client)
        error_log("DataTables error: " . $e->getMessage());
        error_log("DataTables error trace: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'error' => 'Server error occurred',
            'draw' => (int) Input::get('draw'),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
}