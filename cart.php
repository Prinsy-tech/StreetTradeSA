<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    'SELECT c.id AS cart_id, c.quantity, c.listing_id,
            l.title, l.price, l.image, l.listing_type, l.location,
            u.username AS seller_name
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
?>

<?php include 'includes/header.php'; ?>

<div style="max-width:900px;margin:30px auto;padding:0 20px">

    <h1 style="color:#003366;margin-bottom:6px;font-size:28px">🛒 My Cart</h1>
    <p style="color:#666;margin-bottom:24px;font-size:15px">
        <?= count($item_list) ?> item<?= count($item_list) !== 1 ? 's' : '' ?> in your cart
    </p>

    <?php if (empty($item_list)): ?>
        <div style="min-height:60vh;display:flex;align-items:center;justify-content:center">
            <div style="text-align:center;padding:60px 40px;background:white;
                        border:1px solid #ddd;border-radius:16px;max-width:420px;width:100%">
                <div style="font-size:80px;margin-bottom:20px;line-height:1">🛒</div>
                <h2 style="color:#003366;font-size:22px;margin-bottom:10px">Your shopping cart is empty</h2>
                <p style="color:#666;font-size:15px;margin-bottom:30px;line-height:1.6">
                    Looks like you haven't added anything yet.<br>
                    Browse our listings to find something you love!
                </p>
                <a href="/listings.php"
                   style="display:inline-block;padding:14px 32px;background:#003366;
                          color:white;border-radius:8px;text-decoration:none;
                          font-weight:bold;font-size:15px"
                   onmouseover="this.style.background='#002244'"
                   onmouseout="this.style.background='#003366'">
                    Continue Browsing
                </a>
            </div>
        </div>

    <?php else: ?>
        <div style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start">

            <div>
                <?php foreach ($item_list as $item): ?>
                <div style="background:white;border:1px solid #ddd;border-radius:12px;
                            padding:16px;margin-bottom:14px;display:flex;gap:16px;align-items:center">

                    <?php if ($item['image']): ?>
                        <img src="/assets/uploads/<?= htmlspecialchars($item['image']) ?>"
                             style="width:90px;height:90px;object-fit:cover;border-radius:8px;flex-shrink:0">
                    <?php else: ?>
                        <div style="width:90px;height:90px;border-radius:8px;flex-shrink:0;
                                    background:linear-gradient(135deg,#003366,#cc0000);
                                    display:flex;align-items:center;justify-content:center;font-size:32px">
                            <?= $item['listing_type'] === 'service' ? '🛠️' : '📦' ?>
                        </div>
                    <?php endif; ?>

                    <div style="flex:1;min-width:0">
                        <div style="font-weight:bold;color:#003366;font-size:15px;margin-bottom:4px">
                            <?= htmlspecialchars($item['title']) ?>
                        </div>
                        <div style="font-size:13px;color:#666;margin-bottom:4px">
                            👤 <?= htmlspecialchars($item['seller_name']) ?>
                            &nbsp;|&nbsp;
                            📍 <?= htmlspecialchars($item['location'] ?? 'South Africa') ?>
                        </div>
                        <div style="font-size:14px;color:#cc0000;font-weight:bold">
                            R <?= number_format($item['price'], 2) ?> each
                        </div>
                    </div>

                    <form method="POST" action="cart_update.php" style="display:flex;align-items:center;gap:6px">
                        <input type="hidden" name="listing_id" value="<?= $item['listing_id'] ?>">
                        <button type="submit" name="quantity" value="<?= $item['quantity'] - 1 ?>"
                                style="width:30px;height:30px;border:1px solid #ddd;background:#f5f5f5;
                                       border-radius:6px;cursor:pointer;font-size:16px;font-weight:bold;
                                       color:#333;padding:0;margin:0">−</button>
                        <span style="font-size:15px;font-weight:bold;color:#003366;min-width:24px;text-align:center">
                            <?= $item['quantity'] ?>
                        </span>
                        <button type="submit" name="quantity" value="<?= $item['quantity'] + 1 ?>"
                                style="width:30px;height:30px;border:1px solid #ddd;background:#f5f5f5;
                                       border-radius:6px;cursor:pointer;font-size:16px;font-weight:bold;
                                       color:#333;padding:0;margin:0">+</button>
                    </form>

                    <div style="text-align:right;min-width:80px">
                        <div style="font-size:15px;font-weight:bold;color:#003366">
                            R <?= number_format($item['subtotal'], 2) ?>
                        </div>
                    </div>

                    <form method="POST" action="cart_remove.php">
                        <input type="hidden" name="listing_id" value="<?= $item['listing_id'] ?>">
                        <button type="submit"
                                onclick="return confirm('Remove this item from your cart?')"
                                style="width:34px;height:34px;border:1px solid #ffcccc;
                                       background:#fff5f5;border-radius:6px;cursor:pointer;
                                       font-size:16px;color:#cc0000;padding:0;margin:0">🗑️</button>
                    </form>

                </div>
                <?php endforeach; ?>
            </div>

            <div style="background:white;border:1px solid #ddd;border-radius:12px;
                        padding:24px;position:sticky;top:80px">
                <h3 style="color:#003366;margin-bottom:16px;font-size:18px">Order Summary</h3>

                <div style="border-top:1px solid #eee;padding-top:14px;margin-bottom:20px">
                    <?php foreach ($item_list as $item): ?>
                    <div style="display:flex;justify-content:space-between;font-size:13px;color:#666;margin-bottom:8px">
                        <span><?= htmlspecialchars(substr($item['title'], 0, 22)) ?>... x<?= $item['quantity'] ?></span>
                        <span>R <?= number_format($item['subtotal'], 2) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex;justify-content:space-between;font-size:18px;font-weight:bold;
                            color:#003366;border-top:2px solid #003366;padding-top:14px;margin-bottom:20px">
                    <span>Total</span>
                    <span>R <?= number_format($total, 2) ?></span>
                </div>

                <a href="checkout.php"
                   style="display:block;text-align:center;padding:14px;background:#cc0000;
                          color:white;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px"
                   onmouseover="this.style.background='#aa0000'"
                   onmouseout="this.style.background='#cc0000'">
                    Proceed to Checkout
                </a>

                <a href="/listings.php"
                   style="display:block;text-align:center;padding:12px;color:#003366;
                          text-decoration:none;font-size:14px;margin-top:10px;font-weight:bold">
                    ← Continue Shopping
                </a>
            </div>

        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>