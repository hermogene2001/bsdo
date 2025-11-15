<?php
// Script to restore the missing login and register modals to index.php

// Read the current index.php file
$currentContent = file_get_contents('index.php');

// Read the git version to get the modal content
$gitContent = file_get_contents('current_version.php');

// Extract the login modal (from line 749 to around line 830)
$loginModalLines = [];
$lines = explode("\n", $gitContent);
for ($i = 748; $i < 830; $i++) { // 0-indexed, so 749th line is index 748
    if (isset($lines[$i])) {
        $loginModalLines[] = $lines[$i];
    }
}
$loginModal = implode("\n", $loginModalLines);

// Extract the register modal (from line 833 to around line 920)
$registerModalLines = [];
for ($i = 832; $i < 920; $i++) { // 0-indexed, so 833rd line is index 832
    if (isset($lines[$i])) {
        $registerModalLines[] = $lines[$i];
    }
}
$registerModal = implode("\n", $registerModalLines);

// Find the position to insert the modals (before </body>)
$bodyPos = strrpos($currentContent, '</body>');

if ($bodyPos !== false) {
    // Insert the modals before the closing body tag
    $beforeBody = substr($currentContent, 0, $bodyPos);
    $afterBody = substr($currentContent, $bodyPos);
    
    $newContent = $beforeBody . "\n" . $loginModal . "\n\n" . $registerModal . "\n\n" . $afterBody;
    
    // Write the updated content back to index.php
    file_put_contents('index.php', $newContent);
    
    echo "Modals successfully restored to index.php\n";
} else {
    echo "Error: Could not find closing body tag in index.php\n";
}
?>