<?php
require_once 'config.php';

// Check if user is logged in and is admin
requireRole(['admin']);

echo "<h2>Fixing Primary Parent Relationships</h2>\n";

try {
    // Get all students who don't have a primary parent set
    $stmt = $pdo->query("
        SELECT DISTINCT sp.student_id, s.full_name as student_name
        FROM student_parents sp
        JOIN users s ON sp.student_id = s.id
        WHERE sp.student_id NOT IN (
            SELECT student_id FROM student_parents WHERE is_primary = 1
        )
        ORDER BY s.full_name
    ");
    
    $students_without_primary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students_without_primary)) {
        echo "<p style='color: green;'>✅ All students already have primary parents set!</p>\n";
    } else {
        echo "<p>Found " . count($students_without_primary) . " students without primary parents:</p>\n";
        
        foreach ($students_without_primary as $student) {
            // Get the first parent for this student
            $parent_stmt = $pdo->prepare("
                SELECT sp.id, sp.parent_id, u.full_name as parent_name, u.phone
                FROM student_parents sp
                JOIN users u ON sp.parent_id = u.id
                WHERE sp.student_id = ?
                ORDER BY sp.id ASC
                LIMIT 1
            ");
            $parent_stmt->execute([$student['student_id']]);
            $first_parent = $parent_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($first_parent) {
                // Set this parent as primary
                $update_stmt = $pdo->prepare("UPDATE student_parents SET is_primary = 1 WHERE id = ?");
                $update_stmt->execute([$first_parent['id']]);
                
                $phone_status = $first_parent['phone'] ? "📱 " . $first_parent['phone'] : "❌ No phone";
                echo "<p>✅ Set <strong>{$first_parent['parent_name']}</strong> as primary parent for <strong>{$student['student_name']}</strong> ({$phone_status})</p>\n";
            } else {
                echo "<p>❌ No parents found for <strong>{$student['student_name']}</strong></p>\n";
            }
        }
        
        echo "<p style='color: green; margin-top: 20px;'><strong>✅ Primary parent relationships have been fixed!</strong></p>\n";
    }
    
    // Show summary of all parent-student relationships
    echo "<hr><h3>Current Parent-Student Relationships Summary:</h3>\n";
    
    $summary_stmt = $pdo->query("
        SELECT s.full_name as student_name, 
               p.full_name as parent_name, 
               p.phone,
               sp.relationship,
               sp.is_primary
        FROM student_parents sp
        JOIN users s ON sp.student_id = s.id
        JOIN users p ON sp.parent_id = p.id
        ORDER BY s.full_name, sp.is_primary DESC
    ");
    
    $relationships = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($relationships)) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background: #f0f0f0;'><th>Student</th><th>Parent</th><th>Phone</th><th>Relationship</th><th>Primary</th></tr>\n";
        
        foreach ($relationships as $rel) {
            $primary_badge = $rel['is_primary'] ? "✅ Primary" : "Secondary";
            $phone_display = $rel['phone'] ? $rel['phone'] : "❌ No phone";
            $row_style = $rel['is_primary'] ? "background: #e8f5e8;" : "";
            
            echo "<tr style='{$row_style}'>";
            echo "<td>{$rel['student_name']}</td>";
            echo "<td>{$rel['parent_name']}</td>";
            echo "<td>{$phone_display}</td>";
            echo "<td>{$rel['relationship']}</td>";
            echo "<td>{$primary_badge}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}

echo "<p style='margin-top: 20px;'><a href='parents.php'>← Back to Parents Management</a></p>\n";
?>