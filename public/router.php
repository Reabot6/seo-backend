<?php
// Router for PHP built-in server
// This file handles routing for the development server since it doesn't support .htaccess

$requested_file = __DIR__ . $_SERVER['REQUEST_URI'];

// If the requested file/directory exists and is not a directory, serve it
if (file_exists($requested_file) && !is_dir($requested_file)) {
    return false; // Let the server serve the file
}

// Otherwise, route to index.php
require __DIR__ . '/index.php';