<?php
define('PHPUNIT_RUN', 1);
require_once __DIR__ . '/../vendor/autoload.php';


$classLoader = new \Composer\Autoload\ClassLoader();
$classLoader->addPsr4("Test\\", __DIR__. "/lib", true);
$classLoader->addPsr4("Test\\PageObject\\", __DIR__. "/lib", true);

$classLoader->register();