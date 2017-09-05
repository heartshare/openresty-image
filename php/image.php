<?php
require 'vendor/autoload.php';

use Intervention\Image\ImageManagerStatic as Image;

$document_root = $_SERVER['SERVER_ROOT'];

$uri = $_SERVER['REQUEST_URI'];

// 定义图片处理标识符
$image_separator = '/image2/';

// 获取源图片路径
$source_image = $document_root . explode($image_separator, $uri)[0];

if (!file_exists($source_image)) {
    echo 'file not found!';
    exit();
}

// 定义缓存路径
$cache_dir = $document_root . DIRECTORY_SEPARATOR . 'cache';

// 分析图片处理参数
$tmp_params = explode($image_separator, $uri)[1];


// URL格式模仿七牛云处理 https://developer.qiniu.com/dora/manual/1270/the-advanced-treatment-of-images-imagemogr2

// 定义允许的布尔型参数名
$allow_bool_params = array(
    'auto-orient',  // 角度纠正
);

// 定义允许的mpa类型参数名
$allow_map_params = array(
    'thumbnail',    // 缩略设置
    'quality',      // 质量设置
);

// 检测存在的参数
$params = (explode('/', $tmp_params));

$map_params = array();
$bool_params = array();

foreach ($params as $pk => $param) {
    if (in_array($param, $allow_map_params) && isset($params[$pk + 1])) {
        $map_params[$param] = urldecode($params[$pk + 1]);
    }

    if (in_array($param, $allow_bool_params)) {
        $bool_params[$param] = true;
    }
}


if (empty($map_params) && empty($bool_params)) {
    echo 'file not found!';
    exit();
}


// 缓存图片路径
$ext = pathinfo($source_image)['extension'];

$cache_file = $cache_dir . DIRECTORY_SEPARATOR . md5($uri) . '.' . $ext;

if (!is_dir(dirname($cache_file))) {
    mkdir(dirname($cache_file), 0755, true);
}

if ($ext == 'gif') {
    copy($source_image, $cache_file);
    exit();
}

// 处理图片
Image::configure(array('driver' => 'imagick'));
$img = Image::make($source_image);

$source_width = $img->width();
$source_height = $img->height();

// 优先处理角度纠正，根据原图EXIF信息自动旋正，便于后续处理。
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


// 缩略处理
if (isset($map_params['thumbnail']) && (strpos($map_params['thumbnail'], 'x') !== false)) {

    list($set_width, $set_height) = explode('x', $map_params['thumbnail']);

    $width = $img->width();
    $height = $img->height();

    $set_width = intval($set_width);
    $set_height = intval($set_height);

    if ((strpos($set_width, '!') !== 0) && $width < $set_width) {
        $set_width = $width;
    }

    if ((strpos($set_height, '!') !== 0) && $height < $set_height) {
        $set_height = $height;
    }

    if ($set_width && $set_height) {
        $img->resize($set_width, $set_height);
    } elseif ($set_width && !$set_height) {
        $img->resize($set_width, null, function ($constraint) {
            $constraint->aspectRatio();
        });
    } elseif (!$set_width && $set_height) {
        $img->resize(null, $set_height, function ($constraint) {
            $constraint->aspectRatio();
        });
    }
}

// 增加水印支持
$width = $img->width();
$height = $img->height();

$img->text('王玉鹏的官方网站：www.41ms.com', $width - 10, $height - 10, function ($font) {
    $font->file('msyh.ttf');
    $font->size(15);
    $font->color('#BEBEBE');
    $font->align('right');
    $font->valign('bottom');
});


// 图片质量设置
$quality = 75;

if (isset($map_params['quality']) && (intval($map_params['quality']) > 0)) {
    $quality = intval($map_params['quality']);
}

echo $img->response($ext, $quality);

$img->save($cache_file, $quality);
$img->destroy();
