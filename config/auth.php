<?php
session_start();

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user has specific role
function has_role($role) {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === $role;
}

// Check if user is admin
function is_admin() {
    return has_role('admin');
}

// Check if user is landlord
function is_landlord() {
    return has_role('landlord');
}

// Check if user is caretaker
function is_caretaker() {
    return has_role('caretaker');
}

// Check if user is customer
function is_customer() {
    return has_role('customer');
}

// Redirect if not logged in
function require_login($redirect = '../login.php') {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}

// Redirect if not admin
function require_admin() {
    if (!is_admin()) {
        header('Location: ../login.php');
        exit;
    }
}

// Redirect if not landlord
function require_landlord() {
    if (!is_landlord()) {
        header('Location: ../login.php');
        exit;
    }
}

// Redirect if not caretaker
function require_caretaker() {
    if (!is_caretaker()) {
        header('Location: ../login.php');
        exit;
    }
}

// Redirect if not customer
function require_customer() {
    if (!is_customer()) {
        header('Location: ../login.php');
        exit;
    }
}

// Logout function
function logout() {
    session_destroy();
    header('Location: ./login.php');
    exit;
}
?>
