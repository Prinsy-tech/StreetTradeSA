<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];

// Get cart items
$stmt = $conn->prepare(
    'SELECT c.id AS cart_id, c.quantity, c.listing_id,
            l.title, l.price, l.image, l.listing_type, l.location,
            u.username AS seller_name, u.id AS seller_id
     FROM cart c
     JOIN listings l ON c.listing_id = l.id
     JOIN users u ON l.seller_id = u.id
     WHERE c.user_id = ?
     ORDER BY c.added_at DESC'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$items = $stmt->get_result();

$total     = 0;
$item_list = [];
while ($row = $items->fetch_assoc()) {
    $row['subtotal'] = $row['price'] * $row['quantity'];
    $total += $row['subtotal'];
    $item_list[] = $row;
}

// Redirect if cart is empty
if (empty($item_list)) {
    header('Location: cart.php'); exit;
}

// Get buyer details
$buyer = $conn->prepare('SELECT * FROM users WHERE id = ?');
$buyer->bind_param('i', $user_id);
$buyer->execute();
$buyer_info = $buyer->get_result()->fetch_assoc();

$success = '';
$error   = '';

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name      = trim($_POST['full_name']);
    $phone          = trim($_POST['phone']);
    $address        = trim($_POST['address']);
    $city           = trim($_POST['city']);
    $payment_method = $_POST['payment_method'];

    if (empty($full_name) || empty($phone) || empty($address) || empty($city) || empty($payment_method)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Save each cart item as a payment/transaction
        foreach ($item_list as $item) {
            $amount = $item['subtotal'];

            // Insert into payments
            $pay = $conn->prepare(
                'INSERT INTO payments (buyer_id, seller_id, listing_id, amount, method, status)
                 VALUES (?, ?, ?, ?, ?, "pending")'
            );
            $pay->bind_param('iiids', $user_id, $item['seller_id'], $item['listing_id'], $amount, $payment_method);
            $pay->execute();
            $payment_id = $conn->insert_id;

            // Insert into transactions
            $trans = $conn->prepare(
                'INSERT INTO transactions (payment_id, status)
                 VALUES (?, "pending")'
            );
            $trans->bind_param('i', $payment_id);
            $trans->execute();
        }

        // Clear the cart
        $clear = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
        $clear->bind_param('i', $user_id);
        $clear->execute();

        $success = 'order_placed';
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php if ($success === 'order_placed'): ?>

    <!-- SUCCESS MESSAGE -->
    <div style="min-height:70vh;display:flex;align-items:center;justify-content:center;padding:40px 20px">
        <div style="text-align:center;background:white;border:1px solid #ddd;border-radius:16px;
                    padding:60px 40px;max-width:500px;width:100%">
            <div style="font-size:80px;margin-bottom:20px">🎉</div>
            <h2 style="color:#003366;font-size:26px;margin-bottom:12px">Order Placed Successfully!</h2>
            <p style="color:#666;font-size:15px;line-height:1.7;margin-bottom:10px">
                Thank you for your order. Your order has been received and is now being processed.
            </p>
            <p style="color:#666;font-size:15px;line-height:1.7;margin-bottom:30px">
                The seller will contact you shortly to arrange delivery or collection.
            </p>
            <div style="background:#f0f8f0;border:1px solid #c3e6c3;border-radius:8px;
                        padding:16px;margin-bottom:30px">
                <p style="color:#1a7a4a;font-size:14px;font-weight:bold;margin:0">
                    ✅ Your order has been saved and the seller has been notified.
                </p>
            </div>
            <a href="listings.php"
               style="display:inline-block;padding:14px 32px;background:#003366;
                      color:white;border-radius:8px;text-decoration:none;
                      font-weight:bold;font-size:15px;margin-right:10px"
               onmouseover="this.style.background='#002244'"
               onmouseout="this.style.background='#003366'">
                Continue Shopping
            </a>
            <a href="messages.php"
               style="display:inline-block;padding:14px 32px;background:#f5f5f5;
                      color:#003366;border-radius:8px;text-decoration:none;
                      font-weight:bold;font-size:15px;border:1px solid #ddd"
               onmouseover="this.style.background='#e8e8e8'"
               onmouseout="this.style.background='#f5f5f5'">
                Message Seller
            </a>
        </div>
    </div>

<?php else: ?>

<div style="max-width:1000px;margin:30px auto;padding:0 20px">

    <h1 style="color:#003366;margin-bottom:6px;font-size:28px">Checkout</h1>
    <p style="color:#666;margin-bottom:24px;font-size:15px">Complete your order details below</p>

    <?php if ($error): ?>
        <div style="background:#fdecea;color:#cc0000;border:1px solid #f5c6c2;
                    border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:14px">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">

        <!-- LEFT: DELIVERY + PAYMENT FORM -->
        <div>

            <!-- DELIVERY DETAILS -->
            <div style="background:white;border:1px solid #ddd;border-radius:12px;
                        padding:24px;margin-bottom:20px">
                <h3 style="color:#003366;margin-bottom:20px;font-size:18px">📦 Delivery Details</h3>

                <form method="POST" id="checkoutForm">

                    <label style="display:block;font-size:14px;font-weight:bold;
                                  color:#003366;margin-bottom:4px">Full Name *</label>
                    <input type="text" name="full_name"
                           value="<?= htmlspecialchars($buyer_info['full_name'] ?? '') ?>"
                           placeholder="Your full name" required
                           style="width:100%;padding:10px 14px;border:1px solid #ddd;
                                  border-radius:8px;font-size:14px;margin-bottom:14px;
                                  box-sizing:border-box">

                    <label style="display:block;font-size:14px;font-weight:bold;
                                  color:#003366;margin-bottom:4px">Phone Number *</label>
                    <input type="tel" name="phone"
                           value="<?= htmlspecialchars($buyer_info['phone'] ?? '') ?>"
                           placeholder="e.g. 071 234 5678" required
                           style="width:100%;padding:10px 14px;border:1px solid #ddd;
                                  border-radius:8px;font-size:14px;margin-bottom:14px;
                                  box-sizing:border-box">

                    <label style="display:block;font-size:14px;font-weight:bold;
                                  color:#003366;margin-bottom:4px">Street Address *</label>
                    <input type="text" name="address"
                           placeholder="e.g. 123 Main Street" required
                           style="width:100%;padding:10px 14px;border:1px solid #ddd;
                                  border-radius:8px;font-size:14px;margin-bottom:14px;
                                  box-sizing:border-box">

                    <label style="display:block;font-size:14px;font-weight:bold;
                                  color:#003366;margin-bottom:4px">City / Town *</label>
                    <input type="text" name="city"
                           value="<?= htmlspecialchars($buyer_info['location'] ?? '') ?>"
                           placeholder="e.g. Johannesburg" required
                           style="width:100%;padding:10px 14px;border:1px solid #ddd;
                                  border-radius:8px;font-size:14px;margin-bottom:0;
                                  box-sizing:border-box">

            </div>

            <!-- PAYMENT METHOD -->
            <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:24px">
                <h3 style="color:#003366;margin-bottom:20px;font-size:18px">💳 Payment Method</h3>

                <div onclick="selectPayment('escrow')" id="escrowCard"
                     style="border:2px solid #ddd;border-radius:10px;padding:18px;
                            margin-bottom:12px;cursor:pointer;transition:all 0.2s">
                    <div style="display:flex;align-items:center;gap:14px">
                        <div style="font-size:32px">🔒</div>
                        <div>
                            <div style="font-weight:bold;color:#003366;font-size:15px">
                                Secure Escrow
                            </div>
                            <div style="font-size:13px;color:#666;margin-top:4px">
                                Funds held safely until you confirm delivery. Recommended for online orders.
                            </div>
                        </div>
                    </div>
                </div>

                <div onclick="selectPayment('cash')" id="cashCard"
                     style="border:2px solid #ddd;border-radius:10px;padding:18px;
                            cursor:pointer;transition:all 0.2s">
                    <div style="display:flex;align-items:center;gap:14px">
                        <div style="font-size:32px">💵</div>
                        <div>
                            <div style="font-weight:bold;color:#003366;font-size:15px">
                                Cash on Collection
                            </div>
                            <div style="font-size:13px;color:#666;margin-top:4px">
                                Pay in cash when you collect your item from the seller.
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="payment_method" id="paymentInput" value="">

                <button type="submit"
                        style="width:100%;padding:14px;background:#cc0000;color:white;
                               border:none;border-radius:8px;cursor:pointer;font-weight:bold;
                               font-size:16px;margin-top:20px;margin-bottom:0"
                        onmouseover="this.style.background='#aa0000'"
                        onmouseout="this.style.background='#cc0000'">
                    Place Order →
                </button>

                </form>
            </div>

        </div>

        <!-- RIGHT: ORDER SUMMARY -->
        <div style="background:white;border:1px solid #ddd;border-radius:12px;
                    padding:24px;position:sticky;top:80px">
            <h3 style="color:#003366;margin-bottom:16px;font-size:18px">Order Summary</h3>

            <?php foreach ($item_list as $item): ?>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;
                        padding-bottom:14px;border-bottom:1px solid #f0f0f0">
                <?php if ($item['image']): ?>
                    <img src="/assets/uploads/<?= htmlspecialchars($item['image']) ?>"
                         style="width:50px;height:50px;object-fit:cover;border-radius:6px;flex-shrink:0">
                <?php else: ?>
                    <div style="width:50px;height:50px;border-radius:6px;flex-shrink:0;
                                background:linear-gradient(135deg,#003366,#cc0000);
                                display:flex;align-items:center;justify-content:center;font-size:20px">
                        <?= $item['listing_type'] === 'service' ? '🛠️' : '📦' ?>
                    </div>
                <?php endif; ?>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:bold;color:#003366">
                        <?= htmlspecialchars(substr($item['title'], 0, 30)) ?>
                    </div>
                    <div style="font-size:12px;color:#999">
                        x<?= $item['quantity'] ?> &nbsp;|&nbsp; R <?= number_format($item['price'], 2) ?> each
                    </div>
                </div>
                <div style="font-size:14px;font-weight:bold;color:#003366;flex-shrink:0">
                    R <?= number_format($item['subtotal'], 2) ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div style="display:flex;justify-content:space-between;font-size:18px;
                        font-weight:bold;color:#003366;border-top:2px solid #003366;
                        padding-top:14px;margin-top:4px">
                <span>Total</span>
                <span>R <?= number_format($total, 2) ?></span>
            </div>

            <a href="cart.php"
               style="display:block;text-align:center;padding:10px;color:#003366;
                      text-decoration:none;font-size:14px;margin-top:14px;font-weight:bold">
                ← Edit Cart
            </a>
        </div>

    </div>
</div>

<?php endif; ?>

<script>
// Keep track of the method selection state
let selectedMethod = '';

function selectPayment(method) {
    document.getElementById('escrowCard').style.border = '2px solid #ddd';
    document.getElementById('escrowCard').style.background = 'white';
    document.getElementById('cashCard').style.border = '2px solid #ddd';
    document.getElementById('cashCard').style.background = 'white';

    if (method === 'escrow') {
        document.getElementById('escrowCard').style.border = '2px solid #003366';
        document.getElementById('escrowCard').style.background = '#e8f0fb';
    } else {
        document.getElementById('cashCard').style.border = '2px solid #003366';
        document.getElementById('cashCard').style.background = '#e8f0fb';
    }

    document.getElementById('paymentInput').value = method;
    selectedMethod = method; // Update local tracker variable state
}

// INTERCEPT THE PLACE ORDER SUBMISSION
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    // Stop the browser from immediately refreshing or posting the form
    e.preventDefault();

    // Ensure they chose a payment option
    if (!selectedMethod) {
        alert('Please select a payment method before proceeding.');
        return;
    }

    // 1. ROUTE A: CASH ON COLLECTION
    if (selectedMethod === 'cash') {
        // Allow the standard PHP processing script block at the top of checkout.php to handle it
        this.submit();
        return;
    }

    // 2. ROUTE B: SECURE ESCROW (PAYGATE INTERACTION ENGINE)
    if (selectedMethod === 'escrow') {
        // Collect cart items information from PHP dynamically using JSON parsing
        const cartItems = <?= json_encode($item_list) ?>;
        const overallTotal = <?= json_encode($total) ?>;

        // Take the first listing item data properties as the main payment description fields
        const primaryItem = cartItems[0];

        // Dispatch background query to your initiate file in the root folder
        fetch('initiate_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                listing_id: primaryItem.listing_id,
                seller_id: primaryItem.seller_id,
                amount: overallTotal
            })
        })
        .then(response => response.json())
        .then(payload => {
            if (payload.success) {
                // Programmatically build a temporary hidden standard form to pass parameters securely to PayGate
                const tempForm = document.createElement('form');
                tempForm.method = 'POST';
                tempForm.action = payload.redirect_url;

                // Loop across data inputs attaching fields natively
                for (const key in payload.data) {
                    if (payload.data.hasOwnProperty(key)) {
                        const hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = key;
                        hiddenField.value = payload.data[key];
                        tempForm.appendChild(hiddenField);
                    }
                }

                document.body.appendChild(tempForm);
                tempForm.submit(); // Dispatches request pushing customer into PayGate terminal interface
            } else {
                alert('Payment Gateway Initialization Failure: ' + payload.message);
            }
        })
        .catch(error => {
            console.error('Network execution error:', error);
            alert('An infrastructure communication timeout occurred.');
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>