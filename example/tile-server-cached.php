<?php

// cached tile server to demonstrate PHP 7 Mapnik

namespace PHP7MapnikExample2;

const RAD_TO_DEG = 180 / M_PI;

$x = isset($_GET['x']) ? $_GET['x'] : null;
$y = isset($_GET['y']) ? $_GET['y'] : null;
$zoom = isset($_GET['z']) ? $_GET['z'] : null;

if ($x === null || $y === null || $zoom === null) {
    throw new \Exception('Missing X, Y, or Z query parameter.');
}


$size = 256;


$Bc = [];
$Cc = [];
$zc = [];
$Ac = [];


$options['image']['x'] = $size;
$options['image']['y'] = $size;
$options['xml'] = realpath(dirname(__FILE__)) . '/example.xml';
$options['basePath'] = realpath(dirname(__FILE__) . '/../tests/data');


//store via hashed filesytem
$file['hash'] = md5("$x-$y-$zoom");
$file['hashmap'] = '';
for ($i=0; $i < 12; $i = $i+4) { 
  $file['hashmap'] .= substr($file['hash'],$i, 4 ). DIRECTORY_SEPARATOR;
}

$path = realpath('./cache/').'/'.$file['hashmap'];

$file = $path.md5($options['xml']).'.'.$size;


$options['tile']['x'] = $x;
$options['tile']['y'] = $y;
$options['tile']['z'] = $zoom;
$options['tile']['size'] = $size;
$options['tile']['file'] = $file;
$options['tile']['levels'] = 18;




    if (!is_file($file)){
         if (!is_dir($path)) { 
            createPath($path);
         }
        renderTile($options);
    }

header("Content-Type: image/png");
header('Content-Length: ' . filesize($file));

readfile($file);


exit();
//header("Content-Length: " . strlen($renderedImage));




/**
 * create path
 *
 * @param string $path
 * @return next item, false or true
 */

function createPath($path) {
    if (is_dir($path)) return true;
    $prev_path = substr($path, 0, strrpos($path, '/', -2) + 1 );
    $return = createPath($prev_path);
    return ($return && is_writable($prev_path)) ? mkdir($path) : false;
}


/**
 * Convert screen pixel value to lat lng
 *
 * @param array $px
 * @param int $zoom
 * @return array
 */
function pxToLatLng(array $px, $zoom) {
    global $Bc, $Cc, $zc;
    $zoomDenominator = $zc[$zoom];
    $g = ($px[1] - $zoomDenominator) / (-$Cc[$zoom]);
    $lat = ($px[0] - $zoomDenominator) / $Bc[$zoom];
    $lng = RAD_TO_DEG * (2 * atan(exp($g)) - 0.5 * M_PI);
    return [$lat, $lng];
}




/**
 *  render tiles;
 *
 * @param array $options
 */
function renderTile($options){
    global $Bc, $Cc, $zc;

    $x = $options['tile']['x'];
    $y = $options['tile']['y'];
    $zoom = $options['tile']['z']; 
    $size = $options['tile']['size'];
    $levels = $options['tile']['levels'];

    $levelSize = $size;
    for ($d = 0; $d <= $levels; $d++) {
        $Bc[] = $levelSize / 360;
        $Cc[] = $levelSize / (2 * M_PI);
        $zc[] = $levelSize / 2;
        $Ac[] = $levelSize;
        $levelSize *= 2;
    }


    // Find tile boundary

    $lowerLeft = [$x * $size, ($y + 1) * $size];
    $upperRight = [($x + 1) * $size, $y * $size];

    $lowerLeftLatLng = pxToLatLng($lowerLeft, $zoom);
    $upperRightLatLng = pxToLatLng($upperRight, $zoom);

    $source = new \Mapnik\Projection('+init=epsg:4326');
    $destination = new \Mapnik\Projection('+init=epsg:3857');
    $transform = new \Mapnik\ProjTransform($source, $destination);
    $boundingBox = new \Mapnik\Box2D(
        $lowerLeftLatLng[0],
        $lowerLeftLatLng[1],
        $upperRightLatLng[0],
        $upperRightLatLng[1]
    );

    $tileBoundingBox = $transform->forward($boundingBox);

    // Render
    $pluginConfigOutput = [];
    exec('mapnik-config --input-plugins', $pluginConfigOutput);
    \Mapnik\DatasourceCache::registerDatasources($pluginConfigOutput[0]);

    $map = new \Mapnik\Map($options['image']['x'], $options['image']['y'], '+init=epsg:3857');
    $fontConfigOutput = [];
    exec('mapnik-config --fonts', $fontConfigOutput);
    $map->registerFonts($fontConfigOutput[0]);


    $map->loadXmlFile($options['xml'], false, $options['basePath']);
    $map->zoomToBox($tileBoundingBox);

    $image = new \Mapnik\Image($options['image']['x'], $options['image']['y']);
    $renderer = new \Mapnik\AggRenderer($map, $image);
    $renderer->apply();

    //$renderedImage = $image->saveToString('png');
    $renderedImage = $image->saveToFile($options['tile']['file']);


}

