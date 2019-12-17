<?php

// Create an __autoload function
// (can conflicts other autoloaders)
// http://php.net/manual/en/language.oop5.autoload.php

$libDir = __DIR__ . '/lib/Saml2/';
$extlibDir = __DIR__ . '/extlib/';

// Load composer
if (file_exists(__DIR__ .'/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// Load now external libs
require_once $extlibDir . 'xmlseclibs/xmlseclibs.php';

$folderInfo = scandir($libDir);

foreach ($folderInfo as $element) {
    if (is_file($libDir.$element) && (substr($element, -4) === '.php')) {
        include_once $libDir.$element;
        //break;
    }
}
