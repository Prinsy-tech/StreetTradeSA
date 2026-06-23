<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id   = $_SESSION['user_id'];
    $receiver_id = (int)$_POST['receiver_id'];
    $body        = trim($_POST['body']);
    $listing_id  = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : null;

    if (empty($body)) {
        header('Location: messages.php?with=' . $receiver_id);
        exit;
    }

    if ($sender_id === $receiver_id) {
        header('Location: messages.php');
        exit;
    }

    $stmt = $conn->prepare(
        'INSERT INTO messages (sender_id, receiver_id, listing_id, body)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('iiis', $sender_id, $receiver_id, $listing_id, $body);
    $stmt->execute();

    header('Location: messages.php?with=' . $receiver_id);
    exit;
}

header('Location: messages.php');
exit;
?>