<?php
require_once 'includes/db.php';

$error = '';

// Redirect away if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'seller') {
        header('Location:dashboard.php');
    } else {
        header('Location:listings.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare('SELECT id, username, password, bio FROM users WHERE email=?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['bio'];

            if ($user['bio'] === 'seller') {
                header('Location:dashboard.php');
            } else {
                header('Location:listings.php');
            }
            exit;
        }
    }
    $error = 'Invalid email or password. Please try again.';
}
?>

<?php include 'includes/header.php'; ?>

<div class="form-container">
    <h1>Login</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="your@email.com" required>

        <label>Password</label>
        <input type="password" name="password" placeholder="Your password" required>

        <button type="submit">Login</button>
    </form>

    <p style="margin-top:16px;text-align:center;font-size:14px">
        Don't have an account? <a href="register.php" style="color:#003366;font-weight:bold">Register here</a>
    </p>
</div>

<?php include 'includes/footer.php'; ?>