<?php

require_once 'config.php';

// --- Security Enhancements: Configuration ---
// Define allowed MIME types (adjust as needed)
$allowed_mime_types = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'text/plain',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'application/vnd.ms-excel', // .xls
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' // .xlsx
];
// Define maximum file size (e.g., 10MB)
$max_file_size = 10 * 1024 * 1024; // 10 MB in bytes
// --------------------------------------------

$message = '';
$error = false;
$db_connection = null; // Initialize DB connection variable

// Check if form was submitted and file exists
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['uploaded_file'])) {
    $file = $_FILES['uploaded_file'];

    // Check for basic upload errors first
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = true;
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message = "Error: File is too large (server/form limit).";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "Error: File was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "Error: No file was uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Error: Missing temporary folder.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Error: Failed to write file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "Error: A PHP extension stopped the file upload.";
                break;
            default:
                $message = "Error: Unknown upload error.";
                break;
        }
    } else {
        // --- Security Enhancements: Validation ---
        $original_filename = basename($file['name']); // Use basename for basic security
        $mime_type = $file['type']; // Get MIME type reported by browser
        $file_size = $file['size'];

        // 1. Validate MIME Type
        if (!in_array($mime_type, $allowed_mime_types)) {
            $error = true;
            $message = "Error: Invalid file type. Allowed types: " . implode(', ', $allowed_mime_types);
        }
        // 2. Validate File Size
        elseif ($file_size > $max_file_size) {
            $error = true;
            $message = sprintf("Error: File is too large (max %d MB allowed).", $max_file_size / 1024 / 1024);
        }
        // -----------------------------------------
        else {
            // Validation passed, proceed with moving and DB insertion
            $upload_dir = 'uploads/';
            // Generate a unique filename to prevent overwrites and hide original name on server
            $stored_filename = uniqid('', true) . '-' . $original_filename; // Add more entropy with second param
            $target_path = $upload_dir . $stored_filename;

            // Attempt to move the uploaded file
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // File moved successfully, now connect to DB

                // Build connection string
                $conn_string = sprintf("host=%s port=%s dbname=%s user=%s password=%s",
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_USER,
                    DB_PASS
                );

                // Establish connection
                $db_connection = pg_connect($conn_string);

                if (!$db_connection) {
                    $error = true;
                    $message = "Error: Could not connect to the database.";
                    // Clean up: Remove the uploaded file if DB connection fails
                    if (file_exists($target_path)) unlink($target_path);
                } else {
                    // --- Security Enhancement: Parameterized Query & Sanitization ---
                    // Sanitize original filename before DB insertion
                    $sanitized_original_filename = htmlspecialchars($original_filename, ENT_QUOTES, 'UTF-8');

                    // Construct parameterized SQL query
                    $query = "INSERT INTO files (original_filename, stored_filename, mime_type, file_size) VALUES ($1, $2, $3, $4)";
                    $params = [
                        $sanitized_original_filename, // Use sanitized version
                        $stored_filename,
                        $mime_type,
                        $file_size // Integer, safe to pass directly
                    ];

                    // Execute parameterized query
                    $result = pg_query_params($db_connection, $query, $params);
                    // -------------------------------------------------------------

                    if (!$result) {
                        $error = true;
                        // Log the detailed error instead of showing it to the user
                        error_log("Database Error: " . pg_last_error($db_connection));
                        $message = "Error: Could not save file metadata to the database.";
                        // Clean up: Remove the uploaded file if DB insertion fails
                        if (file_exists($target_path)) unlink($target_path);
                    } else {
                        $message = "File uploaded successfully!";
                    }

                    // Close the database connection
                    pg_close($db_connection);
                }
            } else {
                $error = true;
                $message = "Error: Failed to move uploaded file. Check permissions or path.";
                // Log potential issue
                error_log("Failed to move uploaded file from " . $file['tmp_name'] . " to " . $target_path);
            }
        }
    }
} else {
    // Handle cases where the script is accessed directly or form is incomplete
    $error = true;
    $message = "Invalid request or no file selected.";
}

// Ensure connection is closed if it was opened and an error occurred before pg_close was reached
if ($db_connection) {
    @pg_close($db_connection); // Use @ to suppress errors if connection already closed
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Status</title>
    <style>
        body { font-family: sans-serif; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <h1>Upload Status</h1>
    <div class="message <?php echo $error ? 'error' : 'success'; ?>">
        <?php echo htmlspecialchars($message); // Escape output for security ?>
    </div>
    <p><a href="index.php">Upload another file</a></p>
</body>
</html>
