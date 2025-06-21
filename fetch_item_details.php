<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['lab_id']) || !isset($_SESSION['role'])) {
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
$sql = "SELECT r.sr_no, r.name_of_item, r.price, r.date, r.item_specification, 
               l.lab_name, c.category_name
        FROM register r
        LEFT JOIN labs l ON r.lab_id = l.lab_id
        LEFT JOIN category c ON r.cid = c.cid
        WHERE r.sr_no IN ($placeholders) AND r.lab_id = ? AND (r.disposal_status IS NULL OR r.disposal_status NOT LIKE 'Disposed')";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Query preparation failed"]);
    exit;
}

$types = str_repeat('i', count($sr_nos)) . 's';
$bind_params = array_merge($sr_nos, [$_SESSION['lab_id']]);
$stmt->bind_param($types, ...$bind_params);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'sr_no' => $row['sr_no'],
        'name_of_item' => $row['name_of_item'],
        'price' => $row['price'],
        'date' => $row['date'],
        'item_specification' => $row['item_specification'],
        'lab_name' => $row['lab_name'] ?? 'N/A',
        'category_name' => $row['category_name'] ?? 'N/A'
    ];
}
echo json_encode($items);
$stmt->close();
$conn->close();
?>