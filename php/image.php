<?php
require 'vendor/autoload.php';

use Intervention\Image\ImageManagerStatic as Image;

$source_image = 'test.jpg';
$img = Image::make($source_image);

$exif = $img->exif();

if (isset($exif['Orientation'])) {
    switch ($exif['Orientation']) {
        case 6:
            $img->rotate(-90);
            break;
        case 8:
            $img->rotate(90);
            break;
        case 3:
            $img->rotate(180);
            break;
    }
}

//var_dump($exif);
echo $img->response();
