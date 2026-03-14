<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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
    case 'POST':
        // Log emergency override
        $data = json_decode(file_get_contents("php://input"), true);
        
        $reason = $data['reason'] ?? '';
        $dailySpending = $data['daily_spending'] ?? 0;
        $dailyTarget = $data['daily_target'] ?? 0;
        
        if (empty($reason)) {
            die(json_encode(["error" => "Reason is required"]));
        }
        
        $stmt = $conn->prepare("INSERT INTO emergency_overrides (user_id, reason, daily_spending, daily_target) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isdd", $userId, $reason, $dailySpending, $dailyTarget);
        
        if ($stmt->execute()) {
            echo json_encode([
                "message" => "Emergency override logged successfully",
                "id" => $conn->insert_id
            ]);
        } else {
            echo json_encode(["error" => "Error logging emergency override"]);
        }
        break;
        
    case 'GET':
        // Get emergency override history
        $stmt = $conn->prepare("SELECT * FROM emergency_overrides WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $overrides = [];
        while ($row = $result->fetch_assoc()) {
            $overrides[] = $row;
        }
        
        echo json_encode($overrides);
        break;
        
    default:
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

$conn->close();
?>
