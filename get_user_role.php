<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['lab_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$lab_id = $_SESSION['lab_id'];
$sql = "SELECT role FROM users WHERE lab_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $lab_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(["role" => $row['role']]);
} else {
    echo json_encode(["role" => "guest"]);
}

$stmt->close();
$conn->close();
?>