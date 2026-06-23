<?php
// initiate_payment.php
require_once 'includes/db.php';

header('Content-Type: application/json');

// Ensure only logged-in buyers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Read incoming JSON body data from the JavaScript checkout request
$input = json_decode(file_get_contents('php://input'), true);
$listing_id = isset($input['listing_id']) ? (int)$input['listing_id'] : 0;
$seller_id  = isset($input['seller_id'])  ? (int)$input['seller_id'] : 0;
$amount     = isset($input['amount'])     ? (float)$input['amount']   : 0.00;
$buyer_id   = $_SESSION['user_id'];

if (!$listing_id || !$seller_id || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid transaction metrics.']);
    exit;
}

// 1. Store a tracking entry directly matching your table schema columns
$stmt = $conn->prepare(
    "INSERT INTO payments (listing_id, buyer_id, seller_id, amount, status, method, created_at) 
     VALUES (?, ?, ?, ?, 'pending', 'escrow', NOW())"
);
$stmt->bind_param('iiid', $listing_id, $buyer_id, $seller_id, $amount);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database failure recording payment row.']);
    exit;
}

$payment_id = $conn->insert_id;

// 2. Compile PayGate redirection parameters
$paygateUrl = "https://www.paygate.co.za/payweb3/process.trans";

// Detect if running on HTTP or HTTPS dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . '/streettrader';

$paygateData = [
    'PAYGATE_ID'       => '10011072130', // Default Sandbox testing ID
    'REFERENCE'        => "ST-" . $payment_id,
    'AMOUNT'           => (int)($amount * 100), // Converted to cents natively
    'CURRENCY'         => 'ZAR',
    'RETURN_URL'       => $baseUrl . "/payment_callback.php?payment_id=" . $payment_id,
    'TRANSACTION_DATE' => date('Y-m-d H:i:s'),
    'LOCALE'           => 'en-za',
    'COUNTRY'          => 'ZA'
];

// 3. Security Layer: Calculate MD5 Checksum Signature
// (Uses the default PayGate Sandbox secret encryption key: "ns7b5K9f")
$encryptionKey = "ns7b5K9f"; 
$checksumSource = "";
foreach ($paygateData as $key => $value) {
    $checksumSource .= $value;
}
$checksumSource .= $encryptionKey;
$paygateData['CHECKSUM'] = md5($checksumSource);

// Return data package back to checkout.php
echo json_encode([
    'success'      => true,
    'redirect_url' => $paygateUrl,
    'data'         => $paygateData
]);
exit;