Here's a basic PHP authentication system file structure:

```php
<?php
session_start();

// Configuration
$db_host = 'localhost';
$db_user = 'username';
$db_pass = 'password';
$db_name = 'database';

// Database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// User login function
function login($email, $password) {
    global $conn;
    
    // Prevent SQL injection
    $email = $conn->real_escape_string($email);
    
    $query = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = $conn->query($query);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['logged_in'] = true;
            return true;
        }
    }
    
    return false;
}

// User registration function
function register($email, $password, $name) {
    global $conn;
    
    // Prevent SQL injection
    $email = $conn->real_escape_string($email);
    $name = $conn->real_escape_string($name);
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users (email, password, name) VALUES ('$email', '$hashed_password', '$name')";
    
    if ($conn->query($query)) {
        return $conn->insert_id;
    }
    
    return false;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Logout function
function logout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    return true;
}
?>
```

This file includes essential authentication functions: login, registration, session checking, and logout.