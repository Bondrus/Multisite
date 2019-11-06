<?php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;

include_once('includes\MyConfig.php');
include_once('includes\Db.php');
include_once('includes\Sender.php');
include_once('includes\ViewIT.php');
include_once('includes\Utils.php');

$config = new MyConfig();
$db = new Db();
$db->DbConnect($config->mysqlHost,$config->mysqlUser,$config->mysqlPass,$config->mysqlDb);
$config->FromUrl();

$sender= new Sender($config,$db);
if ($config->url == '/robots.txt') {
    $sender->SendRobotTxt();
} else {
    if ($config->url == '/sitemap.xml') {
        $sender->SendSiteMapXml();
    } else {

        $sender->AddDataCat();
        $sender->SendForms($_POST);

        ViewIT::Print($sender->hostconfig['template'], $sender->data);
    };
};
?>
