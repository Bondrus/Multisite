<?php


class MyConfig
{
    public $mysqlHost = '';
    public $mysqlUser = '';
    public $mysqlPass = '';
    public $mysqlDb = '';
    public $typeError = 0;
    public $host = '';
    public $url = '';
    public $podstroka=0;

    public function __construct()
    {
        $this->mysqlHost = "localhost";
        $this->mysqlUser = "";
        $this->mysqlPass = "";
        $this->mysqlDb = "";
        // параметры вывода ошибок
        if ($this->typeError == 0) {
            ini_set('display_startup_errors', 1);
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        } else {
            ini_set('display_startup_errors', 0);
            ini_set('display_errors', 0);
            error_reporting(0);
        }
    }

    public function FromUrl()
    {
        if (!function_exists('getallheaders')) {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        } else {
            $headers = getallheaders();
        }
        $this->host = str_replace('www.', '', strtolower($headers['Host']));
        $this->url = $_SERVER['REQUEST_URI'];
        if ($this->url == '/') {
            $this->url = '/index.php';
        }
        if (($this->url != '/index.php') &&  (file_exists($this->url))) {
            include($this->url);
            exit;
        };

        // определяем номер подстраницы
        $pos = strpos($this->url, "podstroka");
        if ($pos === false) {
            $this->podstroka = 0;
        } else {
            $this->podstroka = (int)substr($this->url, $pos + 10);
            $this->url = substr($this->url, 0, $pos);
        };

    }


}