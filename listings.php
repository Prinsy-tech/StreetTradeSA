<?php
require_once 'includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: listings.php'); exit;
}

$stmt = $conn->prepare(
    'SELECT l.*, u.username, u.full_name, u.phone, u.location AS seller_location, u.id AS seller_id,
            c.name AS cat_name,
            COALESCE(AVG(r.rating), 0) AS avg_rating,
            COUNT(r.id) AS review_count
     FROM listings l
     JOIN users u ON l.seller_id = u.id
     LEFT JOIN categories c ON l.category_id = c.id
     LEFT JOIN reviews r ON r.seller_id = l.seller_id
     WHERE l.id = ? AND l.status = "active"
     GROUP BY l.id'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing) {
    header('Location: listings.php'); exit;
}

$reviews = $conn->prepare(
    'SELECT r.*, u.username AS buyer_name
     FROM reviews r
     JOIN users u ON r.buyer_id = u.id
     WHERE r.seller_id = ?
     ORDER BY r.created_at DESC
     LIMIT 5'
);
$reviews->bind_param('i', $listing['seller_id']);
$reviews->execute();
$reviews_result = $reviews->get_result();
?>

<?php include 'includes/header.php'; ?>

<div style="background:#003366;padding:30px 20px;color:white">
    <div style="max-width:1000px;margin:0 auto">
        <a href="listings.php" style="color:rgba(255,255,255,0.7);text-decoration:none;font-size:14px">
            ← Back to Listings
        </a>
    </div>
</div>

<div style="max-width:1000px;margin:30px auto;padding:0 20px">
    <div style="display:grid;grid-template-columns:1fr 320px;gap:30px;align-items:start">

        <div>
            <?php if ($listing['image']): ?>
                <img src="assets/uploads/<?= htmlspecialchars($listing['image']) ?>"
                     style="width:100%;max-height:400px;object-fit:cover;border-radius:12px;margin-bottom:24px">
            <?php else: ?>
                <div style="width:100%;height:300px;background:linear-gradient(135deg,#003366,#cc0000);
                            border-radius:12px;display:flex;align-items:center;justify-content:center;
                            font-size:80px;margin-bottom:24px">
                    <?= $listing['listing_type'] === 'service' ? '🛠️' : '📦' ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap">
                <span style="background:<?= $listing['listing_type'] === 'service' ? '#fff4e0' : '#e8f5ee' ?>;
                             color:<?= $listing['listing_type'] === 'service' ? '#e07b00' : '#1a7a4a' ?>;
                             padding:4px 12px;border-radius:20px;font-size:13px;font-weight:bold">
                    <?= ucfirst($listing['listing_type']) ?>
                </span>
                <span style="background:#f0f4f8;color:#666;padding:4px 12px;border-radius:20px;font-size:13px">
                    <?= htmlspecialchars($listing['cat_name'] ?? 'Other') ?>
                </span>
            </div>

            <h1 style="color:#003366;font-size:28px;margin-bottom:10px">
                <?= htmlspecialchars($listing['title']) ?>
            </h1>

            <div style="margin-bottom:16px">
                <?php
                $stars = round($listing['avg_rating']);
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= $stars
                        ? '<span style="color:#f4c430;font-size:20px">★</span>'
                        : '<span style="color:#ddd;font-size:20px">★</span>';
                }
                ?>
                <span style="color:#666;font-size:14px;margin-left:6px">
                    (<?= $listing['review_count'] ?> review<?= $listing['review_count'] != 1 ? 's' : '' ?>)
                </span>
            </div>

            <div style="font-size:32px;font-weight:bold;color:#cc0000;margin-bottom:20px">
                R <?= number_format($listing['price'], 2) ?>
            </div>

            <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:24px;margin-bottom:24px">
                <h3 style="color:#003366;margin-bottom:12px;font-size:18px">Description</h3>
                <p style="color:#444;line-height:1.8;font-size:15px">
                    <?= nl2br(htmlspecialchars($listing['description'])) ?>
                </p>
            </div>

            <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:20px;margin-bottom:24px">
                <h3 style="color:#003366;margin-bottom:10px;font-size:16px">📍 Location</h3>
                <p style="color:#444;font-size:15px">
                    <?= htmlspecialchars($listing['location'] ?? $listing['seller_location'] ?? 'South Africa') ?>
                </p>
            </div>

            <?php if ($reviews_result->num_rows > 0): ?>
            <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:24px">
                <h3 style="color:#003366;margin-bottom:16px;font-size:18px">Customer Reviews</h3>
                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                <div style="border-bottom:1px solid #f0f0f0;padding-bottom:16px;margin-bottom:16px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <strong style="color:#003366;font-size:14px">
                            <?= htmlspecialchars($review['buyer_name']) ?>
                        </strong>
                        <span style="font-size:12px;color:#999">
                            <?= date('d M Y', strtotime($review['created_at'])) ?>
                        </span>
                    </div>
                    <div style="margin-bottom:6px">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span style="color:<?= $i <= $review['rating'] ? '#f4c430' : '#ddd' ?>;font-size:16px">★</span>
                        <?php endfor; ?>
                    </div>
                    <?php if ($review['comment']): ?>
                    <p style="color:#444;font-size:14px;line-height:1.6">
                        <?= htmlspecialchars($review['comment']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>

        <div style="position:sticky;top:80px">
            <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:20px;margin-bottom:16px">
                <h3 style="color:#003366;margin-bottom:16px;font-size:16px">Seller Information</h3>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
                    <div style="width:50px;height:50px;border-radius:50%;background:#003366;
                                color:white;display:flex;align-items:center;justify-content:center;
                                font-weight:bold;font-size:20px;flex-shrink:0">
                        <?= strtoupper(substr($listing['username'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:bold;color:#003366;font-size:15px">
                            <?= htmlspecialchars($listing['full_name'] ?: $listing['username']) ?>
                        </div>
                        <div style="font-size:13px;color:#999">@<?= htmlspecialchars($listing['username']) ?></div>
                    </div>
                </div>

                <div style="font-size:14px;color:#666;margin-bottom:6px">
                    📍 <?= htmlspecialchars($listing['seller_location'] ?? 'South Africa') ?>
                </div>
                <?php if ($listing['phone']): ?>
                <div style="font-size:14px;color:#666;margin-bottom:16px">
                    📞 <?= htmlspecialchars($listing['phone']) ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $listing['seller_id']): ?>
                    <a href="messages.php?with=<?= $listing['seller_id'] ?>"
                       style="display:block;text-align:center;padding:12px;background:#003366;
                              color:white;border-radius:8px;text-decoration:none;
                              font-weight:bold;font-size:14px;margin-bottom:10px"
                       onmouseover="this.style.background='#002244'"
                       onmouseout="this.style.background='#003366'">
                        💬 Message Seller
                    </a>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php"
                       style="display:block;text-align:center;padding:12px;background:#003366;
                              color:white;border-radius:8px;text-decoration:none;
                              font-weight:bold;font-size:14px;margin-bottom:10px">
                        Login to Message Seller
                    </a>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'buyer'): ?>
                    <form method="POST" action="cart_add.php">
                        <input type="hidden" name="listing_id" value="<?= $listing['id'] ?>">
                        <input type="hidden" name="redirect" value="listing_view.php?id=<?= $listing['id'] ?>">
                        <button type="submit"
                                style="width:100%;padding:12px;background:#cc0000;color:white;
                                       border:none;border-radius:8px;cursor:pointer;
                                       font-weight:bold;font-size:14px;margin:0">
                            🛒 Add to Cart
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:20px">
                <h3 style="color:#003366;margin-bottom:12px;font-size:16px">Listing Details</h3>
                <div style="font-size:14px;color:#666;line-height:2">
                    <div>📅 Posted: <?= date('d M Y', strtotime($listing['created_at'])) ?></div>
                    <div>🏷️ Type: <?= ucfirst($listing['listing_type']) ?></div>
                    <div>📂 Category: <?= htmlspecialchars($listing['cat_name'] ?? 'Other') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>