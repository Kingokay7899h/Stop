<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'store') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$requestId = $data['requestId'] ?? 0;
$action = $data['action'] ?? '';
$rejection_reason = $data['rejection_reason'] ?? '';

if ($requestId === 0 || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

$status = $action === 'approve' ? 'Pending Committee' : 'Rejected by Stores';
$approved_by = $_SESSION['name'] ?? 'Store Officer';
$sql = "UPDATE disposal_forms SET status = ?, rejection_reason = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $status, $rejection_reason, $requestId);
if ($stmt->execute()) {
    $sql = "UPDATE register r 
            JOIN disposal_forms df ON r.sr_no = df.item_id 
            SET r.disposal_status = ?, r.reason_for_disposal = ? 
            WHERE df.id = ? AND r.disposal_status IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $rejection_reason, $requestId);
    $stmt->execute();
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => "Update failed"]);
}

$stmt->close();
$conn->close();
?>