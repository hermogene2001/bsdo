<?php
// Create sample images for carousel

$upload_dir = 'uploads/carousel/';

// Create sample images
$images = [
    'sample1.jpg' => ['width' => 1200, 'height' => 400, 'bg_color' => [78, 115, 223], 'text' => 'Welcome to BSDO Sale'], // Blue
    'sample2.jpg' => ['width' => 1200, 'height' => 400, 'bg_color' => [28, 200, 138], 'text' => 'Live Shopping Experience'], // Green
    'sample3.jpg' => ['width' => 1200, 'height' => 400, 'bg_color' => [246, 194, 62], 'text' => 'Rent Products'] // Yellow
];

foreach ($images as $filename => $config) {
    // Create image
    $image = imagecreate($config['width'], $config['height']);
    
    // Set background color
    $bg_color = imagecolorallocate($image, $config['bg_color'][0], $config['bg_color'][1], $config['bg_color'][2]);
    
    // Set text color (white)
    $text_color = imagecolorallocate($image, 255, 255, 255);
    
    // Add text
    $font_size = 5;
    $text_bbox = imageftbbox($font_size, 0, 'arial', $config['text']);
    $text_width = $text_bbox[2] - $text_bbox[0];
    $text_height = $text_bbox[1] - $text_bbox[7];
    $x = ($config['width'] - $text_width) / 2;
    $y = ($config['height'] - $text_height) / 2 + $text_height;
    
    imagestring($image, $font_size, $x, $y, $config['text'], $text_color);
    
    // Save image
    imagejpeg($image, $upload_dir . $filename);
    
    // Free memory
    imagedestroy($image);
    
    echo "Created $filename\n";
}

echo "Sample images created successfully!\n";
?>