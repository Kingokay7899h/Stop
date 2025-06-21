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

if ($requestId === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

$status = 'Disposed';
$disposal_date = date('Y-m-d'); // 2025-06-21
$conn->begin_transaction();
try {
    $sql = "SELECT df.item_id, df.condemnation_reason, r.name_of_item, r.lab_id 
            FROM disposal_forms df 
            JOIN register r ON df.item_id = r.sr_no 
            WHERE df.id = ? AND r.sr_no IS NOT NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();

    if (!$item) {
        throw new Exception("Item not found or already disposed");
    }

    $sql = "UPDATE disposal_forms SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $requestId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update disposal form");
    }

    $sql = "UPDATE register SET disposal_status = ?, reason_for_disposal = ? WHERE sr_no = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $item['condemnation_reason'], $item['item_id']);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update register");
    }

    $sql = "INSERT INTO past_disposals (item_id, item_name, disposal_date, reason, lab_id) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $item['item_id'], $item['name_of_item'], $disposal_date, $item['condemnation_reason'], $item['lab_id']);
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert into past_disposals");
    }

    $conn->commit();
    echo json_encode(["status" => "success"]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>s