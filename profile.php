<?php
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// Fetch current user data
$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: login.php'); exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $username  = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $phone     = trim($_POST['phone']);
        $location  = trim($_POST['location']);

        if (empty($username)) {
            $error = 'Username cannot be empty.';
        } else {
            $check = $conn->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $check->bind_param('si', $username, $user_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = 'That username is already taken.';
            } else {
                $upd = $conn->prepare(
                    'UPDATE users SET username=?, full_name=?, phone=?, location=? WHERE id=?'
                );
                $upd->bind_param('ssssi', $username, $full_name, $phone, $location, $user_id);
                if ($upd->execute()) {
                    $_SESSION['username'] = $username;
                    $success = 'Profile updated successfully!';
                    $stmt2 = $conn->prepare('SELECT * FROM users WHERE id = ?');
                    $stmt2->bind_param('i', $user_id);
                    $stmt2->execute();
                    $user = $stmt2->get_result()->fetch_assoc();
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
            }
        }
    }

    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password     = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } else {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $upd = $conn->prepare('UPDATE users SET password=? WHERE id=?');
            $upd->bind_param('si', $hashed, $user_id);
            if ($upd->execute()) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

$role       = $user['bio'];
$role_label = ucfirst($role);
$role_icon  = $role === 'seller' ? '🏪' : '🛍️';
$role_color = $role === 'seller' ? '#cc0000' : '#1a7a4a';
$role_bg    = $role === 'seller' ? '#fdecea' : '#e8f5ee';

// Seller stats
if ($role === 'seller') {
    $s1 = $conn->prepare('SELECT COUNT(*) AS cnt FROM listings WHERE seller_id=? AND status="active"');
    $s1->bind_param('i', $user_id);
    $s1->execute();
    $active_listings = $s1->get_result()->fetch_assoc()['cnt'];

    $s2 = $conn->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM payments WHERE seller_id=? AND status="completed"');
    $s2->bind_param('i', $user_id);
    $s2->execute();
    $sales = $s2->get_result()->fetch_assoc();
}

// Buyer stats
if ($role === 'buyer') {
    $b1 = $conn->prepare('SELECT COUNT(*) AS cnt FROM payments WHERE buyer_id=?');
    $b1->bind_param('i', $user_id);
    $b1->execute();
    $order_count = $b1->get_result()->fetch_assoc()['cnt'];

    $b2 = $conn->prepare('SELECT COUNT(*) AS cnt FROM payments WHERE buyer_id=? AND status="completed"');
    $b2->bind_param('i', $user_id);
    $b2->execute();
    $completed = $b2->get_result()->fetch_assoc()['cnt'];
}
?>

<?php include 'includes/header.php'; ?>

<!-- PAGE HEADER -->
<div style="background:linear-gradient(135deg,#003366,#001a33);color:white;padding:40px 20px">
    <div style="max-width:900px;margin:0 auto;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
        <div style="width:72px;height:72px;border-radius:50%;background:#cc0000;
                    color:white;display:flex;align-items:center;justify-content:center;
                    font-weight:bold;font-size:30px;flex-shrink:0;border:3px solid rgba(255,255,255,0.3)">
            <?= strtoupper(substr($user['username'], 0, 1)) ?>
        </div>
        <div>
            <h1 style="font-size:26px;margin-bottom:4px">
                <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>
            </h1>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span style="font-size:14px;opacity:0.75">@<?= htmlspecialchars($user['username']) ?></span>
                <span style="background:<?= $role_bg ?>;color:<?= $role_color ?>;
                             padding:3px 12px;border-radius:20px;font-size:13px;font-weight:bold">
                    <?= $role_icon ?> <?= $role_label ?>
                </span>
            </div>
            <div style="font-size:13px;opacity:0.65;margin-top:4px">
                📅 Member since <?= date('F Y', strtotime($user['created_at'])) ?>
            </div>
        </div>
    </div>
</div>

<div style="max-width:900px;margin:30px auto;padding:0 20px 50px">

    <?php if ($success): ?>
    <div style="background:#e8f5ee;color:#1a7a4a;border:1px solid #c3e6c3;
                border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:14px;font-weight:bold">
        ✅ <?= $success ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:#fdecea;color:#cc0000;border:1px solid #f5c6c2;
                border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:14px">
        ⚠️ <?= $error ?>
    </div>
    <?php endif; ?>

    <!-- STATS ROW -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:28px">

        <?php if ($role === 'seller'): ?>
        <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center">
            <div style="font-size:28px;font-weight:bold;color:#003366"><?= $active_listings ?></div>
            <div style="font-size:13px;color:#666;margin-top:4px">Active Listings</div>
        </div>
        <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center">
            <div style="font-size:28px;font-weight:bold;color:#003366"><?= $sales['cnt'] ?></div>
            <div style="font-size:13px;color:#666;margin-top:4px">Completed Sales</div>
        </div>
        <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center">
            <div style="font-size:22px;font-weight:bold;color:#1a7a4a">R <?= number_format($sales['total'], 2) ?></div>
            <div style="font-size:13px;color:#666;margin-top:4px">Total Earnings</div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'buyer'): ?>
        <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center">
            <div style="font-size:28px;font-weight:bold;color:#003366"><?= $order_count ?></div>
            <div style="font-size:13px;color:#666;margin-top:4px">Total Orders</div>
        </div>
        <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:20px;text-align:center">
            <div style="font-size:28px;font-weight:bold;color:#1a7a4a"><?= $completed ?></div>
            <div style="font-size:13px;color:#666;margin-top:4px">Completed Orders</div>
        </div>
        <?php endif; ?>

        <div style="background:<?= $role_bg ?>;border:1px solid <?= $role_color ?>33;border-radius:12px;padding:20px;text-align:center">
            <div style="font-size:36px"><?= $role_icon ?></div>
            <div style="font-size:14px;font-weight:bold;color:<?= $role_color ?>;margin-top:6px"><?= $role_label ?> Account</div>
        </div>

    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

        <!-- EDIT PROFILE -->
        <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:28px">
            <h2 style="color:#003366;font-size:18px;margin-bottom:20px;
                       padding-bottom:12px;border-bottom:2px solid #f0f0f0">
                ✏️ Edit Profile
            </h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">

                <label style="display:block;font-size:13px;font-weight:bold;color:#003366;margin-bottom:4px">Username *</label>
                <input type="text" name="username"
                       value="<?= htmlspecialchars($user['username']) ?>" required
                       style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                              font-size:14px;margin-bottom:14px;box-sizing:border-box">

                <label style="display:block;font-size:13px;font-weight:bold;color:#003366;margin-bottom:4px">Full Name</label>
                <input type="text" name="full_name"
                       value="<?= htmlspecialchars($user['full_name'] ?? '') ?>"
                       placeholder="Your full name"
                       style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                              font-size:14px;margin-bottom:14px;box-sizing:border-box">

                <label style="display:block;font-size:13px;font-weight:bold;color:#003366;margin-bottom:4px">Phone Number</label>
                <input type="tel" name="phone"
                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                       placeholder="e.g. 071 234 5678"
                       style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                              font-size:14px;margin-bottom:14px;box-sizing:border-box">

                <label style="display:block;font-size:13px;font-weight:bold;color:#003366;margin-bottom:4px">City / Town</label>
                <input type="text" name="location"
                       value="<?= htmlspecialchars($user['location'] ?? '') ?>"
                       placeholder="e.g. Johannesburg"
                       style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                              font-size:14px;margin-bottom:14px;box-sizing:border-box">

                <label style="display:block;font-size:13px;font-weight:bold;color:#666;margin-bottom:4px">
                    Email <span style="font-weight:normal">(cannot be changed)</span>
                </label>
                <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                       style="width:100%;padding:10px 14px;border:1px solid #eee;border-radius:8px;
                              font-size:14px;margin-bottom:20px;box-sizing:border-box;
                              background:#f9f9f9;color:#999;cursor:not-allowed">

                <button type="submit"
                        style="width:100%;padding:12px;background:#003366;color:white;border:none;
                               border-radius:8px;cursor:pointer;font-weight:bold;font-size:15px;margin:0"
                        onmouseover="this.style.background='#002244'"
                        onmouseout="this.style.background='#003366'">
                    Save Changes
                </button>
            </form>
        </div>

        <!-- RIGHT COLUMN -->
        <div style="display:flex;flex-direction:column;gap:20px">

            <!-- CHANGE PASSWORD -->
            <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:28px">
                <h2 style="color:#003366;font-size:18px;margin-bottom:20px;
                           padding-bottom:12px;border-bottom:2px solid #f0f0f0">
                    🔐 Change Password
                </h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <label style="display:block;font-size:13px;font-weight:bold;color:#003366;margin-bottom:4px">Current Password *</label>
                    <input type="password" name="current_password" required
                           placeholder="Enter current password"
                           style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                                  font-size:14px;margin-bottom:14px;box-sizing:border-box">

                    <label style="display:block;font-size:13px;font-weight:bold;color:#003366;margin-bottom:4px">New Password *</label>
                    <input type="password" name="new_password" required
                           placeholder="At least 6 characters"
                           style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                                  font-size:14px;margin-bottom:14px;box-sizing:border-box">

                    <label style="display:block;font-size:13px;font-weight:bold;color:#003366;margin-bottom:4px">Confirm New Password *</label>
                    <input type="password" name="confirm_password" required
                           placeholder="Repeat new password"
                           style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                                  font-size:14px;margin-bottom:20px;box-sizing:border-box">

                    <button type="submit"
                            style="width:100%;padding:12px;background:#cc0000;color:white;border:none;
                                   border-radius:8px;cursor:pointer;font-weight:bold;font-size:15px;margin:0"
                            onmouseover="this.style.background='#aa0000'"
                            onmouseout="this.style.background='#cc0000'">
                        Change Password
                    </button>
                </form>
            </div>

            <!-- ACCOUNT INFO -->
            <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:24px">
                <h2 style="color:#003366;font-size:18px;margin-bottom:16px;
                           padding-bottom:12px;border-bottom:2px solid #f0f0f0">
                    ℹ️ Account Info
                </h2>
                <div style="font-size:14px;color:#444;line-height:2.2">
                    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #f5f5f5;padding-bottom:6px;margin-bottom:2px">
                        <span style="color:#666">Account Type</span>
                        <span style="font-weight:bold;color:<?= $role_color ?>"><?= $role_icon ?> <?= $role_label ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #f5f5f5;padding-bottom:6px;margin-bottom:2px">
                        <span style="color:#666">Email</span>
                        <span style="font-weight:bold"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-bottom:1px solid #f5f5f5;padding-bottom:6px;margin-bottom:2px">
                        <span style="color:#666">Location</span>
                        <span style="font-weight:bold"><?= htmlspecialchars($user['location'] ?? 'Not set') ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between">
                        <span style="color:#666">Member Since</span>
                        <span style="font-weight:bold"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>

                <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f0f0f0;display:flex;flex-direction:column;gap:8px">
                    <?php if ($role === 'seller'): ?>
                    <a href="/dashboard.php"
                       style="display:block;text-align:center;padding:10px;background:#f0f4f8;
                              color:#003366;border-radius:8px;text-decoration:none;font-size:13px;font-weight:bold">
                        📊 Go to Dashboard
                    </a>
                    <a href="/seller_orders.php"
                       style="display:block;text-align:center;padding:10px;background:#f0f4f8;
                              color:#003366;border-radius:8px;text-decoration:none;font-size:13px;font-weight:bold">
                        📋 My Orders
                    </a>
                    <?php else: ?>
                    <a href="/orders.php"
                       style="display:block;text-align:center;padding:10px;background:#f0f4f8;
                              color:#003366;border-radius:8px;text-decoration:none;font-size:13px;font-weight:bold">
                        📦 My Orders
                    </a>
                    <a href="/cart.php"
                       style="display:block;text-align:center;padding:10px;background:#f0f4f8;
                              color:#003366;border-radius:8px;text-decoration:none;font-size:13px;font-weight:bold">
                        🛒 My Cart
                    </a>
                    <?php endif; ?>
                    <a href="/logout.php"
                       style="display:block;text-align:center;padding:10px;background:#fdecea;
                              color:#cc0000;border-radius:8px;text-decoration:none;font-size:13px;font-weight:bold"
                       onclick="return confirm('Are you sure you want to log out?')">
                        🚪 Log Out
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>