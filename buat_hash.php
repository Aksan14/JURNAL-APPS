<?php
// File: buat_hash.php

// Ganti 'admin' di bawah ini jika Anda ingin password lain
$password_saya = 'password'; 

$hash = password_hash($password_saya, PASSWORD_DEFAULT);

echo "Password Anda adalah: " . $password_saya;
echo "<br><br>";
echo "Copy dan paste HASH di bawah ini ke phpMyAdmin:";
echo "<br><br>";
echo $hash;
?>