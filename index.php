<?php
require __DIR__ . '/vendor/autoload.php';

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
        $sender->AddStatistics();

        $sender->ReadContent();
        $sender->AddDataCat();

        if ($config->url == '/sitemap') {
            $sender->AddDataSiteMap();
        } else {
            if ($sender->content['content'] == '') {
                $sender->AddDataMultiContent();
            } else {
                $sender->AddDataRead();
                // определяем ссылки на другие страницы
                $sender->AddDataReadOutlink();
                // определяем данные каталога
                $sender->AddDataContent();
            }
        };

        $sender->AddDataProperties();
        $sender->AddDataSape();
	ViewIT::Print($sender->hostconfig['template'], $sender->data);
    };
};
?>
