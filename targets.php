<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database connection
$host = "sql201.infinityfree.com";
$user = "if0_41338430"; // InfinityFree MySQL user
$password = "uwlpRCnXtwZkc"; // InfinityFree MySQL password
$database = "if0_41338430_financial_literacy";

$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

// Get user ID from token
function getUserIdFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }
    
    $token = $matches[1];
    $decoded = base64_decode($token);
    
    if (!$decoded) {
        return null;
    }
    
    $parts = explode(':', $decoded);
    return isset($parts[0]) ? (int)$parts[0] : null;
}

$userId = getUserIdFromToken();

if (!$userId) {
    die(json_encode(["error" => "Access token required"]));
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get daily target
        $stmt = $conn->prepare("SELECT * FROM daily_targets WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            die(json_encode(["error" => "Daily target not found"]));
        }
        
        $target = $result->fetch_assoc();
        
        echo json_encode([
            "target_amount" => (float)$target['target_amount'],
            "spending_lock_enabled" => (bool)$target['spending_lock_enabled'],
            "cooldown_enabled" => (bool)$target['cooldown_enabled'],
            "emergency_override_enabled" => (bool)$target['emergency_override_enabled']
        ]);
        break;
        
    case 'POST':
    case 'PUT':
        // Update daily target
        $data = json_decode(file_get_contents("php://input"), true);
        
        $targetAmount = $data['target_amount'] ?? 50.00;
        $spendingLock = isset($data['spending_lock_enabled']) ? ($data['spending_lock_enabled'] ? 1 : 0) : 0;
        $cooldown = isset($data['cooldown_enabled']) ? ($data['cooldown_enabled'] ? 1 : 0) : 0;
        $emergencyOverride = isset($data['emergency_override_enabled']) ? ($data['emergency_override_enabled'] ? 1 : 0) : 0;
        
        $stmt = $conn->prepare("UPDATE daily_targets SET target_amount = ?, spending_lock_enabled = ?, cooldown_enabled = ?, emergency_override_enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
        $stmt->bind_param("diiii", $targetAmount, $spendingLock, $cooldown, $emergencyOverride, $userId);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["message" => "Daily target updated successfully"]);
            } else {
                echo json_encode(["error" => "Daily target not found"]);
            }
        } else {
            echo json_encode(["error" => "Error updating daily target"]);
        }
        break;
        
    default:
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

$conn->close();
?>
