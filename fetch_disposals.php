<?php
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_SESSION['role']) || !isset($_SESSION['lab_id'])) {
    echo json_encode([]);
    exit;
}

$role = $_SESSION['role'];
$lab_id = $_SESSION['lab_id'];
$type = $_GET['type'] ?? 'pending';
$disposals = [];

switch ($type) {
    case 'rejected':
        $sql = "SELECT df.id, df.item_id, r.name_of_item, r.item_specification, df.status, 
                       df.condemnation_reason, df.created_at
                FROM disposal_forms df
                JOIN register r ON df.item_id = r.sr_no
                WHERE df.status LIKE 'Rejected%'";
        if (in_array($role, ['lab assistant', 'lab faculty incharge', 'HOD'])) {
            $sql .= " AND r.lab_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $lab_id);
        } elseif ($role === 'store') {
            $sql .= " AND df.status LIKE 'Rejected by Stores'";
            $stmt = $conn->prepare($sql);
        } elseif ($role === 'principal') {
            $sql .= " AND df.status LIKE 'Rejected by Committee'";
            $stmt = $conn->prepare($sql);
        }
        break;
    case 'past':
        $sql = "SELECT pd.id, pd.item_id, r.name_of_item, r.item_specification, pd.status, 
                       pd.reason AS condemnation_reason, pd.disposal_date AS created_at
                FROM past_disposals pd
                JOIN register r ON pd.item_id = r.sr_no";
        if (in_array($role, ['lab assistant', 'lab faculty incharge', 'HOD'])) {
            $sql .= " WHERE r.lab_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $lab_id);
        } else {
            $stmt = $conn->prepare($sql);
        }
        break;
    default: // pending
        $sql = "SELECT df.id, df.item_id, r.name_of_item, r.item_specification, df.status, 
                       df.condemnation_reason, df.created_at
                FROM disposal_forms df
                JOIN register r ON df.item_id = r.sr_no
                WHERE df.status NOT LIKE 'Rejected%' AND df.status != 'Disposed'";
        if (in_array($role, ['lab assistant', 'lab faculty incharge', 'HOD'])) {
            $sql .= " AND r.lab_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $lab_id);
        } elseif ($role === 'store') {
            $sql .= " AND df.status IN ('Pending Stores', 'Pending Committee', 'Approved by Committee')";
            $stmt = $conn->prepare($sql);
        } elseif ($role === 'principal') {
            $sql .= " AND df.status IN ('Pending Committee', 'Approved by Committee')";
            $stmt = $conn->prepare($sql);
        }
        break;
}

if (!isset($stmt)) {
    echo json_encode([]);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $disposals[] = [
        'id' => $row['id'],
        'item_id' => $row['item_id'],
        'name_of_item' => $row['name_of_item'],
        'item_specification' => $row['item_specification'],
        'status' => $row['status'],
        'condemnation_reason' => $row['condemnation_reason'],
        'created_at' => $row['created_at']
    ];
}

echo json_encode($disposals);
$stmt->close();
$conn->close();
?>