<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    'SELECT t.*, l.title, l.image, l.listing_type,
            u.username AS seller_name, u.phone AS seller_phone,
            t.id AS transaction_id, t.status AS transaction_status
     FROM transactions t
     JOIN listings l ON t.listing_id = l.id
     JOIN users u ON t.seller_id = u.id
     WHERE t.buyer_id = ?
     ORDER BY t.created_at DESC'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders = $stmt->get_result();
?>

<?php include 'includes/header.php'; ?>

<div style="max-width:900px;margin:30px auto;padding:0 20px">
    <h1 style="color:#003366;margin-bottom:6px;font-size:28px">📦 My Orders</h1>
    <p style="color:#666;margin-bottom:24px;font-size:15px">Track and manage your orders</p>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'review_added'): ?>
        <div style="background:#e8f5ee;color:#1a7a4a;border:1px solid #c3e6c3;border-radius:8px;padding:12px;margin-bottom:16px;font-size:14px;font-weight:bold;">
            ✅ Review submitted successfully! Your feedback is live on the seller's dashboard.
        </div>
    <?php endif; ?>

    <?php if ($orders->num_rows === 0): ?>
        <div style="min-height:50vh;display:flex;align-items:center;justify-content:center">
            <div style="text-align:center;padding:60px 40px;background:white;
                        border:1px solid #ddd;border-radius:16px;max-width:420px;width:100%">
                <div style="font-size:80px;margin-bottom:20px">📦</div>
                <h2 style="color:#003366;font-size:22px;margin-bottom:10px">No orders yet</h2>
                <p style="color:#666;font-size:15px;margin-bottom:30px">
                    You haven't placed any orders yet. Browse listings to find something you love!
                </p>
                <a href="/listings.php"
                   style="display:inline-block;padding:14px 32px;background:#003366;
                          color:white;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px"
                   onmouseover="this.style.background='#002244'"
                   onmouseout="this.style.background='#003366'">
                    Browse Listings
                </a>
            </div>
        </div>

    <?php else: ?>
        <?php while ($order = $orders->fetch_assoc()): ?>
        <div style="background:white;border:1px solid #ddd;border-radius:12px;
                    padding:20px;margin-bottom:16px">
            <div style="display:flex;gap:16px;align-items:center">

                <!-- IMAGE -->
                <?php if (!empty($order['image'])): ?>
                    <img src="/assets/uploads/<?= htmlspecialchars($order['image']) ?>"
                         style="width:80px;height:80px;object-fit:cover;border-radius:8px;flex-shrink:0">
                <?php else: ?>
                    <div style="width:80px;height:80px;border-radius:8px;flex-shrink:0;
                                background:linear-gradient(135deg,#003366,#cc0000);
                                display:flex;align-items:center;justify-content:center;font-size:28px">
                        <?= ($order['listing_type'] ?? '') === 'service' ? '🛠️' : '📦' ?>
                    </div>
                <?php endif; ?>

                <!-- DETAILS -->
                <div style="flex:1;min-width:0">
                    <div style="font-weight:bold;color:#003366;font-size:16px;margin-bottom:4px">
                        <?= htmlspecialchars($order['title']) ?>
                    </div>
                    <div style="font-size:13px;color:#666;margin-bottom:4px">
                        👤 Seller: <?= htmlspecialchars($order['seller_name']) ?>
                        <?php if (!empty($order['seller_phone'])): ?>
                            &nbsp;|&nbsp; 📞 <?= htmlspecialchars($order['seller_phone']) ?>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:13px;color:#666;margin-bottom:4px">
                        📅 Ordered: <?= date('d M Y', strtotime($order['created_at'])) ?>
                        &nbsp;|&nbsp;
                        💳 <?= ucfirst($order['payment_method']) ?>
                    </div>
                    <div style="font-size:16px;font-weight:bold;color:#cc0000">
                        R <?= number_format($order['amount'], 2) ?>
                    </div>
                </div>

                <!-- STATUS + ACTIONS -->
                <div style="text-align:right;flex-shrink:0">

                    <?php
                    $status = $order['transaction_status'];
                    $badge_color = '#666';
                    $badge_bg = '#f0f0f0';
                    if ($status === 'pending')  { $badge_color = '#e07b00'; $badge_bg = '#fff4e0'; }
                    if ($status === 'released') { $badge_color = '#1a7a4a'; $badge_bg = '#e8f5ee'; }
                    if ($status === 'refunded') { $badge_color = '#cc0000'; $badge_bg = '#fdecea'; }
                    ?>
                    <span style="background:<?= $badge_bg ?>;color:<?= $badge_color ?>;
                                 padding:6px 14px;border-radius:20px;font-size:13px;
                                 font-weight:bold;display:inline-block;margin-bottom:10px">
                        <?= ucfirst($status) ?>
                    </span>

                    <?php if ($status === 'pending' && $order['payment_method'] === 'escrow'): ?>
                        <br>
                        <form method="POST" action="confirm_delivery.php">
                            <input type="hidden" name="transaction_id" value="<?= $order['transaction_id'] ?>">
                            <button type="submit"
                                    onclick="return confirm('Confirm you have received this item? This will release payment to the seller.')"
                                    style="padding:10px 16px;background:#1a7a4a;color:white;
                                           border:none;border-radius:8px;cursor:pointer;
                                           font-weight:bold;font-size:13px;margin:0">
                                ✅ Confirm Delivery
                            </button>
                        </form>
                    <?php elseif ($status === 'pending' && $order['payment_method'] === 'cash'): ?>
                        <br>
                        <span style="font-size:12px;color:#666">Awaiting cash payment on collection</span>
                    <?php elseif ($status === 'released'): ?>
                        <br>
                        <span style="font-size:12px;color:#1a7a4a;font-weight:bold">✅ Delivery confirmed</span>

                        <?php
                        $rev_check = $conn->prepare("SELECT id FROM reviews WHERE transaction_id = ?");
                        $rev_check->bind_param("i", $order['transaction_id']);
                        $rev_check->execute();
                        $has_reviewed = $rev_check->get_result()->num_rows > 0;
                        ?>

                        <?php if (!$has_reviewed): ?>
                            <div style="margin-top:12px;padding:12px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:8px;text-align:left;max-width:260px;display:inline-block">
                                <form method="POST" action="submit_review.php">
                                    <input type="hidden" name="transaction_id" value="<?= $order['transaction_id'] ?>">
                                    <input type="hidden" name="listing_id"     value="<?= $order['listing_id'] ?>">
                                    <input type="hidden" name="seller_id"      value="<?= $order['seller_id'] ?>">

                                    <label style="display:block;font-size:12px;font-weight:bold;color:#003366;margin-bottom:4px">Rating:</label>
                                    <select name="rating" required style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;font-size:13px;margin-bottom:8px;background:white">
                                        <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                                        <option value="4">⭐⭐⭐⭐ (4/5)</option>
                                        <option value="3">⭐⭐⭐ (3/5)</option>
                                        <option value="2">⭐⭐ (2/5)</option>
                                        <option value="1">⭐ (1/5)</option>
                                    </select>

                                    <label style="display:block;font-size:12px;font-weight:bold;color:#003366;margin-bottom:4px">Review Details:</label>
                                    <textarea name="comment" placeholder="Write your experience..."
                                              style="width:100%;height:52px;padding:6px;border:1px solid #ddd;
                                                     border-radius:6px;font-size:12px;font-family:inherit;
                                                     box-sizing:border-box;resize:none;margin-bottom:8px"></textarea>

                                    <button type="submit"
                                            style="width:100%;padding:8px;background:#003366;color:white;
                                                   border:none;border-radius:6px;font-weight:bold;font-size:12px;cursor:pointer"
                                            onmouseover="this.style.background='#002244'"
                                            onmouseout="this.style.background='#003366'">
                                        ⭐ Submit Review
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="font-size:12px;color:#666;margin-top:8px;font-style:italic">🌟 Review left for this order!</div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <br>
                    <a href="messages.php?with=<?= $order['seller_id'] ?>"
                       style="display:inline-block;margin-top:8px;font-size:13px;
                              color:#003366;font-weight:bold;text-decoration:none">
                        💬 Message Seller
                    </a>
                </div>

            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>