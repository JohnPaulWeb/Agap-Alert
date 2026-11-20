<?php
require_once __DIR__ . '/config/db.php';
$db = db();

$password = 'admin123';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Promote ID 1 (rexyllsantiago1@gmail.com) to admin and set a password
$stmt = $db->prepare(
    "UPDATE users SET role = 'admin', status = 'active', password_hash = ? WHERE id = 1"
);
$stmt->execute([$hash]);

echo "✓ Done! Rows updated: " . $stmt->rowCount() . "\n\n";
echo "You can now sign in with:\n";
echo "Email:    rexyllsantiago1\@gmail.com\n";
echo "Password: admin123\n\n";
echo "Or just click Continue with Google.\n\n";
echo "DELETE THIS FILE after logging in!";
