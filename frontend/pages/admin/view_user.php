<?php
// ================================================================
// FILE: frontend/pages/admin/view_user.php
// ADMIN - VIEW USER DETAILS
// ================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// LOGIN PROTECTION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/login.php');
    exit;
}

// ROLE CHECK - ONLY ADMIN
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_GET['id'] ?? 0;
if ($user_id <= 0) {
    header('Location: users.php?error=invalid_id');
    exit;
}

require_once __DIR__ . '/../../../backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// GET USER DATA
$stmt = $db->prepare("
    SELECT 
        u.*,
        b.name as branch_name,
        b.location as branch_location,
        b.phone as branch_phone
    FROM users u
    LEFT JOIN branches b ON u.branch_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php?error=user_not_found');
    exit;
}

include_once '../../components/admin_header.php';
include_once '../../components/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View User - <?= htmlspecialchars($user['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .main-content { margin-left: 270px; padding: 28px 32px; margin-top: 68px; }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>

<main class="main-content">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-3xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">
                <i class="fas fa-user-circle text-blue-600"></i>
                User Details
            </h1>
            <a href="users.php" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Full Name</label>
                <p class="text-lg font-semibold"><?= htmlspecialchars($user['full_name']) ?></p>
            </div>
            
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Username</label>
                <p class="text-lg font-mono"><?= htmlspecialchars($user['username']) ?></p>
            </div>
            
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Email</label>
                <p class="text-sm"><?= htmlspecialchars($user['email']) ?></p>
            </div>
            
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Phone</label>
                <p class="text-sm"><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></p>
            </div>
            
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Role</label>
                <p class="text-sm">
                    <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold">
                        <?= strtoupper($user['role']) ?>
                    </span>
                </p>
            </div>
            
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Status</label>
                <p class="text-sm">
                    <span class="<?= $user['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> px-3 py-1 rounded-full text-xs font-semibold">
                        <?= strtoupper($user['status']) ?>
                    </span>
                </p>
            </div>
            
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Branch</label>
                <p class="text-sm"><?= htmlspecialchars($user['branch_name'] ?? 'N/A') ?></p>
            </div>
            
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Specialty</label>
                <p class="text-sm"><?= htmlspecialchars($user['specialty'] ?? 'N/A') ?></p>
            </div>
            
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Created At</label>
                <p class="text-sm"><?= date('F d, Y h:i A', strtotime($user['created_at'])) ?></p>
            </div>
            
            <div>
                <label class="text-xs text-gray-500 uppercase font-semibold">Last Updated</label>
                <p class="text-sm"><?= date('F d, Y h:i A', strtotime($user['updated_at'])) ?></p>
            </div>
        </div>

        <div class="mt-8 flex gap-3">
            <a href="edit_user.php?id=<?= $user['id'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                <i class="fas fa-edit"></i> Edit User
            </a>
            <a href="users.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                <i class="fas fa-list"></i> All Users
            </a>
        </div>
    </div>
</main>

</body>
</html>