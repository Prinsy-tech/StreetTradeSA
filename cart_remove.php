<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php'); exit;
}

$user_id    = $_SESSION['user_id'];
$listing_id = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;

if ($listing_id) {
    $stmt = $conn->prepare('DELETE FROM cart WHERE user_id=? AND listing_id=?');
    $stmt->bind_param('ii', $user_id, $listing_id);
    $stmt->execute();
}

header('Location: cart.php');
exit;
?>