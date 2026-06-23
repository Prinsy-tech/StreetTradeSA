<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php'); exit;
}

$user_id    = $_SESSION['user_id'];
$listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

if ($listing_id) {
    $stmt = $conn->prepare(
        'INSERT INTO cart (user_id, listing_id, quantity)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE quantity = quantity + 1'
    );
    $stmt->bind_param('ii', $user_id, $listing_id);
    $stmt->execute();
}

$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'listings.php';
header('Location: ' . $redirect);
exit;
?>