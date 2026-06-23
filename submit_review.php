<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// 1. Get data from form and session
$reviewer_id = $_SESSION['user_id']; // Matches 'reviewer_id' in your table
$transaction_id = (int)$_POST['transaction_id'];
$seller_id = (int)$_POST['seller_id'];
$rating = (int)$_POST['rating'];
$comment = trim($_POST['comment']);

// 2. Validate rating
if ($rating < 1 || $rating > 5) {
    header('Location: orders.php?error=invalid_rating');
    exit;
}

// 3. Prevent duplicate reviews for the same transaction
$check = $conn->prepare("SELECT id FROM reviews WHERE transaction_id = ?");
$check->bind_param("i", $transaction_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    header('Location: orders.php?error=already_reviewed');
    exit;
}

// 4. Insert matching your exact columns from image_d164ab.png
$stmt = $conn->prepare("INSERT INTO reviews (transaction_id, reviewer_id, seller_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiis", $transaction_id, $reviewer_id, $seller_id, $rating, $comment);

if ($stmt->execute()) {
    header('Location: orders.php?success=review_added');
} else {
    header('Location: orders.php?error=db_failed');
}
exit;