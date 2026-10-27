<?php
session_start();
include '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['attachment'];

// Validate file type - Allow common document formats
$allowedTypes = [
    // PDF
    'application/pdf',
    // Word documents
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    // Excel spreadsheets
    'application/vnd.ms-excel', // .xls
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    // PowerPoint presentations
    'application/vnd.ms-powerpoint', // .ppt
    'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
    // Images
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    // Text files
    'text/plain',
    'text/csv',
    // Other
    'application/zip',
    'application/x-zip-compressed'
];

// Also check by file extension as backup
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'csv', 'zip'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => 'File type not allowed. Allowed: PDF, Word, Excel, PowerPoint, Images, Text, ZIP']);
    exit();
}

// Validate file size (max 20MB - increased from 10MB)
$maxSize = 20 * 1024 * 1024; // 20MB in bytes
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File size must be less than 20MB']);
    exit();
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueFilename = uniqid() . '_' . time() . '.' . $fileExtension;
$uploadPath = '../uploads/' . $uniqueFilename;

// Create uploads directory if it doesn't exist
if (!file_exists('../uploads')) {
    mkdir('../uploads', 0755, true);
}

// Move uploaded file to uploads directory
if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Return success with file path
    echo json_encode([
        'success' => true, 
        'filename' => $uniqueFilename,
        'path' => $uploadPath,
        'original_name' => $file['name']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
}
?>
