<?php
require_once 'config.php';

// Simple test to check parent-student relationships
try {
    echo "<h2>Student-Parent Relationship Test</h2>\n";
    
    // Test the SMS function query
    $test_students = $pdo->query("
        SELECT DISTINCT s.id, s.full_name as student_name, s.username
        FROM users s
        WHERE s.role = 'student' AND s.status = 'active'
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($test_students as $student) {
        echo "<h3>Testing student: {$student['student_name']} (ID: {$student['id']})</h3>\n";
        
        // Test the new query
        $stmt = $pdo->prepare("
            SELECT u.phone, u.full_name as parent_name, s.full_name as student_name
            FROM users u 
            JOIN student_parents sp ON u.id = sp.parent_id 
            JOIN users s ON sp.student_id = s.id
            WHERE sp.student_id = ? AND u.phone IS NOT NULL AND u.phone != ''
            ORDER BY sp.is_primary DESC, sp.id ASC
            LIMIT 1
        ");
        $stmt->execute([$student['id']]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($parent) {
            echo "✅ Found parent: {$parent['parent_name']} with phone: {$parent['phone']}<br>\n";
        } else {
            echo "❌ No parent with phone number found<br>\n";
            
            // Check if student has parents without phones
            $no_phone_stmt = $pdo->prepare("
                SELECT u.full_name as parent_name, sp.relationship
                FROM users u 
                JOIN student_parents sp ON u.id = sp.parent_id 
                WHERE sp.student_id = ?
            ");
            $no_phone_stmt->execute([$student['id']]);
            $parents_no_phone = $no_phone_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($parents_no_phone)) {
                echo "   Parents without phones: ";
                foreach ($parents_no_phone as $p) {
                    echo "{$p['parent_name']} ({$p['relationship']}), ";
                }
                echo "<br>\n";
            } else {
                echo "   No parents linked at all<br>\n";
            }
        }
        echo "<br>\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>