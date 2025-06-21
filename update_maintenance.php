<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['lab assistant', 'lab faculty incharge'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$sr_no = $data['sr_no'] ?? 0;
$last_maintenance = $data['last_maintenance'] ?? null;
$maintainence_due = $data['maintainence_due'] ?? null;
$service_provider = $data['service_provider'] ?? null;

if ($sr_no === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid item ID"]);
    exit;
}

if ($last_maintenance && $maintainence_due && strtotime($last_maintenance) >= strtotime($maintainence_due)) {
    echo json_encode(["status" => "error", "message" => "Maintenance due date must be after last maintenance"]);
    exit;
}

$sql = "UPDATE register SET last_maintenance = ?, maintainence_due = ?, service_provider = ? WHERE sr_no = ? AND lab_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssis", $last_maintenance, $maintainence_due, $service_provider, $sr_no, $_SESSION['lab_id']);
if ($stmt->execute()) {
    // Optional: Log the update
    $log_sql = "INSERT INTO maintenance_logs (sr_no, last_maintenance, maintainence_due, service_provider, updated_by, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("issss", $sr_no, $last_maintenance, $maintainence_due, $service_provider, $_SESSION['role']);
    $log_stmt->execute();
    $log_stmt->close();
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Update failed"]);
}

$stmt->close();
$conn->close();
?>