<?php
/**
 * Test runner script for Elan Registry automated tests
 * 
 * This script provides an easy way to run the automated test suite
 * and generate reports for the car update functionality.
 */

// Set up environment for testing
require_once __DIR__ . '/../users/init.php';

echo "==============================================\n";
echo "Elan Registry - Car Update Test Suite\n";
echo "==============================================\n\n";

// Check if PHPUnit is available
if (!class_exists('PHPUnit\Framework\TestCase')) {
    echo "❌ PHPUnit not found. Please install PHPUnit:\n";
    echo "   composer require --dev phpunit/phpunit\n\n";
    exit(1);
}

// Test configuration
$testClasses = [
    'CarUpdateTest' => 'Core car update functionality',
    'FileUploadSecurityTest' => 'File upload security validations'
];

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

echo "🧪 Running automated test suite...\n\n";

foreach ($testClasses as $className => $description) {
    echo "📋 Testing: $description ($className)\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        // Load test class
        require_once __DIR__ . "/$className.php";
        
        // Create test suite
        $suite = new PHPUnit\Framework\TestSuite();
        $suite->addTestSuite($className);
        
        // Run tests
        $runner = new PHPUnit\TextUI\TestRunner();
        $result = $runner->run($suite);
        
        // Collect results
        $classTests = $result->count();
        $classPassed = $classTests - $result->errorCount() - $result->failureCount();
        $classFailed = $result->errorCount() + $result->failureCount();
        
        $totalTests += $classTests;
        $passedTests += $classPassed;
        $failedTests += $classFailed;
        
        if ($classFailed === 0) {
            echo "✅ All tests passed! ($classPassed/$classTests)\n";
        } else {
            echo "❌ Some tests failed! ($classPassed/$classTests passed, $classFailed failed)\n";
            
            // Show failure details
            foreach ($result->failures() as $failure) {
                echo "   💥 " . $failure->getTestName() . ": " . $failure->getMessage() . "\n";
            }
            
            foreach ($result->errors() as $error) {
                echo "   🚨 " . $error->getTestName() . ": " . $error->getMessage() . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Test suite failed to run: " . $e->getMessage() . "\n";
        $failedTests++;
    }
    
    echo "\n";
}

// Final summary
echo "==============================================\n";
echo "📊 TEST SUMMARY\n";
echo "==============================================\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests ✅\n";
echo "Failed: $failedTests " . ($failedTests > 0 ? '❌' : '✅') . "\n";
echo "Success Rate: " . round(($passedTests / max($totalTests, 1)) * 100, 1) . "%\n\n";

if ($failedTests === 0) {
    echo "🎉 All tests passed! The car update functionality is working correctly.\n\n";
    echo "Security validations tested:\n";
    echo "✅ File upload security (MIME validation, size limits, secure filenames)\n";
    echo "✅ Input validation and sanitization\n";
    echo "✅ CSRF token protection\n";
    echo "✅ Directory traversal prevention\n";
    echo "✅ Data validation and formatting\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the failures above and fix the issues.\n\n";
    exit(1);
}