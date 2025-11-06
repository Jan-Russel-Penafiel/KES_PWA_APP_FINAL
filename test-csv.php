<?php
// Simple CSV test file
// Test URL: test-csv.php

require_once 'config.php';

// Test data structures that might cause the array to string conversion error
$simpleData = [
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
];

$complexData = [
    'section' => ['section_name' => 'Grade 10-A', 'grade_level' => '10'],
    'students' => [
        ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
    ]
];

// Include the outputCSV function from reports.php
function outputCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Check if data is a nested structure (for complex reports)
        if (isset($data['section']) || isset($data['student']) || isset($data['attendance'])) {
            // Handle complex report structures
            if (isset($data['attendance']) && is_array($data['attendance'])) {
                // Use the attendance data as the main data for CSV
                $csvData = $data['attendance'];
            } elseif (isset($data['students']) && is_array($data['students'])) {
                // Use the students data as the main data for CSV
                $csvData = $data['students'];
            } else {
                // Flatten the complex structure
                $csvData = [];
                foreach ($data as $key => $value) {
                    if (is_array($value) && !empty($value)) {
                        $csvData = $value;
                        break;
                    }
                }
            }
        } else {
            $csvData = $data;
        }
        
        if (!empty($csvData) && is_array($csvData)) {
            // Write headers
            fputcsv($output, array_keys($csvData[0]));
            
            // Write data
            foreach ($csvData as $row) {
                // Convert any arrays or objects to strings
                $cleanRow = array();
                foreach ($row as $value) {
                    if (is_array($value)) {
                        $cleanRow[] = implode(', ', $value);
                    } elseif (is_object($value)) {
                        $cleanRow[] = (string) $value;
                    } elseif (is_null($value)) {
                        $cleanRow[] = '';
                    } else {
                        $cleanRow[] = $value;
                    }
                }
                fputcsv($output, $cleanRow);
            }
        }
    }
    
    fclose($output);
}

if (isset($_GET['test'])) {
    $testType = $_GET['test'];
    
    if ($testType === 'simple') {
        outputCSV($simpleData, 'simple_test');
    } elseif ($testType === 'complex') {
        outputCSV($complexData, 'complex_test');
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>CSV Test</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">CSV Download Test</h5>
                    </div>
                    <div class="card-body">
                        <p>Test CSV downloads with different data structures:</p>
                        
                        <div class="d-grid gap-2">
                            <a href="test-csv.php?test=simple" class="btn btn-primary">
                                <i class="fas fa-download me-2"></i>Test Simple CSV
                            </a>
                            <a href="test-csv.php?test=complex" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>Test Complex CSV
                            </a>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <strong>Simple:</strong> Tests basic array structure<br>
                                <strong>Complex:</strong> Tests nested array structure (like reports)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>