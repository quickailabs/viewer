<?php

// Define path for the metadata JSON file
define('METADATA_FILE', 'metadata.json');

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
$fp = null; // Initialize file pointer

// Check if form was submitted and file exists
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['uploaded_file'])) {
    $file = $_FILES['uploaded_file'];

    // Check for basic upload errors first
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = true;
        // (Error messages for upload errors remain the same)
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
            // Validation passed, proceed with moving and metadata update
            $upload_dir = 'uploads/';
            // Generate a unique filename to prevent overwrites and hide original name on server
            $stored_filename = uniqid('', true) . '-' . $original_filename; // Add more entropy
            $target_path = $upload_dir . $stored_filename;

            // Attempt to move the uploaded file
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // File moved successfully, now update metadata.json

                // --- JSON Metadata Update Logic ---
                try {
                    // Open file for read/write, create if doesn't exist
                    $fp = fopen(METADATA_FILE, 'c+');
                    if (!$fp) {
                        throw new Exception("Could not open metadata file.");
                    }

                    // Acquire exclusive lock
                    if (!flock($fp, LOCK_EX)) {
                        throw new Exception("Could not lock metadata file.");
                    }

                    // Read existing data
                    $file_content = '';
                    $file_stat = fstat($fp);
                    if ($file_stat['size'] > 0) {
                         $file_content = fread($fp, $file_stat['size']);
                         if ($file_content === false) {
                             throw new Exception("Could not read metadata file.");
                         }
                    }
                    // Initialize metadata if file is empty or read failed
                    if (empty($file_content)) {
                        $metadata = [];
                    } else {
                        // Decode JSON
                        $metadata = json_decode($file_content, true); // Decode as associative array
                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($metadata)) {
                            // Handle potential corruption or invalid format
                             error_log("JSON decode error or invalid format in " . METADATA_FILE . ": " . json_last_error_msg());
                             throw new Exception("Metadata file format is invalid. Cannot proceed.");
                             // Alternatively, could attempt to overwrite with [] or backup/rename the corrupted file
                             // For now, we error out to prevent data loss.
                        }
                    }

                    // Generate unique ID
                    $new_id = uniqid();

                    // Prepare new entry (sanitize original filename just in case)
                    $new_entry = [
                        'id' => $new_id,
                        'original_filename' => htmlspecialchars($original_filename, ENT_QUOTES, 'UTF-8'),
                        'stored_filename' => $stored_filename,
                        'mime_type' => $mime_type,
                        'file_size' => $file_size,
                        'upload_timestamp' => time() // Use Unix timestamp
                    ];

                    // Append new entry
                    $metadata[] = $new_entry;

                    // Encode updated data
                    $json_data = json_encode($metadata, JSON_PRETTY_PRINT);
                    if ($json_data === false) {
                        throw new Exception("Could not encode metadata to JSON.");
                    }

                    // Write updated data back to file
                    if (ftruncate($fp, 0) === false) { // Truncate the file
                         throw new Exception("Could not truncate metadata file.");
                    }
                    if (fseek($fp, 0) === -1) { // Rewind pointer
                         throw new Exception("Could not seek in metadata file.");
                    }
                    if (fwrite($fp, $json_data) === false) {
                        throw new Exception("Could not write to metadata file.");
                    }
                    fflush($fp); // Ensure data is written before releasing lock

                    $message = "File uploaded successfully!";

                } catch (Exception $e) {
                    $error = true;
                    $message = "Error: Could not update metadata. " . $e->getMessage();
                    // Log the detailed error
                    error_log("Metadata Update Error: " . $e->getMessage());
                    // Clean up: Remove the uploaded file if metadata update fails
                    if (file_exists($target_path)) {
                        unlink($target_path);
                        error_log("Cleaned up file due to metadata error: " . $target_path);
                    }
                } finally {
                    // Always release lock and close file if open
                    if ($fp) {
                        flock($fp, LOCK_UN); // Release the lock
                        fclose($fp); // Close the file handle
                    }
                }
                // --- End JSON Metadata Update Logic ---

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

// No database connection to close anymore

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
