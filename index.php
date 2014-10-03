<?php
session_start();
error_reporting(E_ERROR | E_WARNING | E_PARSE);

/* Include Files */
require_once 'YoutubeAnalyticsAPI.php';

$yt = new YoutubeAnalytics('Id Client', 'Secret Client', 0, true);

$res = $yt->listVideos();
echo $res;
?>
