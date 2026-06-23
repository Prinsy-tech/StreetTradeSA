<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php'); exit;
}

$user_id    = $_SESSION['user_id'];
$listing_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error      = '';
$success    = '';

if (!$listing_id) {
    header('Location: dashboard.php'); exit;
}

// Get listing — make sure it belongs to this seller
$stmt = $conn->prepare('SELECT * FROM listings WHERE id=? AND seller_id=?');
$stmt->bind_param('ii', $listing_id, $user_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();

if (!$listing) {
    header('Location: dashboard.php'); exit;
}

// Get categories
$cats = $conn->query('SELECT * FROM categories ORDER BY name');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price       = (float)$_POST['price'];
    $category_id = (int)$_POST['category_id'];
    $type        = $_POST['listing_type'];
    $location    = trim($_POST['location']);
    $status      = $_POST['status'];
    $image_name  = $listing['image'];

    if (empty($title) || empty($description) || empty($price) || empty($type)) {
        $error = 'Please fill in all required fields.';
    } else {

        // Handle new image upload
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg','jpeg','png','gif','webp'];
            $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = 'Only JPG, PNG, GIF and WEBP images are allowed.';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $error = 'Image must be under 5MB.';
            } else {
                $image_name = uniqid('listing_') . '.' . $ext;
                $upload_dir = __DIR__ . '/assets/uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
            }
        }

        if (!$error) {
            $stmt = $conn->prepare(
                'UPDATE listings SET title=?, description=?, price=?, category_id=?,
                 listing_type=?, image=?, location=?, status=?
                 WHERE id=? AND seller_id=?'
            );
            $stmt->bind_param('ssdissssii', $title, $description, $price, $category_id,
                              $type, $image_name, $location, $status, $listing_id, $user_id);
            if ($stmt->execute()) {
                $success = 'Listing updated successfully!';
                // Refresh listing data
                $stmt2 = $conn->prepare('SELECT * FROM listings WHERE id=?');
                $stmt2->bind_param('i', $listing_id);
                $stmt2->execute();
                $listing = $stmt2->get_result()->fetch_assoc();
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div style="max-width:700px;margin:30px auto;padding:0 20px">
    <h1 style="color:#003366;margin-bottom:6px;font-size:28px">✏️ Edit Listing</h1>
    <p style="color:#666;margin-bottom:24px;font-size:15px">Update your listing details below</p>

    <?php if ($error): ?>
        <div style="background:#fdecea;color:#cc0000;border:1px solid #f5c6c2;
                    border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:14px">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background:#e8f5ee;color:#1a7a4a;border:1px solid #c3e6c3;
                    border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:14px">
            <?= $success ?>
            <a href="dashboard.php" style="color:#003366;font-weight:bold;margin-left:8px">
                Go to Dashboard
            </a>
        </div>
    <?php endif; ?>

    <div style="background:white;border:1px solid #ddd;border-radius:12px;padding:30px">
        <form method="POST" enctype="multipart/form-data">

            <!-- LISTING TYPE -->
            <p style="font-weight:bold;color:#003366;margin-bottom:10px;font-size:14px">
                Listing Type *
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
                <div id="productCard" onclick="selectType('product')"
                     style="border:2px solid <?= $listing['listing_type'] === 'product' ? '#003366' : '#ddd' ?>;
                            background:<?= $listing['listing_type'] === 'product' ? '#e8f0fb' : 'white' ?>;
                            border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:all 0.2s">
                    <span style="font-size:28px;display:block;margin-bottom:6px">📦</span>
                    <div style="font-weight:bold;color:#003366;font-size:14px">Product</div>
                </div>
                <div id="serviceCard" onclick="selectType('service')"
                     style="border:2px solid <?= $listing['listing_type'] === 'service' ? '#003366' : '#ddd' ?>;
                            background:<?= $listing['listing_type'] === 'service' ? '#e8f0fb' : 'white' ?>;
                            border-radius:10px;padding:16px;text-align:center;cursor:pointer;transition:all 0.2s">
                    <span style="font-size:28px;display:block;margin-bottom:6px">🛠️</span>
                    <div style="font-weight:bold;color:#003366;font-size:14px">Service</div>
                </div>
            </div>
            <input type="hidden" name="listing_type" id="typeInput"
                   value="<?= $listing['listing_type'] ?>">

            <!-- TITLE -->
            <label style="display:block;font-size:14px;font-weight:bold;color:#003366;margin-bottom:4px">
                Title *
            </label>
            <input type="text" name="title"
                   value="<?= htmlspecialchars($listing['title']) ?>"
                   required maxlength="150"
                   style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                          font-size:14px;margin-bottom:16px;box-sizing:border-box">

            <!-- DESCRIPTION -->
            <label style="display:block;font-size:14px;font-weight:bold;color:#003366;margin-bottom:4px">
                Description *
            </label>
            <textarea name="description" required rows="5"
                      style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                             font-size:14px;margin-bottom:16px;box-sizing:border-box;resize:vertical">
<?= htmlspecialchars($listing['description']) ?>
            </textarea>

            <!-- PRICE -->
            <label style="display:block;font-size:14px;font-weight:bold;color:#003366;margin-bottom:4px">
                Price (R) *
            </label>
            <input type="number" name="price"
                   value="<?= $listing['price'] ?>"
                   required min="0" step="0.01"
                   style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                          font-size:14px;margin-bottom:16px;box-sizing:border-box">

            <!-- CATEGORY -->
            <label style="display:block;font-size:14px;font-weight:bold;color:#003366;margin-bottom:4px">
                Category
            </label>
            <select name="category_id"
                    style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                           font-size:14px;margin-bottom:16px;box-sizing:border-box;background:white">
                <option value="">Select a category</option>
                <?php
                $cats->data_seek(0);
                while ($cat = $cats->fetch_assoc()):
                ?>
                <option value="<?= $cat['id'] ?>"
                    <?= $listing['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
                <?php endwhile; ?>
            </select>

            <!-- LOCATION -->
            <label style="display:block;font-size:14px;font-weight:bold;color:#003366;margin-bottom:4px">
                Location
            </label>
            <input type="text" name="location"
                   value="<?= htmlspecialchars($listing['location'] ?? '') ?>"
                   placeholder="e.g. Soweto, Johannesburg"
                   style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                          font-size:14px;margin-bottom:16px;box-sizing:border-box">

            <!-- STATUS -->
            <label style="display:block;font-size:14px;font-weight:bold;color:#003366;margin-bottom:4px">
                Status
            </label>
            <select name="status"
                    style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                           font-size:14px;margin-bottom:16px;box-sizing:border-box;background:white">
                <option value="active" <?= $listing['status'] === 'active' ? 'selected' : '' ?>>
                    Active
                </option>
                <option value="inactive" <?= $listing['status'] === 'inactive' ? 'selected' : '' ?>>
                    Inactive
                </option>
            </select>

            <!-- CURRENT IMAGE -->
            <?php if ($listing['image']): ?>
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:14px;font-weight:bold;
                              color:#003366;margin-bottom:8px">Current Image</label>
                <img src="/assets/uploads/<?= htmlspecialchars($listing['image']) ?>"
                     style="width:120px;height:120px;object-fit:cover;border-radius:8px;
                            border:1px solid #ddd">
            </div>
            <?php endif; ?>

            <!-- NEW IMAGE -->
            <label style="display:block;font-size:14px;font-weight:bold;color:#003366;margin-bottom:4px">
                <?= $listing['image'] ? 'Replace Image (optional)' : 'Image (optional)' ?>
            </label>
            <input type="file" name="image" accept="image/*"
                   style="width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;
                          font-size:14px;margin-bottom:24px;box-sizing:border-box;background:white">

            <div style="display:flex;gap:12px">
                <button type="submit"
                        style="flex:1;padding:14px;background:#003366;color:white;border:none;
                               border-radius:8px;cursor:pointer;font-weight:bold;font-size:16px;margin:0"
                        onmouseover="this.style.background='#002244'"
                        onmouseout="this.style.background='#003366'">
                    Save Changes
                </button>
                <a href="dashboard.php"
                   style="flex:1;padding:14px;background:#f5f5f5;color:#003366;border:1px solid #ddd;
                          border-radius:8px;font-weight:bold;font-size:16px;text-decoration:none;
                          text-align:center">
                    Cancel
                </a>
            </div>

        </form>
    </div>
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
        document.getElementById('serviceCard').style.border     = '2px solid #003366';
        document.getElementById('serviceCard').style.background = '#e8f0fb';
    }
    document.getElementById('typeInput').value = type;
}
</script>

<?php include 'includes/footer.php'; ?>