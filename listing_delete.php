<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php'); exit;
}

$user_id    = $_SESSION['user_id'];
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$listing_id) {
    header('Location: dashboard.php'); exit;
}

// Make sure listing belongs to this seller
$stmt = $conn->prepare('SELECT id, image FROM listings WHERE id=? AND seller_id=?');
$stmt->bind_param('ii', $listing_id, $user_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing) {
    header('Location: dashboard.php'); exit;
}

// Delete image file if exists
if ($listing['image']) {
    $image_path = __DIR__ . '/assets/uploads/' . $listing['image'];
    if (file_exists($image_path)) {
        unlink($image_path);
    }
}

// Delete listing
$del = $conn->prepare('DELETE FROM listings WHERE id=? AND seller_id=?');
$del->bind_param('ii', $listing_id, $user_id);
$del->execute();

header('Location: dashboard.php?deleted=1');
exit;
?>