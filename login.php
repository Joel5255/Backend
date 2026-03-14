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

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    die(json_encode(["error" => "Invalid JSON data"]));
}

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

// Validation
if (empty($email) || empty($password)) {
    die(json_encode(["error" => "Email and password are required"]));
}

// Get user from database
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die(json_encode(["error" => "Invalid credentials"]));
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password'])) {
    die(json_encode(["error" => "Invalid credentials"]));
}

// Generate simple token (in production, use JWT)
$token = base64_encode($user['id'] . ':' . $user['email'] . ':' . time());

echo json_encode([
    "message" => "Login successful",
    "token" => $token,
    "user" => [
        "id" => $user['id'],
        "name" => $user['name'],
        "email" => $user['email']
    ]
]);

$stmt->close();
$conn->close();
?>
