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

$data = json_decode(file_get_contents("php://input"), true);
$sr_nos = $data['sr_nos'] ?? [];

if (empty($sr_nos)) {
    echo json_encode(["status" => "error", "message" => "No items provided"]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($sr_nos), '?'));
$sql = "SELECT r.sr_no, r.name_of_item, r.disposal_status, r.reason_for_disposal, df.status AS disposal_form_status
        FROM register r
        LEFT JOIN disposal_forms df ON r.sr_no = df.item_id AND df.status NOT IN ('Disposed', 'Rejected%')
        WHERE r.sr_no IN ($placeholders) AND r.lab_id = ? 
        AND (r.disposal_status IS NOT NULL OR df.id IS NOT NULL)";
$stmt = $conn->prepare($sql);
$types = str_repeat('i', count($sr_nos)) . 's';
$bind_params = array_merge($sr_nos, [$_SESSION['lab_id']]);
$stmt->bind_param($types, ...$bind_params);
$stmt->execute();
$result = $stmt->get_result();

$invalid_items = [];
while ($row = $result->fetch_assoc()) {
    if ($row['disposal_status'] || $row['disposal_form_status']) {
        $status = $row['disposal_status'] ?: $row['disposal_form_status'];
        $reason = $row['reason_for_disposal'] ?: 'Pending disposal request';
        $invalid_items[] = [
            'sr_no' => $row['sr_no'],
            'name' => $row['name_of_item'],
            'status' => $status,
            'reason' => $reason
        ];
    }
}

if (!empty($invalid_items)) {
    echo json_encode(["status" => "error", "invalid_items" => $invalid_items]);
} else {
    echo json_encode(["status" => "success"]);
}

$stmt->close();
$conn->close();
?>