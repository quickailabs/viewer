<?php

// Define path for the metadata JSON file
define('METADATA_FILE', 'metadata.json');

// --- Step 3: Validate Input ID ---
if (!isset($_GET['id'])) {
    http_response_code(400); // Bad Request
    die("Error: Missing file ID.");
}
$file_id = $_GET['id']; // Get the ID (can be string like uniqid generates)

// --- Step 4: Read and Decode JSON ---
$metadata = [];
$metadata_error_message = '';

if (file_exists(METADATA_FILE) && is_readable(METADATA_FILE)) {
    $json_content = file_get_contents(METADATA_FILE);
    if ($json_content === false) {
        $metadata_error_message = "Error: Could not read metadata file.";
        error_log("Failed to read metadata file: " . METADATA_FILE);
    } else {
        if (trim($json_content) === '') {
             $metadata = []; // Treat empty file as empty list
        } else {
            $decoded_data = json_decode($json_content, true); // Decode as associative array
            if (json_last_error() !== JSON_ERROR_NONE) {
                $metadata_error_message = "Error: Metadata file is corrupted or not valid JSON.";
                error_log("JSON decode error (" . json_last_error() . ") in " . METADATA_FILE . ": " . json_last_error_msg());
            } elseif (!is_array($decoded_data)) {
                $metadata_error_message = "Error: Metadata file format is invalid (expected JSON array).";
                error_log("Invalid metadata format in " . METADATA_FILE . ": Expected array, got " . gettype($decoded_data));
            } else {
                $metadata = $decoded_data;
            }
        }
    }
} elseif (!file_exists(METADATA_FILE)) {
    $metadata_error_message = "Error: Metadata file not found.";
    error_log("Metadata file does not exist: " . METADATA_FILE);
} else {
    $metadata_error_message = "Error: Metadata file exists but is not readable.";
    error_log("Metadata file not readable: " . METADATA_FILE);
}

// If there was an error reading or parsing metadata, stop here
if ($metadata_error_message) {
    http_response_code(500); // Internal Server Error
    die(htmlspecialchars($metadata_error_message));
}

// --- Step 5: Find File Metadata by ID ---
$file_data = null; // Initialize before loop
foreach ($metadata as $item) {
    // Ensure the item has an 'id' key before comparing
    if (isset($item['id']) && $item['id'] === $file_id) {
        $file_data = $item;
        break; // Found the file, exit loop
    }
}

// --- Step 6: Check File Found ---
if ($file_data === null) {
    http_response_code(404); // Not Found
    die("Error: File not found."); // User-friendly message
}

// --- Step 7 & 8: Use Retrieved Metadata & Keep Existing Logic ---

// Validate necessary keys exist in $file_data before proceeding
if (!isset($file_data['stored_filename']) || !isset($file_data['original_filename']) || !isset($file_data['mime_type'])) {
     http_response_code(500); // Internal Server Error
     error_log("Incomplete metadata for ID " . $file_id . " in " . METADATA_FILE);
     die("Error: Incomplete file metadata found.");
}

// Construct File Path & Check Existence (Filesystem)
$upload_dir = 'uploads/';
// Apply basename() to stored filename for security
$safe_stored_filename = basename($file_data['stored_filename']);
$file_path = $upload_dir . $safe_stored_filename;

if (!file_exists($file_path) || !is_readable($file_path)) {
    http_response_code(404); // Not Found
    error_log("File not found or not readable on server: " . $file_path . " (Metadata ID: " . $file_id . ")");
    die("Error: File not found on server or cannot be accessed.");
}

// Download Logic
if (isset($_GET['download'])) {
    // Ensure file_size is available for Content-Length, provide default if not
    $file_size = isset($file_data['file_size']) ? $file_data['file_size'] : filesize($file_path);

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    // Use basename() on original_filename just in case
    header('Content-Disposition: attachment; filename="' . basename($file_data['original_filename']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $file_size);
    flush(); // Flush system output buffer
    readfile($file_path);
    exit;
}

// Determine Action Based on MIME Type
$mime_type = $file_data['mime_type'];

// Check if it's an image type
if (strpos($mime_type, 'image/') === 0) {
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . basename($file_data['original_filename']) . '"');
    readfile($file_path);
    exit;
}

// Check if it's a PDF
if ($mime_type === 'application/pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file_data['original_filename']) . '"');
    readfile($file_path);
    exit;
}

// HTML Structure for Non-Direct Output (Default Case)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Viewer - <?php echo htmlspecialchars($file_data['original_filename']); ?></title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .file-info { margin-bottom: 20px; }
        .download-link { display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .download-link:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <h1>File Viewer</h1>
    <div class="file-info">
        <p><strong>Filename:</strong> <?php echo htmlspecialchars($file_data['original_filename']); ?></p>
        <p><strong>MIME Type:</strong> <?php echo htmlspecialchars($mime_type); ?></p>
        <p>Preview is not available for this file type.</p>
    </div>
    <a href="viewer.php?id=<?php echo urlencode($file_id); ?>&download=1" class="download-link">Download File</a>
    <br><br>
    <p><a href="index.php">Back to Uploads</a></p>
</body>
</html>
