<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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

// Get user ID from token (simple authentication)
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
        // Get expenses (with optional date filter)
        $date = $_GET['date'] ?? '';
        
        if (!empty($date)) {
            $stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? AND date = ? ORDER BY created_at DESC");
            $stmt->bind_param("is", $userId, $date);
        } else {
            $stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $userId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $expenses = [];
        
        while ($row = $result->fetch_assoc()) {
            $expenses[] = $row;
        }
        
        echo json_encode($expenses);
        break;
        
    case 'POST':
        // Add new expense
        $data = json_decode(file_get_contents("php://input"), true);
        
        $amount = $data['amount'] ?? 0;
        $category = $data['category'] ?? '';
        $date = $data['date'] ?? date('Y-m-d');
        $description = $data['description'] ?? null;
        
        if (empty($amount) || empty($category)) {
            die(json_encode(["error" => "Amount and category are required"]));
        }
        
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, date, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idsss", $userId, $amount, $category, $date, $description);
        
        if ($stmt->execute()) {
            // Update transaction cooldown
            $cooldownStmt = $conn->prepare("INSERT OR REPLACE INTO transaction_cooldowns (user_id, last_transaction_time) VALUES (?, ?)");
            $cooldownStmt->bind_param("is", $userId, date('Y-m-d H:i:s'));
            $cooldownStmt->execute();
            
            echo json_encode([
                "message" => "Expense added successfully",
                "expense" => [
                    "id" => $conn->insert_id,
                    "user_id" => $userId,
                    "amount" => $amount,
                    "category" => $category,
                    "date" => $date,
                    "description" => $description,
                    "created_at" => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            echo json_encode(["error" => "Error adding expense"]);
        }
        break;
        
    case 'DELETE':
        // Delete expense
        $expenseId = $_GET['id'] ?? 0;
        
        if (empty($expenseId)) {
            die(json_encode(["error" => "Expense ID required"]));
        }
        
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $expenseId, $userId);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["message" => "Expense deleted successfully"]);
            } else {
                echo json_encode(["error" => "Expense not found"]);
            }
        } else {
            echo json_encode(["error" => "Error deleting expense"]);
        }
        break;
        
    default:
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

$conn->close();
?>
