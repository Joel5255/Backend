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

$today = date('Y-m-d');

switch ($_GET['action'] ?? '') {
    case 'today':
        // Get today's spending summary
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(amount), 0) as total_spent,
                COUNT(*) as transaction_count
            FROM expenses 
            WHERE user_id = ? AND date = ?
        ");
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $summary = $result->fetch_assoc();
        
        echo json_encode([
            "total_spent" => (float)$summary['total_spent'],
            "transaction_count" => (int)$summary['transaction_count'],
            "date" => $today
        ]);
        break;
        
    case 'cooldown':
        // Check cooldown status
        $stmt = $conn->prepare("SELECT last_transaction_time FROM transaction_cooldowns WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $inCooldown = false;
        $remainingMinutes = 0;
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lastTransaction = new DateTime($row['last_transaction_time']);
            $now = new DateTime();
            $timeDiff = $now->getTimestamp() - $lastTransaction->getTimestamp();
            $cooldownPeriod = 30 * 60; // 30 minutes
            
            if ($timeDiff < $cooldownPeriod) {
                $inCooldown = true;
                $remainingMinutes = ceil(($cooldownPeriod - $timeDiff) / 60);
            }
        }
        
        echo json_encode([
            "in_cooldown" => $inCooldown,
            "remaining_minutes" => $remainingMinutes
        ]);
        break;
        
    default:
        echo json_encode(["error" => "Action not specified"]);
        break;
}

$conn->close();
?>
