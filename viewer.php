<?php

require_once 'config.php';

// --- Step 2: Get File ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400); // Bad Request
    die("Error: Invalid or missing file ID.");
}
$file_id = (int)$_GET['id']; // Cast to integer

// --- Step 3: Database Connection & Query ---
$db_connection = null;
$file_data = null;
$db_error_message = '';

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
    http_response_code(500); // Internal Server Error
    die("Error: Could not connect to the database.");
}

// Prepare and execute parameterized query
$query = "SELECT original_filename, stored_filename, mime_type FROM files WHERE id = $1";
$result = pg_query_params($db_connection, $query, array($file_id));

if (!$result) {
    pg_close($db_connection);
    http_response_code(500); // Internal Server Error
    die("Error: Could not query the database.");
}

// --- Step 4: Check File Existence (Database) ---
if (pg_num_rows($result) === 0) {
    pg_close($db_connection);
    http_response_code(404); // Not Found
    die("Error: File not found in database.");
}

$file_data = pg_fetch_assoc($result);
pg_close($db_connection); // Close connection after fetching

// --- Step 5: Construct File Path & Check Existence (Filesystem) ---
$upload_dir = 'uploads/';
// --- Security Enhancement: Apply basename() to stored filename ---
$safe_stored_filename = basename($file_data['stored_filename']);
$file_path = $upload_dir . $safe_stored_filename;
// -----------------------------------------------------------------

if (!file_exists($file_path) || !is_readable($file_path)) {
    http_response_code(404); // Not Found
    error_log("File not found or not readable on server: " . $file_path); // Log for debugging
    die("Error: File not found on server or cannot be accessed.");
}

// --- Step 7: Refinement (Download Logic) ---
if (isset($_GET['download'])) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_data['original_filename']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    flush(); // Flush system output buffer
    readfile($file_path);
    exit;
}

// --- Step 6: Determine Action Based on MIME Type ---
$mime_type = $file_data['mime_type'];

// Check if it's an image type
if (strpos($mime_type, 'image/') === 0) {
    // Output image directly
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . basename($file_data['original_filename']) . '"'); // Suggest inline display
    readfile($file_path);
    exit;
}

// Check if it's a PDF
if ($mime_type === 'application/pdf') {
    // Output PDF directly
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file_data['original_filename']) . '"'); // Suggest inline display
    readfile($file_path);
    exit;
}

// --- Step 8: HTML Structure for Non-Direct Output (Default Case) ---
// If not an image, not PDF, and not a download request, show a page with a download link.
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
    <a href="viewer.php?id=<?php echo $file_id; ?>&download=1" class="download-link">Download File</a>
    <br><br>
    <p><a href="index.php">Back to Uploads</a></p>
</body>
</html>
