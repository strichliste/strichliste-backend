<?php

use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__).'/.env');

// this file drops and recreates the schema — refuse to run against anything but test
if ('test' !== $_SERVER['APP_ENV']) {
    throw new RuntimeException(sprintf('tests/bootstrap.php wipes the database and must only run with APP_ENV=test, got "%s".', $_SERVER['APP_ENV']));
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();
$metadata = $em->getMetadataFactory()->getAllMetadata();
$tool = new SchemaTool($em);
$tool->dropSchema($metadata);
$tool->createSchema($metadata);

$kernel->shutdown();
