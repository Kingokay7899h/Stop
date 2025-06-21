<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['role'])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$id = $_GET['id'] ?? 0;
$sql = "SELECT df.*, r.name_of_item, r.item_specification, r.date AS purchase_date, 
               r.price AS book_value, l.lab_name, c.category_name
        FROM disposal_forms df
        JOIN register r ON df.item_id = r.sr_no
        LEFT JOIN labs l ON r.lab_id = l.lab_id
        LEFT JOIN category c ON r.cid = c.cid
        WHERE df.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$data = ['items' => []];
while ($row = $result->fetch_assoc()) {
    $data['items'][] = [
        'item_id' => $row['item_id'],
        'sr_no' => $row['id'],
        'description' => $row['name_of_item'] . ' (' . $row['item_specification'] . ')',
        'purchase_date' => $row['purchase_date'],
        'book_value' => $row['book_value'],
        'lab_name' => $row['lab_name'] ?? 'N/A',
        'category_name' => $row['category_name'] ?? 'N/A',
        'condemnation_reason' => $row['condemnation_reason'] ?? 'N/A',
        'remarks' => $row['remarks'] ?? 'N/A'
    ];
    $data['status'] = $row['status'];
    $data['prepared_by'] = ['name' => $row['submitted_by'], 'designation' => ''];
    $data['reviewed_by'] = ['name' => $row['approved_by'] ?? '', 'designation' => ''];
}

if (empty($data['items'])) {
    echo json_encode(['error' => 'Form not found']);
} else {
    echo json_encode($data);
}
$stmt->close();
$conn->close();
?>