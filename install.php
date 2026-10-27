<?php
if (file_exists(__DIR__ . '/installed.lock')) {
    http_response_code(403);
    die('Installation is already complete.');
}
 //system installation Script to be ran to check system requirements and database connection.

$phpVersion = phpversion();
$minPhpVersion = '7.4.0';

echo "<h1>Open Registry Database - Installation Check</h1>\n";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
</style>\n";

echo "<div class='section'>\n";
echo "<h2>System Requirements Check</h2>\n";

// PHP Version Check
if (version_compare($phpVersion, $minPhpVersion, '>=')) {
    echo "<p class='success'>✓ PHP Version: $phpVersion (Required: $minPhpVersion)</p>\n";
} else {
    echo "<p class='error'>✗ PHP Version: $phpVersion (Required: $minPhpVersion)</p>\n";
}

// PHP Extensions Check
$requiredExtensions = ['pdo_pgsql', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p class='success'>✓ Extension: $ext</p>\n";
    } else {
        echo "<p class='error'>✗ Extension: $ext (Required)</p>\n";
    }
}

// Composer Check
if (file_exists('vendor/autoload.php')) {
    echo "<p class='success'>✓ Composer dependencies installed</p>\n";
} else {
    echo "<p class='warning'>⚠ Composer dependencies not found. Run 'composer install' to install PhpSpreadsheet.</p>\n";
}

echo "</div>\n";

// Database Connection Test
echo "<div class='section'>\n";
echo "<h2>Database Connection Test</h2>\n";

if (file_exists('includes/db.php')) {
    echo "<p class='info'>Database configuration file found.</p>\n";
    
    // Try to include and test connection
    try {
        include 'includes/db.php';
        
        if (isset($conn) && $conn instanceof PgConnection) {
            if ($conn->ping()) {
                echo "<p class='success'>✓ Database connection successful</p>\n";
                echo "<p class='info'>Server: " . htmlspecialchars($conn->getServerInfo()) . "</p>\n";

                // Check if required tables exist
                $tables = ['master_register', 'pensioners_register'];
                echo "<h3>Required Tables Check:</h3>\n";
                
                foreach ($tables as $table) {
                    $result = $conn->query("SHOW TABLES LIKE '$table'");
                    if ($result && $result->num_rows > 0) {
                        echo "<p class='success'>✓ Table: $table</p>\n";
                    } else {
                        echo "<p class='warning'>⚠ Table: $table (not found)</p>\n";
                    }
                }
                
            } else {
                echo "<p class='error'>✗ Database connection failed</p>\n";
            }
        } else {
            echo "<p class='error'>✗ Database connection variable not found</p>\n";
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Database connection error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
} else {
    echo "<p class='error'>✗ Database configuration file not found (includes/db.php)</p>\n";
}

echo "</div>\n";

// File Permissions Check
echo "<div class='section'>\n";
echo "<h2>File Permissions Check</h2>\n";

$writableFiles = ['exportData.php', 'includes/'];
foreach ($writableFiles as $file) {
    if (is_writable($file)) {
        echo "<p class='success'>✓ Writable: $file</p>\n";
    } else {
        echo "<p class='warning'>⚠ Not writable: $file</p>\n";
    }
}

echo "</div>\n";

// Installation Instructions
echo "<div class='section'>\n";
echo "<h2>Next Steps</h2>\n";

if (version_compare($phpVersion, $minPhpVersion, '>=') && 
    extension_loaded('pdo_pgsql') && 
    extension_loaded('json') && 
    extension_loaded('mbstring')) {
    
    echo "<p class='success'>✓ System requirements met!</p>\n";
    echo "<p>You can now:</p>\n";
    echo "<ul>\n";
    echo "<li>Access the admin dashboard at <a href='admin_dashboard.php'>admin_dashboard.php</a></li>\n";
    echo "<li>View the database at <a href='viewDatabase.php'>viewDatabase.php</a></li>\n";
    echo "<li>Create users at <a href='create_user.php'>create_user.php</a></li>\n";
    echo "</ul>\n";
    
    if (!file_exists('vendor/autoload.php')) {
        echo "<p class='warning'>For advanced Excel export, run: <code>composer install</code></p>\n";
    }
    
} else {
    echo "<p class='error'>✗ System requirements not met. Please fix the issues above before proceeding.</p>\n";
}

echo "</div>\n";

// Security Notice
echo "<div class='section'>\n";
echo "<h2>Security Notice</h2>\n";
echo "<p class='warning'>⚠ After successful installation:</p>\n";
echo "<ul>\n";
echo "<li>Remove or rename this install.php file</li>\n";
echo "<li>Ensure proper file permissions</li>\n";
echo "<li>Use strong passwords for database access</li>\n";
echo "<li>Consider using HTTPS in production</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<div class='section'>\n";
echo "<h2>Support</h2>\n";
echo "<p>If you encounter issues:</p>\n";
echo "<ul>\n";
echo "<li>Check the README.md file for detailed instructions</li>\n";
echo "<li>Verify your web server configuration</li>\n";
echo "<li>Check PHP error logs</li>\n";
echo "<li>Ensure MySQL/MariaDB service is running</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<p><em>Installation check completed at: " . date('Y-m-d H:i:s') . "</em></p>\n";
?>
