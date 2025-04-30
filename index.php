<?php
// Define path for the metadata JSON file
define('METADATA_FILE', 'metadata.json');

$files_metadata = [];
$metadata_error_message = '';

// --- Step 3: Read and Decode JSON ---
if (file_exists(METADATA_FILE) && is_readable(METADATA_FILE)) {
    $json_content = file_get_contents(METADATA_FILE);

    if ($json_content === false) {
        $metadata_error_message = "Error: Could not read metadata file.";
        error_log("Failed to read metadata file: " . METADATA_FILE);
    } else {
        // Check if content is empty before decoding
        if (trim($json_content) === '') {
             $files_metadata = []; // Treat empty file as empty list
        } else {
            $decoded_data = json_decode($json_content, true); // Decode as associative array

            if (json_last_error() !== JSON_ERROR_NONE) {
                $metadata_error_message = "Error: Metadata file is corrupted or not valid JSON.";
                error_log("JSON decode error (" . json_last_error() . ") in " . METADATA_FILE . ": " . json_last_error_msg());
            } elseif (!is_array($decoded_data)) {
                $metadata_error_message = "Error: Metadata file format is invalid (expected JSON array).";
                error_log("Invalid metadata format in " . METADATA_FILE . ": Expected array, got " . gettype($decoded_data));
            } else {
                $files_metadata = $decoded_data;
                // Optional: Sort files by timestamp descending (if needed)
                usort($files_metadata, function($a, $b) {
                    return ($b['upload_timestamp'] ?? 0) <=> ($a['upload_timestamp'] ?? 0); // Sort descending, handle missing keys
                });
            }
        }
    }
} elseif (!file_exists(METADATA_FILE)) {
    // File doesn't exist, which is fine on first run, treat as empty list
    $files_metadata = [];
} else {
    // File exists but is not readable
    $metadata_error_message = "Error: Metadata file exists but is not readable.";
    error_log("Metadata file not readable: " . METADATA_FILE);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload and Viewer</title>
    <style>
        /* Basic body styling */
        body {
            font-family: sans-serif;
            margin: 20px; /* Add margin around the body */
        }
        /* Spacing for headings */
        h1, h2 {
            margin-bottom: 15px;
        }
        /* Spacing for the upload form */
        form {
            margin-bottom: 30px;
        }
        /* Table styling */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px; /* Keep existing margin-top */
        }
        /* Table cell and header styling */
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        /* Table header specific styling */
        th {
            background-color: #f2f2f2;
        }
        /* Styling for potential error messages */
        .error {
            color: red;
            font-weight: bold;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb; /* Match upload.php error style */
            background-color: #f8d7da; /* Match upload.php error style */
        }
        /* Basic styling for links used as buttons (e.g., View link) */
        td a {
            text-decoration: none;
            color: #007bff;
        }
        td a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>File Upload</h1>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <label for="file">Choose file to upload:</label>
        <input type="file" name="uploaded_file" id="file" required>
        <br><br>
        <input type="submit" value="Upload File">
    </form>

    <hr>

    <h2>Uploaded Files</h2>

    <?php if ($metadata_error_message): ?>
        <p class="error"><?php echo htmlspecialchars($metadata_error_message); ?></p>
    <?php endif; ?>

    <?php if (empty($files_metadata)): ?>
        <?php if (!$metadata_error_message): // Only show "No files" if there wasn't an error reading metadata ?>
            <p>No files uploaded yet.</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Uploaded Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files_metadata as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['original_filename'] ?? 'N/A'); // Use null coalesce for safety ?></td>
                        <td><?php echo isset($file['upload_timestamp']) ? date('Y-m-d H:i:s', $file['upload_timestamp']) : 'N/A'; // Format timestamp ?></td>
                        <td>
                            <?php if (isset($file['id'])): ?>
                                <a href="viewer.php?id=<?php echo urlencode($file['id']); ?>">View</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>
