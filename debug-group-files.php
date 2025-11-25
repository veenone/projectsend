<?php
/**
 * Debug script to check group file visibility
 * Usage: Access this file in browser while logged in as an internal user
 */
require_once 'bootstrap.php';

// Must be logged in
if (!defined('CURRENT_USER_ID')) {
    die('Please log in first');
}

global $dbh;

echo "<h1>Group File Visibility Debug</h1>";
echo "<pre>";

// Get current user info
$user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
$props = $user->getProperties();

echo "=== Current User ===\n";
echo "ID: " . CURRENT_USER_ID . "\n";
echo "Name: " . $props['name'] . "\n";
echo "Role: " . $props['role_name'] . "\n";
echo "Active: " . $props['active'] . "\n\n";

// Get user's groups
echo "=== User's Groups ===\n";
$get_groups = new \ProjectSend\Classes\GroupsMemberships();
$found_groups_array = $get_groups->getGroupsByClient([
    'client_id' => CURRENT_USER_ID,
    'return' => 'array',
]);
$found_groups_list = $get_groups->getGroupsByClient([
    'client_id' => CURRENT_USER_ID,
    'return' => 'list',
]);

echo "Groups (array): " . print_r($found_groups_array, true) . "\n";
echo "Groups (list): '" . $found_groups_list . "'\n";
echo "Is empty: " . (empty($found_groups_list) ? 'YES' : 'NO') . "\n\n";

// Check group memberships in database
echo "=== Group Memberships in Database ===\n";
$stmt = $dbh->prepare("SELECT m.*, g.name as group_name FROM tbl_members m
                       LEFT JOIN tbl_groups g ON m.group_id = g.id
                       WHERE COALESCE(m.user_id, m.client_id) = ?");
$stmt->execute([CURRENT_USER_ID]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Group ID: {$row['group_id']}, Name: {$row['group_name']}, user_id: {$row['user_id']}, client_id: {$row['client_id']}\n";
}

// Get files assigned to these groups
if (!empty($found_groups_list)) {
    echo "\n=== Files Assigned to User's Groups ===\n";
    $stmt = $dbh->prepare("SELECT fr.*, f.filename FROM tbl_files_relations fr
                           LEFT JOIN tbl_files f ON fr.file_id = f.id
                           WHERE FIND_IN_SET(fr.group_id, ?)");
    $stmt->execute([$found_groups_list]);
    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $count++;
        echo "File ID: {$row['file_id']}, Filename: {$row['filename']}, ";
        echo "Group ID: {$row['group_id']}, Hidden: {$row['hidden']}\n";
    }
    if ($count == 0) {
        echo "No files found assigned to these groups\n";
    }
} else {
    echo "\n=== No groups found for this user ===\n";
}

// Get files assigned directly to user
echo "\n=== Files Assigned Directly to User ===\n";
$stmt = $dbh->prepare("SELECT fr.*, f.filename FROM tbl_files_relations fr
                       LEFT JOIN tbl_files f ON fr.file_id = f.id
                       WHERE fr.client_id = ?");
$stmt->execute([CURRENT_USER_ID]);
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    echo "File ID: {$row['file_id']}, Filename: {$row['filename']}, Hidden: {$row['hidden']}\n";
}
if ($count == 0) {
    echo "No files found assigned directly\n";
}

echo "\n=== Test Query (simulating template query) ===\n";
$test_query = "SELECT id, file_id, client_id, group_id FROM tbl_files_relations WHERE (client_id = :id";
if (!empty($found_groups_list) && $found_groups_list !== '') {
    $test_query .= " OR FIND_IN_SET(group_id, :groups)";
}
$test_query .= ") AND hidden = '0'";

echo "Query: " . $test_query . "\n";
echo "Parameters: id=" . CURRENT_USER_ID . ", groups='" . $found_groups_list . "'\n\n";

$stmt = $dbh->prepare($test_query);
$stmt->bindParam(':id', CURRENT_USER_ID, PDO::PARAM_INT);
if (!empty($found_groups_list) && $found_groups_list !== '') {
    $stmt->bindParam(':groups', $found_groups_list);
}
$stmt->execute();

echo "Results:\n";
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    echo "  File ID: {$row['file_id']}, client_id: {$row['client_id']}, group_id: {$row['group_id']}\n";
}
if ($count == 0) {
    echo "  No files found!\n";
} else {
    echo "  Total: $count file(s)\n";
}

echo "</pre>";
echo "<p><strong>If you see no files above but expect some, please check:</strong></p>";
echo "<ul>";
echo "<li>1. Are files actually assigned to the groups you're a member of?</li>";
echo "<li>2. Are those files marked as hidden=0 (not hidden)?</li>";
echo "<li>3. Did the database upgrade run successfully (check user_id column exists)?</li>";
echo "</ul>";
