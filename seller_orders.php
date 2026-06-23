<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];
$success = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $txn_id     = (int)$_POST['transaction_id'];
    $new_status = $_POST['new_status'];

    $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (in_array($new_status, $allowed)) {
        $upd = $conn->prepare('UPDATE transactions SET status=? WHERE id=? AND seller_id=?');
        $upd->bind_param('sii', $new_status, $txn_id, $user_id);
        $upd->execute();

        // Also update payment status
        if ($new_status === 'completed') {
          $upd2 = $conn->prepare('UPDATE payments SET status="released" WHERE transaction_id=?');
$upd2->bind_param('i', $txn_id);
$upd2->execute();
        } elseif ($new_status === 'cancelled') {
           $upd3 = $conn->prepare('UPDATE payments SET status="refunded" WHERE transaction_id=?');
$upd3->bind_param('i', $txn_id);
$upd3->execute();

        }

        $success = 'Order status updated successfully!';
    }
}

// Get all orders for this seller from transactions table
$stmt = $conn->prepare(
    'SELECT t.id AS transaction_id,
            t.buyer_id, t.seller_id, t.listing_id,
            t.amount, t.payment_method AS method,
            t.status AS payment_status,
            t.created_at, t.notes,
            l.title, l.image, l.listing_type,
            u.username AS buyer_name,
            u.phone AS buyer_phone,
            u.location AS buyer_location,
            u.email AS buyer_email
     FROM transactions t
     JOIN listings l ON t.listing_id = l.id
     JOIN users u ON t.buyer_id = u.id
     WHERE t.seller_id = ?
     ORDER BY t.created_at DESC'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders = $stmt->get_result();

// Count by status
$counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
$all_orders = [];
while ($row = $orders->fetch_assoc()) {
    $all_orders[] = $row;
    $counts['all']++;
    $s = $row['payment_status'];
    if (isset($counts[$s])) $counts[$s]++;
}

// Filter
$filter   = isset($_GET['status']) ? $_GET['status'] : 'all';
$filtered = $filter === 'all'
    ? $all_orders
    : array_filter($all_orders, fn($o) => $o['payment_status'] === $filter);
?>

<?php include 'includes/header.php'; ?>

<div style="max-width:1000px;margin:30px auto;padding:0 20px">

    <div style="display:flex;justify-content:space-between;align-items:center;
                margin-bottom:6px;flex-wrap:wrap;gap:10px">
        <h1 style="color:#003366;font-size:28px">📋 Manage Orders</h1>
        <a href="/dashboard.php"
           style="padding:10px 20px;background:#f5f5f5;color:#003366;border:1px solid #ddd;
                  border-radius:8px;text-decoration:none;font-size:14px;font-weight:bold">
            ← Back to Dashboard
        </a>
    </div>
    <p style="color:#666;margin-bottom:24px;font-size:15px">
        Track and update the status of all your orders
    </p>

    <?php if ($success): ?>
    <div style="background:#e8f5ee;color:#1a7a4a;border:1px solid #c3e6c3;
                border-radius:8px;padding:14px 16px;margin-bottom:20px;
                font-size:14px;font-weight:bold">
        ✅ <?= $success ?>
    </div>
    <?php endif; ?>

    <!-- STATUS FILTER TABS -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px">
        <?php
        $tab_labels = [
            'all'       => ['All Orders',  '#003366'],
            'pending'   => ['Pending',     '#e07b00'],
            'confirmed' => ['Confirmed',   '#7b3fbe'],
            'completed' => ['Completed',   '#1a7a4a'],
            'cancelled' => ['Cancelled',   '#cc0000'],
        ];
        foreach ($tab_labels as $key => [$label, $color]):
            $active = $filter === $key;
        ?>
        <a href="seller_orders.php?status=<?= $key ?>"
           style="padding:8px 16px;border-radius:20px;text-decoration:none;
                  font-size:13px;font-weight:bold;
                  border:2px solid <?= $active ? $color : '#ddd' ?>;
                  background:<?= $active ? $color : 'white' ?>;
                  color:<?= $active ? 'white' : '#666' ?>">
            <?= $label ?>
            <span style="background:<?= $active ? 'rgba(255,255,255,0.3)' : '#f0f0f0' ?>;
                         color:<?= $active ? 'white' : '#666' ?>;
                         border-radius:50%;padding:1px 7px;font-size:11px;margin-left:4px">
                <?= $counts[$key] ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ORDERS LIST -->
    <?php if (empty($filtered)): ?>
        <div style="background:white;border:1px solid #ddd;border-radius:12px;
                    padding:60px 20px;text-align:center">
            <div style="font-size:64px;margin-bottom:16px">📋</div>
            <h3 style="color:#003366;font-size:20px;margin-bottom:8px">No orders found</h3>
            <p style="color:#666;font-size:15px">
                <?= $filter === 'all'
                    ? 'You have not received any orders yet.'
                    : 'No orders with status: ' . $filter ?>
            </p>
        </div>

    <?php else: ?>
        <?php foreach ($filtered as $order): ?>
        <?php
        $status = $order['payment_status'];
        $badge  = [
            'pending'   => ['Pending',   '#e07b00', '#fff4e0'],
            'confirmed' => ['Confirmed', '#7b3fbe', '#f3eeff'],
            'completed' => ['Completed', '#1a7a4a', '#e8f5ee'],
            'cancelled' => ['Cancelled', '#cc0000', '#fdecea'],
            'disputed'  => ['Disputed',  '#cc0000', '#fdecea'],
        ][$status] ?? [$status, '#666', '#f0f0f0'];
        ?>

        <div style="background:white;border:1px solid #ddd;border-radius:12px;
                    padding:20px;margin-bottom:16px">
            <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">

                <!-- IMAGE -->
                <?php if (!empty($order['image'])): ?>
                    <img src="/assets/uploads/<?= htmlspecialchars($order['image']) ?>"
                         style="width:90px;height:90px;object-fit:cover;
                                border-radius:8px;flex-shrink:0">
                <?php else: ?>
                    <div style="width:90px;height:90px;border-radius:8px;flex-shrink:0;
                                background:linear-gradient(135deg,#003366,#cc0000);
                                display:flex;align-items:center;justify-content:center;font-size:32px">
                        <?= ($order['listing_type'] ?? '') === 'service' ? '🛠️' : '📦' ?>
                    </div>
                <?php endif; ?>

                <!-- ORDER DETAILS -->
                <div style="flex:1;min-width:200px">
                    <div style="font-weight:bold;color:#003366;font-size:16px;margin-bottom:6px">
                        <?= htmlspecialchars($order['title']) ?>
                    </div>
                    <div style="font-size:13px;color:#666;margin-bottom:4px">
                        👤 Buyer: <strong><?= htmlspecialchars($order['buyer_name']) ?></strong>
                        <?php if (!empty($order['buyer_phone'])): ?>
                            &nbsp;|&nbsp; 📞 <?= htmlspecialchars($order['buyer_phone']) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($order['buyer_email'])): ?>
                    <div style="font-size:13px;color:#666;margin-bottom:4px">
                        ✉️ <?= htmlspecialchars($order['buyer_email']) ?>
                    </div>
                    <?php endif; ?>
                    <div style="font-size:13px;color:#666;margin-bottom:4px">
                        📍 <?= htmlspecialchars($order['buyer_location'] ?? 'South Africa') ?>
                        &nbsp;|&nbsp;
                        📅 <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
                    </div>
                    <div style="font-size:13px;color:#666;margin-bottom:8px">
                        💳 Payment: <strong><?= ucfirst(str_replace('_', ' ', $order['method'])) ?></strong>
                        &nbsp;|&nbsp;
                        💰 Amount: <strong style="color:#cc0000">
                            R <?= number_format($order['amount'], 2) ?>
                        </strong>
                    </div>

                    <?php if (!empty($order['notes'])): ?>
                    <div style="background:#f9f9f9;border:1px solid #eee;border-radius:6px;
                                padding:8px 12px;font-size:13px;color:#444;margin-bottom:8px">
                        📝 Note: <?= htmlspecialchars($order['notes']) ?>
                    </div>
                    <?php endif; ?>

                    <span style="background:<?= $badge[2] ?>;color:<?= $badge[1] ?>;
                                 padding:6px 14px;border-radius:20px;font-size:13px;
                                 font-weight:bold;display:inline-block">
                        <?= $badge[0] ?>
                    </span>
                </div>

                <!-- ACTIONS -->
                <div style="flex-shrink:0;min-width:200px">
                    <?php if (!in_array($status, ['completed', 'cancelled'])): ?>
                    <form method="POST" style="margin-bottom:10px">
                        <input type="hidden" name="transaction_id" value="<?= $order['transaction_id'] ?>">
                        <input type="hidden" name="update_status"  value="1">

                        <label style="display:block;font-size:12px;font-weight:bold;
                                      color:#003366;margin-bottom:6px">
                            Update Status:
                        </label>
                        <select name="new_status"
                                style="width:100%;padding:8px 12px;border:1px solid #ddd;
                                       border-radius:8px;font-size:13px;background:white;
                                       margin-bottom:8px">
                            <option value="pending"   <?= $status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>

                        <button type="submit"
                                style="width:100%;padding:10px;background:#003366;
                                       color:white;border:none;border-radius:8px;
                                       cursor:pointer;font-weight:bold;font-size:13px;margin:0"
                                onmouseover="this.style.background='#002244'"
                                onmouseout="this.style.background='#003366'">
                            Update Status
                        </button>
                    </form>
                    <?php else: ?>
                        <div style="padding:10px;background:#f0f0f0;border-radius:8px;
                                    text-align:center;font-size:13px;color:#666;margin-bottom:10px">
                            Order <?= ucfirst($status) ?> — No further updates
                        </div>
                    <?php endif; ?>

                    <a href="/messages.php?with=<?= $order['buyer_id'] ?>"
                       style="display:block;text-align:center;padding:10px;background:#f5f5f5;
                              color:#003366;border:1px solid #ddd;border-radius:8px;
                              text-decoration:none;font-weight:bold;font-size:13px"
                       onmouseover="this.style.background='#e8e8e8'"
                       onmouseout="this.style.background='#f5f5f5'">
                        💬 Message Buyer
                    </a>
                </div>
            </div>

            <!-- STATUS TIMELINE -->
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f0">
                <div style="display:flex;gap:0;align-items:center">
                    <?php
                    $steps = [
                        ['pending',   'Pending',   '🕐'],
                        ['confirmed', 'Confirmed', '📦'],
                        ['completed', 'Completed', '✅'],
                    ];
                    $step_keys     = array_column($steps, 0);
                    $current_index = array_search($status, $step_keys);
                    if ($current_index === false) $current_index = 0;

                    foreach ($steps as $i => [$skey, $slabel, $sicon]):
                        $is_done    = ($i < $current_index);
                        $is_current = ($i === $current_index);
                        $dot_bg     = $is_current ? '#003366' : ($is_done ? '#1a7a4a' : '#ddd');
                        $text_color = $is_current ? '#003366' : ($is_done ? '#1a7a4a' : '#999');
                    ?>
                    <div style="text-align:center;flex:1">
                        <div style="width:32px;height:32px;border-radius:50%;
                                    background:<?= $dot_bg ?>;color:white;
                                    display:flex;align-items:center;justify-content:center;
                                    font-size:14px;margin:0 auto 4px">
                            <?= $sicon ?>
                        </div>
                        <div style="font-size:11px;color:<?= $text_color ?>;
                                    font-weight:<?= $is_current ? 'bold' : 'normal' ?>">
                            <?= $slabel ?>
                        </div>
                    </div>
                    <?php if ($i < count($steps) - 1): ?>
                    <div style="flex:1;height:2px;
                                background:<?= $is_done ? '#1a7a4a' : '#ddd' ?>;
                                margin-bottom:20px"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>