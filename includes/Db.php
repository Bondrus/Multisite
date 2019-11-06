<?php


class Db
{
    private $linkdb;

    function DbConnect(string $mysqlHost, string $mysqlUser, string $mysqlPass,string $mysqlDb):int {

        /* Соединяемся, выбираем базу данных */
        $this->linkdb = mysqli_connect($mysqlHost, $mysqlUser, $mysqlPass,$mysqlDb);
        if (!$this->linkdb) {
            echo "Ошибка: Невозможно установить соединение с MySQL." . PHP_EOL;
            echo "Код ошибки errno: " . mysqli_connect_errno() . PHP_EOL;
            echo "Текст ошибки error: " . mysqli_connect_error() . PHP_EOL;
            return 0;
        }
        mysqli_query($this->linkdb,"SET NAMES 'utf8'");
        return 1;
    }

    public function DbUpdate() {
        $args=func_get_args();
        $query = array_shift($args);
        $query = str_replace("%s","'%s'",$query);
        foreach ($args as $key => $val) {
            $args[$key] = mysqli_real_escape_string($this->linkdb,$val);
        }
        $query = vsprintf($query, $args);
        if (!$query) return false;

        $res = mysqli_query($this->linkdb,$query) or trigger_error("db: ".mysqli_error($this->linkdb)." in ".$query);
        return $res;
    }

    public function DbGet() {
        /*
        usage: dbget($mode, $query, $param1, $param2,...);
        $mode - "dimension" of result:
        0 - resource
        1 - scalar
        2 - row
        3 - array of rows
        */
        $args=func_get_args();
        if (count($args) < 2) {
            trigger_error("dbget: too few arguments");
            return false;
        }
        $mode  = array_shift($args);
        $query = array_shift($args);
        $query = str_replace("%s","'%s'",$query);

        foreach ($args as $key => $val) {
            $args[$key] = mysqli_real_escape_string($this->linkdb,$val);
        }

        $query = vsprintf($query, $args);
        if (!$query) return false;

        $res = mysqli_query($this->linkdb,$query);
        if (!$res) {
            trigger_error("dbget: ".mysqli_error($this->linkdb)." in ".$query);
            return false;
        }

        if ($mode === 0) return $res;

        if ($mode === 1) {
            if ($row = mysqli_fetch_row($res)) return $row[0];
            else return NULL;
        }

        $a = array();
        if ($mode === 2) {
            if ($row = mysqli_fetch_assoc($res)) return $row;
        }
        if ($mode === 3) {
            while($row = mysqli_fetch_assoc($res)) $a[]=$row;
        }
        return $a;
    }

}