<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['lab_id']) || !isset($_SESSION['role'])) {
    echo json_encode([]);
    exit;
}

$lab_id = $_SESSION['lab_id'];
$current_date = date('Y-m-d'); // 2025-06-21
$sql = "SELECT r.sr_no, r.name_of_item, r.item_specification, r.date, r.price, 
               r.last_maintenance, r.maintainence_due, r.service_provider, r.disposal_status,
               l.lab_name, c.category_name
        FROM register r
        LEFT JOIN labs l ON r.lab_id = l.lab_id
        LEFT JOIN category c ON r.cid = c.cid
        WHERE r.lab_id = ? AND (r.disposal_status IS NULL OR r.disposal_status NOT LIKE 'Disposed')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $lab_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'sr_no' => $row['sr_no'],
        'name_of_the_item' => $row['name_of_item'],
        'item_specification' => $row['item_specification'],
        'procurement_date' => $row['date'],
        'cost' => $row['price'],
        'last_maintenance' => $row['last_maintenance'],
        'maintainence_due' => $row['maintainence_due'],
        'service_provider' => $row['service_provider'],
        'disposal_status' => $row['disposal_status'],
        'lab_name' => $row['lab_name'] ?? 'N/A',
        'category_name' => $row['category_name'] ?? 'N/A'
    ];
}

// Filter for overdue maintenance
$data = array_filter($data, function($item) use ($current_date) {
    return !$item['maintainence_due'] || strtotime($item['maintainence_due']) <= strtotime($current_date);
});

echo json_encode(array_values($data)); // Reindex array after filtering
$stmt->close();
$conn->close();
?>