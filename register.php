<?php
require_once 'includes/db.php';

$error   = '';
$success = '';
$preRole = isset($_GET['role']) ? $_GET['role'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);
    $location  = trim($_POST['location']);
    $role      = $_POST['role'];

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill in all required fields and choose whether you are a Buyer or Seller.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE username=? OR email=?');
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'That username or email is already taken.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $ins = $conn->prepare('INSERT INTO users (username, email, password, full_name, phone, location, bio) VALUES (?,?,?,?,?,?,?)');
            $ins->bind_param('sssssss', $username, $email, $hashed, $full_name, $phone, $location, $role); // role stored in bio column
            if ($ins->execute()) {
                $success = 'Account created successfully!';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

// Redirect away if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'seller') {
        header('Location:dashboard.php');
    } else {
        header('Location:listings.php');
    }
    exit;
}
?>

<?php include 'includes/header.php'; ?>

<div class="form-container">
    <h1>Create an Account</h1>
    <p style="color:#666;margin-bottom:20px;font-size:14px">Join thousands of traders and buyers across South Africa</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="login.php">Login here</a></div>
    <?php endif; ?>

    <form method="POST">

        <p style="font-weight:bold;color:#003366;margin-bottom:10px;font-size:14px">I want to register as *</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">

            <div id="buyerCard"
                onclick="selectRole('buyer')"
                style="border:2px solid #ddd;border-radius:10px;padding:18px 14px;text-align:center;cursor:pointer;transition:all 0.2s">
                <span style="font-size:32px;display:block;margin-bottom:8px">🛍️</span>
                <div style="font-weight:bold;color:#003366;font-size:15px">Buyer</div>
                <div style="font-size:12px;color:#666;margin-top:4px">Browse and buy from traders</div>
            </div>

            <div id="sellerCard"
                onclick="selectRole('seller')"
                style="border:2px solid #ddd;border-radius:10px;padding:18px 14px;text-align:center;cursor:pointer;transition:all 0.2s">
                <span style="font-size:32px;display:block;margin-bottom:8px">🏪</span>
                <div style="font-weight:bold;color:#003366;font-size:15px">Seller</div>
                <div style="font-size:12px;color:#666;margin-top:4px">List and sell your products</div>
            </div>

        </div>
        <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($preRole) ?>">

        <label>Username *</label>
        <input type="text" name="username" placeholder="Choose a username" required>

        <label>Full Name</label>
        <input type="text" name="full_name" placeholder="Your full name">

        <label>Email *</label>
        <input type="email" name="email" placeholder="your@email.com" required>

        <label>Phone Number</label>
        <input type="tel" name="phone" placeholder="e.g. 071 234 5678">

        <label>City or Town</label>
        <input type="text" name="location" placeholder="e.g. Soweto, Johannesburg">

        <label>Password * (minimum 6 characters)</label>
        <input type="password" name="password" placeholder="Choose a password" required>

        <button type="submit">Create Account</button>
    </form>

    <p style="margin-top:16px;text-align:center;font-size:14px">
        Already have an account? <a href="login.php" style="color:#003366;font-weight:bold">Login here</a>
    </p>
</div>

<script>
function selectRole(role) {
    document.getElementById('buyerCard').style.border     = '2px solid #ddd';
    document.getElementById('buyerCard').style.background = 'white';
    document.getElementById('sellerCard').style.border    = '2px solid #ddd';
    document.getElementById('sellerCard').style.background = 'white';

    if (role === 'buyer') {
        document.getElementById('buyerCard').style.border     = '2px solid #003366';
        document.getElementById('buyerCard').style.background = '#e8f0fb';
    } else {
        document.getElementById('sellerCard').style.border     = '2px solid #cc0000';
        document.getElementById('sellerCard').style.background = '#fff0f0';
    }

    document.getElementById('roleInput').value = role;
}

const preRole = '<?= $preRole ?>';
if (preRole) selectRole(preRole);
</script>

<?php include 'includes/footer.php'; ?>