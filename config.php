<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'project2');
define('DB_PASS', 'J2mWmY7WYTTbCYSb');
define('DB_NAME', 'project2');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            $error_msg = "Database connection error: " . $conn->connect_error;
            $error_msg .= "\n\nPlease check:\n";
            $error_msg .= "1. Database credentials in config.php\n";
            $error_msg .= "2. Database and user exist\n";
            $error_msg .= "3. User has proper privileges\n";
            
            die($error_msg);
        }
        
        $conn->set_charset("utf8");
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

function isLoggedIn() {
    if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
        return false;
    }
    
    // Check if account is suspended
    $user_email = $_SESSION['user_email'];
    $conn = getDBConnection();
    
    // Check if is_suspended column exists
    $suspended_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_suspended'");
    $has_suspended = ($suspended_check && $suspended_check->num_rows > 0);
    
    if ($has_suspended) {
        $stmt = $conn->prepare("SELECT is_suspended FROM users WHERE email = ?");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();
            
            // If account is suspended, logout user
            if (!empty($user['is_suspended'])) {
                $conn->close();
                session_destroy();
                return false;
            }
        } else {
            $stmt->close();
            $conn->close();
            return false;
        }
    }
    
    $conn->close();
    return true;
}

function getCurrentUserEmail() {
    return isset($_SESSION['user_email']) ? $_SESSION['user_email'] : null;
}

function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_email = getCurrentUserEmail();
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT role FROM users WHERE email = ?");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return isset($user['role']) && $user['role'] === 'admin';
    }
    
    $stmt->close();
    $conn->close();
    return false;
}

function requireAdmin() {
    if (!isAdmin()) {
        redirect('dashboard.php');
    }
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function getAssetUrl($path) {
    $filePath = __DIR__ . '/' . $path;
    if (file_exists($filePath)) {
        $version = filemtime($filePath);
        return $path . '?v=' . $version;
    }
    return $path . '?v=' . time();
}

function resizeAndCompressImage($source_path, $destination_path, $max_width = 1920, $max_height = 1920, $quality = 85) {
    if (!function_exists('imagecreatefromjpeg') && !function_exists('imagecreatefrompng')) {
        return copy($source_path, $destination_path);
    }
    $image_info = getimagesize($source_path);
    if (!$image_info) {
        return false;
    }
    
    $mime_type = $image_info['mime'];
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    
    $ratio = min($max_width / $original_width, $max_height / $original_height);
    $new_width = (int)($original_width * $ratio);
    $new_height = (int)($original_height * $ratio);
    
    if ($original_width <= $max_width && $original_height <= $max_height) {
        $ratio = 1.0;
        $new_width = $original_width;
        $new_height = $original_height;
    }
    
    switch ($mime_type) {
        case 'image/jpeg':
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $source_image = imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $source_image = imagecreatefromwebp($source_path);
            } else {
                return copy($source_path, $destination_path);
            }
            break;
        default:
            return copy($source_path, $destination_path);
    }
    
    if (!$source_image) {
        return false;
    }
    
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    if ($mime_type == 'image/png' || $mime_type == 'image/gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);
    
    $result = false;
    switch ($mime_type) {
        case 'image/jpeg':
            $result = imagejpeg($new_image, $destination_path, $quality);
            break;
        case 'image/png':
            $png_quality = 9 - round(($quality / 100) * 9);
            $result = imagepng($new_image, $destination_path, $png_quality);
            break;
        case 'image/gif':
            $result = imagegif($new_image, $destination_path);
            break;
        case 'image/webp':
            if (function_exists('imagewebp')) {
                $result = imagewebp($new_image, $destination_path, $quality);
            } else {
                $result = imagejpeg($new_image, $destination_path, $quality);
            }
            break;
    }
    
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    return $result;
}
?>
