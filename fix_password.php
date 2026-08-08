<?php
// ================================================================
// fix_password.php - FIX PASSWORD ONCE AND FOR ALL
// ================================================================

// Direct database connection
$host = 'localhost';
$dbname = 'dispensary_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1 style='color:#0B5ED7;'>🔧 Fix Password Tool</h1>";
    
    // ================================================================
    // STEP 1: Check current password for reception.rose
    // ================================================================
    $stmt = $pdo->prepare("SELECT id, username, full_name, role, password FROM users WHERE username = 'reception.rose'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>📋 Current User Info:</h2>";
    echo "<pre style='background:#f0f0f0;padding:10px;border-radius:8px;'>";
    print_r($user);
    echo "</pre>";
    
    // ================================================================
    // STEP 2: Update password to '12345678' (plain text)
    // ================================================================
    $stmt = $pdo->prepare("UPDATE users SET password = '12345678' WHERE username = 'reception.rose'");
    $result = $stmt->execute();
    
    if ($result) {
        echo "<p style='color:green;font-size:20px;font-weight:bold;'>✅ Password updated to '12345678'!</p>";
    } else {
        echo "<p style='color:red;font-size:20px;'>❌ Update failed</p>";
    }
    
    // ================================================================
    // STEP 3: Verify update worked
    // ================================================================
    $stmt = $pdo->prepare("SELECT id, username, full_name, role, password FROM users WHERE username = 'reception.rose'");
    $stmt->execute();
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>✅ Updated User Info:</h2>";
    echo "<pre style='background:#e8f5e9;padding:10px;border-radius:8px;border:2px solid #4CAF50;'>";
    print_r($updated);
    echo "</pre>";
    
    // ================================================================
    // STEP 4: Update ALL reception users
    // ================================================================
    $stmt = $pdo->prepare("UPDATE users SET password = '12345678' WHERE role = 'reception' AND status = 'active'");
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "<p style='color:green;'>✅ Updated $count reception user(s) to '12345678'</p>";
    
    // ================================================================
    // STEP 5: Show all reception users
    // ================================================================
    $stmt = $pdo->prepare("SELECT id, username, full_name, role, password, status FROM users WHERE role = 'reception' AND status = 'active'");
    $stmt->execute();
    $reception_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>📋 All Reception Users:</h2>";
    echo "<table style='border-collapse:collapse;width:100%;font-size:14px;'>";
    echo "<tr style='background:#0B5ED7;color:white;'>";
    echo "<th style='padding:8px 12px;text-align:left;'>ID</th>";
    echo "<th style='padding:8px 12px;text-align:left;'>Username</th>";
    echo "<th style='padding:8px 12px;text-align:left;'>Full Name</th>";
    echo "<th style='padding:8px 12px;text-align:left;'>Password</th>";
    echo "<th style='padding:8px 12px;text-align:left;'>Status</th>";
    echo "</tr>";
    
    foreach ($reception_users as $u) {
        $color = ($u['password'] === '12345678') ? '#4CAF50' : '#f44336';
        $status = ($u['password'] === '12345678') ? '✅ OK' : '❌ FIX NEEDED';
        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:6px 12px;'>{$u['id']}</td>";
        echo "<td style='padding:6px 12px;font-weight:bold;'>{$u['username']}</td>";
        echo "<td style='padding:6px 12px;'>{$u['full_name']}</td>";
        echo "<td style='padding:6px 12px;color:$color;font-weight:bold;'>{$u['password']}</td>";
        echo "<td style='padding:6px 12px;'>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // ================================================================
    // STEP 6: Login button
    // ================================================================
    echo "<br>";
    echo "<div style='background:#e3f2fd;padding:20px;border-radius:12px;border:2px solid #0B5ED7;'>";
    echo "<h3 style='color:#0B5ED7;'>🔑 Now try logging in:</h3>";
    echo "<ul style='font-size:18px;'>";
    echo "<li><strong>Username:</strong> <code style='background:#1a1a2e;color:#fff;padding:2px 12px;border-radius:4px;'>reception.rose</code></li>";
    echo "<li><strong>Password:</strong> <code style='background:#1a1a2e;color:#4CAF50;padding:2px 12px;border-radius:4px;'>12345678</code></li>";
    echo "</ul>";
    echo "<a href='frontend/pages/login.php' style='display:inline-block;background:#0B5ED7;color:white;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:18px;'>";
    echo "🚀 Go to Login Page";
    echo "</a>";
    echo "</div>";
    
    // ================================================================
    // STEP 7: Check if login code is correct
    // ================================================================
    echo "<br><br>";
    echo "<div style='background:#fff3e0;padding:15px;border-radius:8px;border:1px solid #FF9800;'>";
    echo "<h4 style='color:#E65100;'>⚠️ If still not working, check:</h4>";
    echo "<ol>";
    echo "<li>Make sure <code>login.php</code> has the correct code</li>";
    echo "<li>Clear your browser cache (Ctrl+Shift+Delete)</li>";
    echo "<li>Try incognito/private window</li>";
    echo "<li>Check if <code>session_start()</code> is at the top of login.php</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Database error: " . $e->getMessage() . "</p>";
}
?>