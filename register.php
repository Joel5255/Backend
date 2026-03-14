<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
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

// Test if we can query the database
$testQuery = $conn->query("SELECT 1");
if (!$testQuery) {
    die(json_encode(["error" => "Database query test failed: " . $conn->error]));
}

// Check if users table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if ($tableCheck->num_rows === 0) {
    die(json_encode(["error" => "Users table does not exist in database"]));
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    die(json_encode(["error" => "Invalid JSON data"]));
}

$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

// Validation
if (empty($name) || empty($email) || empty($password)) {
    die(json_encode(["error" => "All fields are required"]));
}

// Check if user already exists
$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    die(json_encode(["error" => "User already exists"]));
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Create user
$stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $hashedPassword);

if ($stmt->execute()) {
    $userId = $conn->insert_id;
    
    // Create default daily target
    $defaultTarget = 50.00;
    $spendingLock = 0;
    $cooldown = 0;
    $emergencyOverride = 0;
    
    $targetStmt = $conn->prepare("INSERT INTO daily_targets (user_id, target_amount, spending_lock_enabled, cooldown_enabled, emergency_override_enabled) VALUES (?, ?, ?, ?, ?)");
    $targetStmt->bind_param("idiii", $userId, $defaultTarget, $spendingLock, $cooldown, $emergencyOverride);
    
    if ($targetStmt->execute()) {
        // Generate simple token (in production, use JWT)
        $token = base64_encode($userId . ':' . $email . ':' . time());
        
        echo json_encode([
            "message" => "User created successfully",
            "token" => $token,
            "user" => [
                "id" => $userId,
                "name" => $name,
                "email" => $email
            ]
        ]);
    } else {
        echo json_encode([
            "error" => "Error creating daily target: " . $targetStmt->error,
            "user_created" => true,
            "user_id" => $userId
        ]);
    }
} else {
    echo json_encode([
        "error" => "Error creating user: " . $stmt->error,
        "sql_error" => $conn->error
    ]);
}

$stmt->close();
$checkStmt->close();
$conn->close();
?>
