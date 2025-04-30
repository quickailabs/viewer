<?php
require_once 'config.php';

$db_connection = null;
$files = [];
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
    $db_error_message = "Error: Could not connect to the database.";
} else {
    // Query Files
    $query = "SELECT id, original_filename, upload_timestamp FROM files ORDER BY upload_timestamp DESC";
    $result = pg_query($db_connection, $query);

    if (!$result) {
        $db_error_message = "Error: Could not retrieve file list from the database.";
    } else {
        // Fetch all results into an array
        while ($row = pg_fetch_assoc($result)) {
            $files[] = $row;
        }
    }
    // Close connection only after fetching data
    pg_close($db_connection);
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

    <?php if ($db_error_message): ?>
        <p class="error"><?php echo htmlspecialchars($db_error_message); ?></p>
    <?php elseif (empty($files)): ?>
        <p>No files uploaded yet.</p>
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
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['original_filename']); ?></td>
                        <td><?php echo htmlspecialchars($file['upload_timestamp']); ?></td>
                        <td>
                            <a href="viewer.php?id=<?php echo urlencode($file['id']); ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>
