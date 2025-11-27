<?php
// Password Update Tool
// Use this to set proper passwords for users after migration

require_once 'config.php';

// Function to update user password
function updateUserPassword($pdo, $username, $new_password) {
    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
        $result = $stmt->execute([$hashed_password, $username]);
        
        if ($result) {
            echo "✅ Password updated for user: $username\n";
            return true;
        } else {
            echo "❌ Failed to update password for user: $username\n";
            return false;
        }
    } catch (PDOException $e) {
        echo "❌ Error updating password for $username: " . $e->getMessage() . "\n";
        return false;
    }
}

// Check command line arguments
if ($argc < 3) {
    echo "Usage: php update_passwords.php <username> <new_password>\n";
    echo "Example: php update_passwords.php admin mynewpassword123\n";
    exit(1);
}

$username = $argv[1];
$password = $argv[2];

// Validate password
if (strlen($password) < 6) {
    echo "❌ Password must be at least 6 characters long!\n";
    exit(1);
}

// Check if user exists
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if (!$stmt->fetch()) {
        echo "❌ User '$username' not found!\n";
        exit(1);
    }
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// Update password
echo "Updating password for user '$username'...\n";
if (updateUserPassword($pdo, $username, $password)) {
    echo "🎉 Password successfully updated!\n";
    echo "\nYou can now login with:\n";
    echo "Username: $username\n";
    echo "Password: [the password you just set]\n";
} else {
    echo "💥 Failed to update password!\n";
    exit(1);
}

echo "\n📋 Current users in system:\n";
try {
    $stmt = $pdo->query("SELECT username, role FROM users ORDER BY role, username");
    while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$user['username']} ({$user['role']})\n";
    }
} catch (PDOException $e) {
    echo "Error listing users: " . $e->getMessage() . "\n";
}
?>