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
$upcoming_date = date('Y-m-d', strtotime('+30 days')); // 2025-07-21

$sql = "SELECT r.sr_no, r.name_of_item, r.item_specification, r.last_maintenance, r.maintainence_due, r.service_provider,
               l.lab_name, c.category_name
        FROM register r
        LEFT JOIN labs l ON r.lab_id = l.lab_id
        LEFT JOIN category c ON r.cid = c.cid
        WHERE r.lab_id = ? 
        AND (r.disposal_status IS NULL OR r.disposal_status NOT LIKE 'Disposed')
        AND r.maintainence_due IS NOT NULL 
        AND r.maintainence_due <= ?
        ORDER BY r.maintainence_due ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $lab_id, $upcoming_date);
$stmt->execute();
$result = $stmt->get_result();

$alerts = [];
while ($row = $result->fetch_assoc()) {
    $due_date = strtotime($row['maintainence_due']);
    $current = strtotime($current_date);
    $status = ($due_date <= $current) ? 'Overdue (' . floor(($current - $due_date) / (60 * 60 * 24)) . ' days)' : 'Upcoming';
    $alerts[] = [
        'sr_no' => $row['sr_no'],
        'name_of_the_item' => $row['name_of_item'],
        'item_specification' => $row['item_specification'],
        'last_maintenance' => $row['last_maintenance'],
        'maintainence_due' => $row['maintainence_due'],
        'service_provider' => $row['service_provider'],
        'status' => $status,
        'lab_name' => $row['lab_name'] ?? 'N/A',
        'category_name' => $row['category_name'] ?? 'N/A'
    ];
}

echo json_encode($alerts);
$stmt->close();
$conn->close();
?>