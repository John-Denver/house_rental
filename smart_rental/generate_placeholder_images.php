<?php
// Create images directory if it doesn't exist
$imagesDir = __DIR__ . '/assets/images/';
if (!file_exists($imagesDir)) {
    mkdir($imagesDir, 0777, true);
}

// List of required images with their dimensions
$images = [
    'about-hero.jpg' => '1200x800',
    'our-story.jpg' => '800x600',
    'team-1.jpg' => '500x500',
    'team-2.jpg' => '500x500',
    'team-3.jpg' => '500x500',
    'team-4.jpg' => '500x500',
    'testimonial-1.jpg' => '200x200',
    'testimonial-2.jpg' => '200x200',
    'testimonial-3.jpg' => '200x200',
    'smart_rental_logo.png' => '200x80'
];

// Download placeholder images
foreach ($images as $filename => $dimensions) {
    $url = "https://placehold.co/{$dimensions}/0d6efd/ffffff?text=" . urlencode(ucwords(str_replace(['-', '.jpg', '.png'], ' ', $filename)));
    $filepath = $imagesDir . $filename;
    
    if (!file_exists($filepath)) {
        $imageData = file_get_contents($url);
        if ($imageData !== false) {
            file_put_contents($filepath, $imageData);
            echo "Downloaded: {$filename}\n";
        } else {
            echo "Failed to download: {$filename}\n";
        }
    } else {
        echo "Skipped (already exists): {$filename}\n";
    }
}

echo "\nImage generation complete!\n";
?>
