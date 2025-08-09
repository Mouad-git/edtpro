<?php
session_start();



if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit;
}
?>