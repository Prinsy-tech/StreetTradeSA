<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id     = (int)$_POST['payment_id'];
    $transaction_id = (int)$_POST['transaction_id'];
    $user_id        = $_SESSION['user_id'];
    $role           = $_SESSION['role'];

    // Update transaction status to released
    $t = $conn->prepare(
        'UPDATE transactions SET status = "released"
         WHERE id = ? AND payment_id = ?'
    );
    $t->bind_param('ii', $transaction_id, $payment_id);
    $t->execute();

    // Update payment status to completed
    $p = $conn->prepare(
        'UPDATE payments SET status = "completed"
         WHERE id = ?'
    );
    $p->bind_param('i', $payment_id);
    $p->execute();

    // Redirect based on role
    if ($role === 'seller') {
        header('Location: seller_orders.php?confirmed=1');
    } else {
        header('Location: orders.php?confirmed=1');
    }
    exit;
}

header('Location: listings.php');
exit;
?>