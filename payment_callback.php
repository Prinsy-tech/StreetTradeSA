<?php
// payment_callback.php
require_once 'includes/db.php';

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;

// Capture status passed back by PayGate via POST or GET
$transaction_status = $_POST['TRANSACTION_STATUS'] ?? $_GET['TRANSACTION_STATUS'] ?? 'failed';

if (!$payment_id) {
    header('HTTP/1.0 400 Bad Request');
    echo "Tracking identifier reference mapping missing.";
    exit;
}

// Map PayGate's status to your system's terminology
$new_status = ($transaction_status === 'approved') ? 'completed' : 'failed';

if ($new_status === 'completed') {
    // 1. Fetch who the buyer is from our master row token log
    $pay_query = $conn->prepare("SELECT buyer_id FROM payments WHERE id = ?");
    $pay_query->bind_param('i', $payment_id);
    $pay_query->execute();
    $master_payment = $pay_query->get_result()->fetch_assoc();
    $buyer_id = $master_payment ? $master_payment['buyer_id'] : null;

    if ($buyer_id) {
        // 2. Fetch all cart contents belonging to this buyer
        $cart_stmt = $conn->prepare(
            "SELECT c.listing_id, c.quantity, l.price, l.seller_id 
             FROM cart c JOIN listings l ON c.listing_id = l.id WHERE c.user_id = ?"
        );
        $cart_stmt->bind_param('i', $buyer_id);
        $cart_stmt->execute();
        $items = $cart_stmt->get_result();

        $is_first_item = true;

        // 3. Loop and record table updates matching your split items structure requirement
        while ($item = $items->fetch_assoc()) {
            $amount = $item['price'] * $item['quantity'];

            if ($is_first_item) {
                // Update our existing pending payment record slot with the real item details
                $pay_update = $conn->prepare(
                    "UPDATE payments SET status = 'completed', seller_id = ?, listing_id = ?, amount = ? WHERE id = ?"
                );
                $pay_update->bind_param('iiid', $item['seller_id'], $item['listing_id'], $amount, $payment_id);
                $pay_update->execute();
                
                $current_payment_target = $payment_id;
                $is_first_item = false;
            } else {
                // If there are multiple items in the cart, insert additional approved rows for them
                $pay_insert = $conn->prepare(
                    "INSERT INTO payments (buyer_id, seller_id, listing_id, amount, method, status, created_at) 
                     VALUES (?, ?, ?, ?, 'escrow', 'completed', NOW())"
                );
                $pay_insert->bind_param('iiid', $buyer_id, $item['seller_id'], $item['listing_id'], $amount);
                $pay_insert->execute();
                $current_payment_target = $conn->insert_id;
            }

            // Insert tracking rows directly into transactions table for escrow release tracking page usage
            $trans_stmt = $conn->prepare("INSERT INTO transactions (payment_id, status) VALUES (?, 'pending')");
            $trans_stmt->bind_param('i', $current_payment_target);
            $trans_stmt->execute();
        }

        // 4. Clear out the buyer's cart now that order payment execution cleared successfully
        $clear_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $clear_cart->bind_param('i', $buyer_id);
        $clear_cart->execute();
    }
} else {
    // If payment failed, flag our log row status cleanly
    $stmt = $conn->prepare("UPDATE payments SET status = 'failed' WHERE id = ?");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
}

// Handshake response confirmation for PayGate's engine
header('HTTP/1.0 200 OK');
exit;