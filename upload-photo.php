<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] != 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Check if it's a POST request with the right action
if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['action']) || $_POST['action'] != 'upload_photo') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$file = $_FILES['photo'];
$current_user = getCurrentUser($pdo);

// Validate file
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = 'uploads/student_photos/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

// Create thumbnails directory if it doesn't exist
$thumbnail_dir = $upload_dir . 'thumbnails/';
if (!is_dir($thumbnail_dir)) {
    if (!mkdir($thumbnail_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create thumbnail directory.']);
        exit;
    }
}

// Generate unique filename
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = 'student_' . $current_user['id'] . '_' . time() . '.' . $file_extension;
$filepath = $upload_dir . $filename;
$thumbnail_path = $thumbnail_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
    exit;
}

// Create thumbnail
try {
    $image_info = getimagesize($filepath);
    if ($image_info === false) {
        throw new Exception('Invalid image file.');
    }
    
    $width = $image_info[0];
    $height = $image_info[1];
    $type = $image_info[2];
    
    // Create image resource from file
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($filepath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($filepath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($filepath);
            break;
        default:
            throw new Exception('Unsupported image type.');
    }
    
    if ($source === false) {
        throw new Exception('Failed to create image resource.');
    }
    
    // Calculate thumbnail dimensions (120x140 for ID card)
    $thumb_width = 120;
    $thumb_height = 140;
    
    // Calculate crop dimensions to maintain aspect ratio
    $aspect_ratio = $width / $height;
    $thumb_aspect_ratio = $thumb_width / $thumb_height;
    
    if ($aspect_ratio > $thumb_aspect_ratio) {
        // Image is wider than thumbnail ratio - crop width
        $crop_width = $height * $thumb_aspect_ratio;
        $crop_height = $height;
        $crop_x = ($width - $crop_width) / 2;
        $crop_y = 0;
    } else {
        // Image is taller than thumbnail ratio - crop height
        $crop_width = $width;
        $crop_height = $width / $thumb_aspect_ratio;
        $crop_x = 0;
        $crop_y = ($height - $crop_height) / 2;
    }
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);
    
    // Preserve transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefill($thumbnail, 0, 0, $transparent);
    }
    
    // Resize and crop
    imagecopyresampled(
        $thumbnail, $source,
        0, 0, $crop_x, $crop_y,
        $thumb_width, $thumb_height, $crop_width, $crop_height
    );
    
    // Save thumbnail
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumbnail, $thumbnail_path, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumbnail, $thumbnail_path, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumbnail, $thumbnail_path);
            break;
    }
    
    // Clean up memory
    imagedestroy($source);
    imagedestroy($thumbnail);
    
} catch (Exception $e) {
    // If thumbnail creation fails, we'll still update the database with the original image
    $thumbnail_path = $filepath;
}

// Update user profile in database
try {
    $stmt = $pdo->prepare("UPDATE users SET profile_image_path = ? WHERE id = ?");
    $stmt->execute([$thumbnail_path, $current_user['id']]);
    
    // Also update the legacy profile_image field for backward compatibility
    $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
    $stmt->execute([$filename, $current_user['id']]);
    
} catch (PDOException $e) {
    // If database update fails, remove uploaded files
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    if (file_exists($thumbnail_path) && $thumbnail_path !== $filepath) {
        unlink($thumbnail_path);
    }
    
    echo json_encode(['success' => false, 'message' => 'Failed to update database.']);
    exit;
}

// Remove old photo files if they exist
if (!empty($current_user['profile_image_path']) && file_exists($current_user['profile_image_path'])) {
    $old_path = $current_user['profile_image_path'];
    if ($old_path !== $thumbnail_path) {
        unlink($old_path);
        
        // Also try to remove the original if it's different
        $old_original = str_replace('/thumbnails/', '/', $old_path);
        if (file_exists($old_original) && $old_original !== $old_path) {
            unlink($old_original);
        }
    }
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Photo uploaded successfully!',
    'photo_path' => $thumbnail_path,
    'original_path' => $filepath
]);
?>
