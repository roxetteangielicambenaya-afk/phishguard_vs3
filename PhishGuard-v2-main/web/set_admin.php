<?php
require_once '../db.php';

// Ilagay dito ang email ng admin na gusto mong i-reset
$admin_email = 'roxetteangielicambenaya@gmail.com'; 

// Ilagay dito ang bagong password
$new_password = 'admin#123';

// Gumawa ng hash
$hash = password_hash($new_password, PASSWORD_DEFAULT);

// I-update ang database
$stmt = $pdo->prepare("UPDATE users SET password = :hash WHERE email = :email");
$stmt->execute([
    ':hash'  => $hash,
    ':email' => $admin_email
]);

if ($stmt->rowCount() > 0) {
    echo "✅ Success! Password for <strong>$admin_email</strong> has been reset to: <strong>$new_password</strong>";
} else {
    echo "❌ Error: Email not found or password is the same.";
}
?>