<?php
require_once '../config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit(); }

$conn = getDBConnection();

// Get all distinct material names across all packages
$result = $conn->query("SELECT DISTINCT material_name FROM package_materials ORDER BY material_name ASC");

$materials = [];
while ($row = $result->fetch_assoc()) {
    $materials[] = $row['material_name'];
}

$conn->close();
echo json_encode($materials);
?>
