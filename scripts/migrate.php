<?php

$timestamp_start = microtime(true);

require 'vendor/autoload.php';
include 'TextCustomBundle/TextFormatter.php';
include 'function.php';

// INI CONFIG
// ------------------------------

set_time_limit(0);
ini_set('memory_limit', -1);
ini_set('display_errors', 'On');
ini_set('error_reporting', 'E_ALL');
ini_set('log_errors', 'On');
ini_set('error_log', '/scripts/logs/migrate.log');

error_reporting(E_ALL & ~E_NOTICE);

// ENVIRONMENT VARIABLES
// ------------------------------

parse_str($argv[1]);

$dbHost         = trim($dbHost);
$dbFluxbbUser   = trim($dbFluxbbUser);
$dbFluxbbName   = trim($dbFluxbbName);
$dbFluxbbPass   = trim($dbFluxbbPass);
$dbFlarumUser   = trim($dbFlarumUser);
$dbFlarumName   = trim($dbFlarumName);
$dbFlarumPass   = trim($dbFlarumPass);
$dbFluxbbPrefix = trim($dbFluxbbPrefix);

$mailFrom = trim($mailFrom);
$mailHost = trim($mailHost);
$mailPort = trim($mailPort);
$mailEncr = trim($mailEncr);
$mailUser = trim($mailUser);
$mailPass = trim($mailPass);

WriteInLog('------------------- STARTING MIGRATION PROCESS -------------------');

try {
    $dbFluxbb = new PDO("mysql:host=$dbHost;dbname=$dbFluxbbName;charset=utf8", $dbFluxbbUser, $dbFluxbbPass);
    $dbFlarum = new PDO("mysql:host=$dbHost;dbname=$dbFlarumName;charset=utf8", $dbFlarumUser, $dbFlarumPass);
    // Enabling PDO exceptions
    $dbFluxbb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbFlarum->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) {
    WriteInLog($e, 'ERROR');
    die('/!\ An error occurred while connecting to the databases');
}

WriteInLog('Connected successfully to the databases !');

RunQuery($dbFlarum, 'TRUNCATE TABLE users');
RunQuery($dbFlarum, 'TRUNCATE TABLE tags');
RunQuery($dbFlarum, 'TRUNCATE TABLE discussions');
RunQuery($dbFlarum, 'TRUNCATE TABLE discussions_tags');
RunQuery($dbFlarum, 'TRUNCATE TABLE posts');
RunQuery($dbFlarum, 'TRUNCATE TABLE groups');
RunQuery($dbFlarum, 'TRUNCATE TABLE users_groups');
RunQuery($dbFlarum, 'TRUNCATE TABLE users_discussions');

include 'importer/smileys.php';
include 'importer/users.php';
include 'importer/categories.php';
include 'importer/forums.php';
include 'importer/topics-posts.php';
include 'importer/groups.php';
include 'importer/subscriptions.php';
include 'importer/bans.php';
include 'importer/misc.php';

$timestamp_end = microtime(true);
$diff = $timestamp_end - $timestamp_start;
$min = floor($diff / 60);
$sec = floor($diff - $min * 60);

WriteInLog("---------------------- END OF MIGRATION (time : $min min $sec sec) ----------------------");
