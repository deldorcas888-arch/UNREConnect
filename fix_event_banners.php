<?php
// Include DB connection
require_once __DIR__ . '/admin/db_connect.php';

// Set paths
$upload_dir = __DIR__ . '/admin/assets/uploads/events/';
$base_url = '/UNREConnect/admin/assets/uploads/events/';

// Ensure upload directory exists
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        die("Failed to create directory: $upload_dir");
    }
    echo "Created directory: $upload_dir<br>";
}

// Get all events with banners
$query = "SELECT events_id, title, banner FROM events WHERE banner IS NOT NULL AND banner != ''";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo "<h2>Fixing Event Banners</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>Old Path</th><th>Status</th></tr>";
    
    while ($event = $result->fetch_assoc()) {
        $old_path = $event['banner'];
        $banner_file = basename($old_path);
        $new_path = 'events/' . $banner_file;
        $full_old_path = '';
        $status = '';
        
        // Try to find the file in various locations
        $possible_paths = [
            __DIR__ . '/admin/' . $old_path,
            __DIR__ . '/admin/uploads/events/' . $banner_file,
            __DIR__ . '/uploads/events/' . $banner_file,
            __DIR__ . '/' . $old_path,
            $old_path
        ];
        
        // Find the first valid path
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $full_old_path = $path;
                break;
            }
        }
        
        // If we found the file, copy it to the new location
        if (!empty($full_old_path)) {
            $target_path = $upload_dir . $banner_file;
            
            // Only copy if the target doesn't exist or is different
            if (!file_exists($target_path) || (md5_file($full_old_path) !== md5_file($target_path))) {
                if (copy($full_old_path, $target_path)) {
                    $status = "Copied to $target_path";
                } else {
                    $status = "Failed to copy to $target_path";
                }
            } else {
                $status = "Already exists in target location";
            }
            
            // Update the database with the new path
            $update_sql = "UPDATE events SET banner = ? WHERE events_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('si', $new_path, $event['events_id']);
            
            if ($update_stmt->execute()) {
                $status .= " | Database updated";
            } else {
                $status .= " | Database update failed: " . $conn->error;
            }
            
            $update_stmt->close();
        } else {
            $status = "File not found in any location";
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($event['events_id']) . "</td>";
        echo "<td>" . htmlspecialchars($event['title']) . "</td>";
        echo "<td>" . htmlspecialchars($old_path) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "No events with banners found.";
}

$conn->close();
?>

<h3>Current Directory Contents</h3>
<pre>
<?php
// List files in the upload directory
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    echo "Files in $upload_dir:\n";
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "- $file\n";
        }
    }
} else {
    echo "Directory not found: $upload_dir";
}
?>
</pre>
