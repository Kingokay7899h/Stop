<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['lab assistant', 'lab faculty incharge', 'HOD'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$requestId = $data['requestId'] ?? '';
$items = $data['items'] ?? [];
$status = $data['status'] ?? 'Pending Stores';
$submitted_by = $_SESSION['name'] ?? 'Unknown';

if (empty($items)) {
    echo json_encode(["status" => "error", "message" => "No items provided"]);
    exit;
}

$conn->begin_transaction();
try {
    foreach ($items as $item) {
        $item_id = $item['item_id'] ?? 0;
        if ($item_id === 0) continue;

        // Check for existing disposal request
        $check_sql = "SELECT id FROM disposal_forms WHERE item_id = ? AND status NOT IN ('Disposed', 'Rejected%')";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $item_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            throw new Exception("Item $item_id already has a pending disposal request");
        }
        $check_stmt->close();

        $condemnation_reason = $item['condemnation_reason'] ?? 'N/A';
        $remarks = $item['remarks'] ?? 'N/A';

        $sql = "INSERT INTO disposal_forms (item_id, condemnation_reason, remarks, status, submitted_by, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $item_id, $condemnation_reason, $remarks, $status, $submitted_by);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert disposal form for item $item_id");
        }

        $sql = "UPDATE register SET disposal_status = ?, reason_for_disposal = ? WHERE sr_no = ? AND disposal_status IS NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $condemnation_reason, $item_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update register for item $item_id");
        }
    }

    $conn->commit();
    echo json_encode(["status" => "success", "requestId" => $requestId]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>