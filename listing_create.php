<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

if ($_SESSION['role'] !== 'seller') {
    header('Location: listings.php'); exit;
}

$error   = '';
$user_id = (int)$_SESSION['user_id'];

// Get user details
$stmt = $conn->prepare('SELECT * FROM users WHERE id=?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: login.php'); exit;
}

// Get categories for dropdown
$cats = $conn->query('SELECT * FROM categories ORDER BY name');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title']);
    $description  = trim($_POST['description']);
    $price        = (float)$_POST['price'];
    $category_id  = (int)$_POST['category_id'];
    $listing_type = $_POST['listing_type'];
    $location     = trim($_POST['location']);
    $image_name   = '';

    if (empty($title) || empty($description) || $price <= 0 || empty($listing_type)) {
        $error = 'Please fill in all required fields and select a listing type.';
    } else {
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = 'Only jpg, png and webp images are allowed.';
            } elseif ($_FILES['image']['size'] > 2097152) {
                $error = 'Image must be under 2MB.';
            } else {
                $image_name = uniqid('listing_') . '.' . $ext;
                move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    'assets/uploads/' . $image_name
                );
            }
        }

        if (!$error) {
            $stmt = $conn->prepare(
                'INSERT INTO listings
                 (seller_id, category_id, title, description, price, listing_type, image, location)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iissdsss',
                $user_id,
                $category_id,
                $title,
                $description,
                $price,
                $listing_type,
                $image_name,
                $location
            );

            if ($stmt->execute()) {
                header('Location: dashboard.php?success=1');
                exit;
            } else {
                $error = 'Database error: ' . $conn->error;
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div style="background:#003366;padding:30px 40px;color:white">
    <div style="max-width:700px;margin:0 auto">
        <a href="dashboard.php"
           style="color:rgba(255,255,255,0.7);text-decoration:none;font-size:14px">
            ← Back to Dashboard
        </a>
        <h1 style="font-size:26px;margin-top:8px">Post a New Listing</h1>
        <p style="opacity:0.8;font-size:14px">
            Fill in the details below to list your product or service
        </p>
    </div>
</div>

<div style="max-width:700px;margin:30px auto;padding:0 20px 40px">

    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data"
          style="background:white;border:1px solid #ddd;border-radius:12px;padding:32px">

        <!-- LISTING TYPE -->
        <p style="font-weight:bold;color:#003366;margin-bottom:10px;font-size:14px">
            I am listing a *
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
            <div id="productCard" onclick="selectType('product')"
                 style="border:2px solid #ddd;border-radius:10px;padding:18px;
                        text-align:center;cursor:pointer;transition:all 0.2s">
                <span style="font-size:32px;display:block;margin-bottom:8px">📦</span>
                <div style="font-weight:bold;color:#003366">Product</div>
                <div style="font-size:12px;color:#666;margin-top:4px">A physical item to sell</div>
            </div>
            <div id="serviceCard" onclick="selectType('service')"
                 style="border:2px solid #ddd;border-radius:10px;padding:18px;
                        text-align:center;cursor:pointer;transition:all 0.2s">
                <span style="font-size:32px;display:block;margin-bottom:8px">🛠️</span>
                <div style="font-weight:bold;color:#003366">Service</div>
                <div style="font-size:12px;color:#666;margin-top:4px">A service you offer</div>
            </div>
        </div>
        <input type="hidden" name="listing_type" id="listingTypeInput" value="">

        <!-- TITLE -->
        <label>Listing Title *</label>
        <input type="text" name="title"
               placeholder="e.g. Fresh vetkoek, Hair braiding, Clothing alterations"
               required>

        <!-- DESCRIPTION -->
        <label>Description *</label>
        <textarea name="description" rows="5"
                  placeholder="Describe your product or service in detail..."
                  required style="resize:vertical"></textarea>

        <!-- PRICE -->
        <label>Price (R) *</label>
        <input type="number" name="price" step="0.01" min="0.01"
               placeholder="e.g. 150.00" required>

        <!-- CATEGORY -->
        <label>Category</label>
        <select name="category_id">
            <option value="0">-- Select a category --</option>
            <?php while ($cat = $cats->fetch_assoc()): ?>
            <option value="<?= $cat['id'] ?>">
                <?= htmlspecialchars($cat['name']) ?>
            </option>
            <?php endwhile; ?>
        </select>

        <!-- LOCATION -->
        <label>Location</label>
        <input type="text" name="location"
               placeholder="e.g. Soweto, Johannesburg"
               value="<?= htmlspecialchars($user['location'] ?? '') ?>">

        <!-- IMAGE -->
        <label>Upload Image (optional — max 2MB)</label>
        <input type="file" name="image" accept="image/*"
               onchange="previewImage(this)">
        <div id="imagePreview" style="margin-top:10px;display:none">
            <img id="previewImg"
                 style="max-width:100%;max-height:200px;border-radius:8px;
                        border:1px solid #ddd;object-fit:cover">
        </div>

        <button type="submit" style="margin-top:24px;width:100%;padding:14px;font-size:16px">
            Post Listing
        </button>

    </form>
</div>

<script>
function selectType(type) {
    document.getElementById('productCard').style.border     = '2px solid #ddd';
    document.getElementById('productCard').style.background = 'white';
    document.getElementById('serviceCard').style.border     = '2px solid #ddd';
    document.getElementById('serviceCard').style.background = 'white';

    if (type === 'product') {
        document.getElementById('productCard').style.border     = '2px solid #003366';
        document.getElementById('productCard').style.background = '#e8f0fb';
    } else {
        document.getElementById('serviceCard').style.border     = '2px solid #cc0000';
        document.getElementById('serviceCard').style.background = '#fff0f0';
    }
    document.getElementById('listingTypeInput').value = type;
}

function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const img     = document.getElementById('previewImg');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include 'includes/footer.php'; ?>