<?php
// convert_password.php

// Replace this with your password
$password = "admin53910abcd";

// Create a secure hash (uses bcrypt/argon depending on your PHP)
$hash = password_hash($password, PASSWORD_DEFAULT);

// Output
echo "Plain Password: " . $password . "<br>";
echo "Hashed Password: " . $hash . "<br>";
