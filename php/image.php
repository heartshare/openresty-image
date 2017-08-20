<?php
require 'vendor/autoload.php';

use Intervention\Image\ImageManagerStatic as Image;

$document_root = $_SERVER['SERVER_ROOT'];

$uri = $_SERVER['REQUEST_URI'];

// 定义图片处理标识符
$image_separator = '/image2/';

// 如果没有处理需求，直接输出
if (strpos($uri, $image_separator) == false) {
    $source_image = $document_root . $uri;
    $img = Image::make($source_image);
    echo $img->response();
}

// 定义缓存路径
$cache_dir = $document_root . DIRECTORY_SEPARATOR . 'cache';

// 分析图片处理参数
$tmp_params = explode($image_separator, $uri)[1];


// URL格式模仿七牛云处理 https://developer.qiniu.com/dora/manual/1270/the-advanced-treatment-of-images-imagemogr2

// 定义允许的布尔型参数名
$allow_bool_params = array(
    'auto-orient',
    'thumbnail',
);

// 定义允许的mpa类型参数名
$allow_map_params = array(
    'strip',
    'gravity',
    'crop',
    'rotate',
    'format',
    'blur',
    'interlace',
    'quality',
    'sharpen',
    'size-limit',
);

// 检测存在的参数
$params = (explode('/', $tmp_params));

$map_params = array();
$bool_params = array();

foreach ($params as $pk => $param) {
    if (in_array($param, $allow_map_params)) {
        $map_params[$param] = urldecode($params[$pk + 1]);
    }

    if (in_array($param, $allow_bool_params)) {
        $bool_params[$param] = true;
    }
}

// 获取源图片路径
$source_image = $document_root . explode($image_separator, $uri)[0];

// 缓存图片路径
$ext = pathinfo($source_image)['extension'];
$cache_file = $cache_dir . DIRECTORY_SEPARATOR . md5($uri) . '.' . $ext;

if (!is_dir(dirname($cache_file))) {
    mkdir(dirname($cache_file), 0755, true);
}

$img = Image::make($source_image);

// 建议放在首位，根据原图EXIF信息自动旋正，便于后续处理。
$exif = $img->exif();

if (isset($exif['Orientation']) && $bool_params['auto-orient']) {
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


$img->resize(850, null, function ($constraint) {
    $constraint->aspectRatio();
});
$img->save($cache_file);
echo $img->response();