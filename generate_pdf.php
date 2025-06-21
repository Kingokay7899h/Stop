<?php
require('fpdf/fpdf.php');
session_start();
$conn = new mysqli("localhost", "root", "", "asset_management");
if ($conn->connect_error) {
    die("Database connection failed");
}

$id = $_GET['id'] ?? 0;
$sql = "SELECT df.*, r.name_of_item, r.item_specification, r.date AS purchase_date, r.price AS book_value, 
               l.lab_name, c.category_name
        FROM disposal_forms df
        JOIN register r ON df.item_id = r.sr_no
        LEFT JOIN labs l ON r.lab_id = l.lab_id
        LEFT JOIN category c ON r.cid = c.cid
        WHERE df.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("Form not found");
}

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Condemnation Form', 0, 1, 'C');
        $this->Ln(5);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

$pdf->Cell(0, 10, 'Lab: ' . ($data['lab_name'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Item: ' . $data['name_of_item'] . ' (' . ($data['item_specification'] ?? 'N/A') . ')', 0, 1);
$pdf->Cell(0, 10, 'Weight: ' . ($data['weight'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Book Value: ' . ($data['book_value'] ?? $data['price'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Purchase Date: ' . ($data['purchase_date'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Unserviceable Date: ' . ($data['unserviceable_date'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Period of Use: ' . ($data['period_of_use'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Current Condition: ' . ($data['current_condition'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Repair Efforts: ' . ($data['repair_efforts'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Location: ' . ($data['location'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Reason for Condemnation: ' . ($data['condemnation_reason'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Remarks: ' . ($data['remarks'] ?? 'N/A'), 0, 1);
$pdf->Cell(0, 10, 'Status: ' . $data['status'], 0, 1);
$pdf->Cell(0, 10, 'Submitted By: ' . ($data['submitted_by'] ?? 'N/A'), 0, 1);
if ($data['status'] === 'Rejected by Stores' || $data['status'] === 'Rejected by Committee') {
    $pdf->Cell(0, 10, 'Rejection Reason: ' . ($data['rejection_reason'] ?? 'N/A'), 0, 1);
}

$pdf->Output('D', 'Condemnation_Form_' . $id . '.pdf');
$stmt->close();
$conn->close();
?>