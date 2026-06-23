<?php
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

if ($_SESSION['role'] !== 'seller') {
    header('Location: listings.php'); exit;
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user details
$stmt = $conn->prepare('SELECT * FROM users WHERE id=?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Total active listings
$stmt2 = $conn->prepare('SELECT COUNT(*) AS cnt FROM listings WHERE seller_id=? AND status="active"');
$stmt2->bind_param('i', $user_id);
$stmt2->execute();
$active_listings = $stmt2->get_result()->fetch_assoc()['cnt'];

// Completed sales and total earnings
$stmt3 = $conn->prepare(
    'SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
     FROM transactions WHERE seller_id=? AND status="completed"'
);
$stmt3->bind_param('i', $user_id);
$stmt3->execute();
$sales = $stmt3->get_result()->fetch_assoc();

// Average rating
$stmt4 = $conn->prepare(
    'SELECT ROUND(AVG(rating),1) AS avg, COUNT(*) AS cnt
     FROM reviews WHERE seller_id=?'
);
$stmt4->bind_param('i', $user_id);
$stmt4->execute();
$ratings = $stmt4->get_result()->fetch_assoc();

// Unread messages
$stmt5 = $conn->prepare(
    'SELECT COUNT(*) AS cnt FROM messages WHERE receiver_id=? AND is_read=0'
);
$stmt5->bind_param('i', $user_id);
$stmt5->execute();
$unread = $stmt5->get_result()->fetch_assoc()['cnt'];

// Recent listings
$stmt6 = $conn->prepare(
    'SELECT * FROM listings WHERE seller_id=? ORDER BY created_at DESC LIMIT 5'
);
$stmt6->bind_param('i', $user_id);
$stmt6->execute();
$recent_listings = $stmt6->get_result();

// Recent sales
$stmt7 = $conn->prepare(
    'SELECT t.id AS payment_id, t.amount, t.payment_method AS method,
            t.status AS payment_status, t.created_at,
            l.title, u.username AS buyer_name,
            t.status AS transaction_status
     FROM transactions t
     JOIN listings l ON t.listing_id = l.id
     JOIN users u ON t.buyer_id = u.id
     WHERE t.seller_id=?
     ORDER BY t.created_at DESC LIMIT 5'
);
$stmt7->bind_param('i', $user_id);
$stmt7->execute();
$recent_sales = $stmt7->get_result();

// Recent reviews
$stmt8 = $conn->prepare(
    'SELECT r.*, u.username AS reviewer_name
     FROM reviews r
     JOIN users u ON r.buyer_id = u.id
     WHERE r.seller_id=?
     ORDER BY r.created_at DESC LIMIT 3'
);
$stmt8->bind_param('i', $user_id);
$stmt8->execute();
$recent_reviews = $stmt8->get_result();
?>

<?php include 'includes/header.php'; ?>

<!-- DASHBOARD HEADER -->
<div style="background:linear-gradient(135deg,#003366,#001a33);color:white;padding:40px">
    <div style="max-width:900px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
        <div>
            <h1 style="font-size:28px;margin-bottom:6px">
                Welcome back, <?= htmlspecialchars($username) ?> 👋
            </h1>
            <p style="opacity:0.8;font-size:15px">
                Here is an overview of your Street Trade SA seller account
            </p>
        </div>
        <a href="listing_create.php"
           style="background:#cc0000;color:white;padding:12px 24px;border-radius:8px;
                  text-decoration:none;font-weight:bold;font-size:15px"
           onmouseover="this.style.background='#aa0000'"
           onmouseout="this.style.background='#cc0000'">
            + Post New Listing
        </a>
    </div>
</div>

<div class="dashboard">

    <!-- STAT CARDS -->
    <div class="stat-cards" style="margin-top:24px">
        <div class="stat-card">
            <span class="stat-label">Active Listings</span>
            <span class="stat-value"><?= $active_listings ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Completed Sales</span>
            <span class="stat-value"><?= $sales['cnt'] ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total Earnings</span>
            <span class="stat-value" style="font-size:20px">
                R <?= number_format($sales['total'], 2) ?>
            </span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Avg Rating</span>
            <span class="stat-value">
                <?= $ratings['avg'] ?? '0.0' ?> ⭐
                <span style="font-size:13px;color:#999;display:block">
                    <?= $ratings['cnt'] ?> review<?= $ratings['cnt'] != 1 ? 's' : '' ?>
                </span>
            </span>
        </div>
        <div class="stat-card" style="cursor:pointer"
             onclick="window.location='messages.php'">
            <span class="stat-label">Unread Messages</span>
            <span class="stat-value" style="color:<?= $unread > 0 ? '#cc0000' : '#003366' ?>">
                <?= $unread ?>
                <?= $unread > 0 ? '🔴' : '' ?>
            </span>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:20px 0">
        <a href="listing_create.php"
           style="padding:10px 20px;background:#003366;color:white;border-radius:8px;
                  text-decoration:none;font-size:14px;font-weight:bold">
            📦 Post New Listing
        </a>
        <a href="seller_orders.php"
           style="padding:10px 20px;background:#cc0000;color:white;border-radius:8px;
                  text-decoration:none;font-size:14px;font-weight:bold">
            📋 View Orders
        </a>
        <a href="listings.php"
           style="padding:10px 20px;background:#f0f4f8;color:#003366;border-radius:8px;
                  text-decoration:none;font-size:14px;font-weight:bold">
            🔍 View All Listings
        </a>
        <a href="messages.php"
           style="padding:10px 20px;background:#f0f4f8;color:#003366;border-radius:8px;
                  text-decoration:none;font-size:14px;font-weight:bold">
            💬 Messages
            <?php if ($unread > 0): ?>
            <span style="background:#cc0000;color:white;border-radius:50%;
                         padding:1px 6px;font-size:11px;margin-left:4px">
                <?= $unread ?>
            </span>
            <?php endif; ?>
        </a>
    </div>

    <!-- MY LISTINGS TABLE -->
    <div style="background:white;border:1px solid #ddd;border-radius:12px;
                overflow:hidden;margin-bottom:24px">
        <div style="background:#003366;color:white;padding:14px 20px;
                    display:flex;justify-content:space-between;align-items:center">
            <span style="font-weight:bold;font-size:15px">My Listings</span>
            <a href="listing_create.php"
               style="color:white;font-size:13px;text-decoration:none;opacity:0.8">
                + Add New
            </a>
        </div>

        <?php if ($recent_listings->num_rows === 0): ?>
            <div style="padding:40px;text-align:center;color:#666;font-size:14px">
                <div style="font-size:50px;margin-bottom:16px">📦</div>
                <h3 style="color:#003366;margin-bottom:8px">No listings yet</h3>
                <p style="margin-bottom:20px">Post your first listing to start selling on Street Trade SA</p>
                <a href="listing_create.php"
                   style="background:#003366;color:white;padding:10px 24px;
                          border-radius:8px;text-decoration:none;font-weight:bold;font-size:14px">
                    Post Your First Listing
                </a>
            </div>
        <?php else: ?>
            <table>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Date Posted</th>
                    <th>Actions</th>
                </tr>
                <?php while ($l = $recent_listings->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($l['title']) ?></strong></td>
                    <td><?= ucfirst($l['listing_type'] ?? 'product') ?></td>
                    <td style="color:#cc0000;font-weight:bold">
                        R <?= number_format($l['price'], 2) ?>
                    </td>
                    <td>
                        <span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:bold;
                            background:<?= $l['status'] === 'active' ? '#e8f5ee' : '#fdecea' ?>;
                            color:<?= $l['status'] === 'active' ? '#1a7a4a' : '#cc0000' ?>">
                            <?= ucfirst($l['status']) ?>
                        </span>
                    </td>
                    <td style="color:#666;font-size:13px">
                        <?= date('d M Y', strtotime($l['created_at'])) ?>
                    </td>
                    <td>
                        <a href="listing_view.php?id=<?= $l['id'] ?>"
                           style="color:#003366;text-decoration:none;font-size:13px;margin-right:8px">
                            View
                        </a>
                        <a href="listing_edit.php?id=<?= $l['id'] ?>"
                           style="color:#e07b00;text-decoration:none;font-size:13px;margin-right:8px">
                            Edit
                        </a>
                        <a href="listing_delete.php?id=<?= $l['id'] ?>"
                           style="color:#cc0000;text-decoration:none;font-size:13px"
                           onclick="return confirm('Are you sure you want to delete this listing?')">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- RECENT SALES TABLE -->
    <div style="background:white;border:1px solid #ddd;border-radius:12px;
                overflow:hidden;margin-bottom:24px">
        <div style="background:#003366;color:white;padding:14px 20px">
            <span style="font-weight:bold;font-size:15px">Recent Sales</span>
        </div>

        <?php if ($recent_sales->num_rows === 0): ?>
            <div style="padding:40px;text-align:center;color:#666;font-size:14px">
                <div style="font-size:50px;margin-bottom:16px">💰</div>
                <p>No sales yet. Post a listing to start selling!</p>
            </div>
        <?php else: ?>
            <table>
                <tr>
                    <th>Listing</th>
                    <th>Buyer</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
                <?php while ($s = $recent_sales->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($s['title']) ?></td>
                    <td><?= htmlspecialchars($s['buyer_name']) ?></td>
                    <td style="color:#1a7a4a;font-weight:bold">
                        R <?= number_format($s['amount'], 2) ?>
                    </td>
                    <td><?= ucfirst($s['method'] ?? 'cash') ?></td>
                    <td>
                        <?php
                        $st = $s['payment_status'];
                        if ($st === 'completed') {
                            $sc = '#1a7a4a'; $sb = '#e8f5ee'; $sl = 'Completed';
                        } elseif ($st === 'delivered') {
                            $sc = '#1a7a4a'; $sb = '#e8f5ee'; $sl = 'Delivered';
                        } elseif ($st === 'ready_for_delivery') {
                            $sc = '#7b3fbe'; $sb = '#f3eeff'; $sl = 'Ready for Delivery';
                        } elseif ($st === 'refunded') {
                            $sc = '#cc0000'; $sb = '#fdecea'; $sl = 'Refunded';
                        } else {
                            $sc = '#e07b00'; $sb = '#fff4e0'; $sl = 'Pending';
                        }
                        ?>
                        <span style="padding:3px 10px;border-radius:20px;font-size:12px;
                                     font-weight:bold;background:<?= $sb ?>;color:<?= $sc ?>">
                            <?= $sl ?>
                        </span>
                    </td>
                    <td style="color:#666;font-size:13px">
                        <?= date('d M Y', strtotime($s['created_at'])) ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- RECENT REVIEWS -->
    <div style="background:white;border:1px solid #ddd;border-radius:12px;
                overflow:hidden;margin-bottom:40px">
        <div style="background:#003366;color:white;padding:14px 20px">
            <span style="font-weight:bold;font-size:15px">Recent Reviews</span>
        </div>

        <?php if ($recent_reviews->num_rows === 0): ?>
            <div style="padding:40px;text-align:center;color:#666;font-size:14px">
                <div style="font-size:50px;margin-bottom:16px">⭐</div>
                <p>No reviews yet. Complete sales to receive reviews from buyers.</p>
            </div>
        <?php else: ?>
            <div style="padding:20px;display:flex;flex-direction:column;gap:16px">
                <?php while ($rev = $recent_reviews->fetch_assoc()): ?>
                <div style="border:1px solid #eee;border-radius:10px;padding:16px">
                    <div style="display:flex;justify-content:space-between;
                                align-items:center;margin-bottom:8px">
                        <strong style="color:#003366">
                            <?= htmlspecialchars($rev['reviewer_name']) ?>
                        </strong>
                        <div>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span style="color:<?= $i <= $rev['rating'] ? '#f4c430' : '#ddd' ?>;font-size:18px">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if ($rev['comment']): ?>
                    <p style="color:#444;font-size:14px;line-height:1.6">
                        "<?= htmlspecialchars($rev['comment']) ?>"
                    </p>
                    <?php endif; ?>
                    <p style="color:#999;font-size:12px;margin-top:8px">
                        <?= date('d M Y', strtotime($rev['created_at'])) ?>
                    </p>
                </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'includes/footer.php'; ?>