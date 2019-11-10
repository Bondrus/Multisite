<?php


use Symfony\Component\DomCrawler\Crawler;

class Sender
{
    private $config;
    public $hostconfig;
    private $db;
    public $content;
    public $data;

    private function tositemap($string)
    {
        $table = array(
            '&' => '&amp;',
            "'" => '&apos;',
            '"' => '&quot;',
            '>' => '&gt;',
            '<' => '&lt;'
        );
        $output = str_replace(
            array_keys($table),
            array_values($table), $string
        );
        return $output;
    }

    private function Readhostconfig()
    {
        $sql = 'select * from hosts where host=%s ';
        $hostconfig = $this->db->DbGet(2, $sql, $this->config->host);
        if (empty($hostconfig['host'])) {
            // обрабатываем субдомены и прочее
            $sql = 'select * from hosts where %s rlike host';
            $hostconfig = $this->db->DbGet(2, $sql, $this->config->host);
            if (empty($hostconfig['host'])) {
                // для прочих делаем редирект на любой хост
                $sql = 'select * from hosts order by rand() limit 1;';
                $hostconfig = $this->db->DbGet(2, $sql);
                header("Location: http://" . $hostconfig['host'] . "/", true, 301);
                exit();
            } else {
                // для субдоменов делаем редирект на домен
                $this->host = $hostconfig['host'];
                header("Location: http://" . $this->config->host . "/", true, 301);
            };
        };
        $this->hostconfig = $hostconfig;
    }


    public function __construct($config, $db)
    {
        $this->config = $config;
        $this->db = $db;
        $this->Readhostconfig();
    }

    public function SendRobotTxt()
    {
        echo "\n";
        echo 'User-Agent: *' . "\n";
        echo 'Disallow:' . "\n";
        echo 'Host: ' . $this->config->host . "\n";
        echo 'Sitemap: http://' . $this->config->host . '/sitemap.xml' . "\n";
    }

    public function SendSiteMapXml()
    {
        $data['xml'] = '<' . '?' . 'xml version="1.0" encoding="UTF-8"?>' . "\n";
        $data['xml'] .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $sql = 'select distinct cat.content, cat.alias as uri, cat.id, 1000000 as count, 1 as lvl, cat.sort, sape 
				from ' . $this->hostconfig['content'] . ' u, ' . $this->hostconfig['cat'] . ' cat where u.host=%s and cat.id=u.cat_id 
				union all
				select u.content, u.uri, cat.id, if(cat.alias="' . $this->config->url . '",1000000+u.id,u.id) as count, 2 as lvl, cat.sort, sape
				from ' . $this->hostconfig['content'] . ' u, ' . $this->hostconfig['cat'] . ' cat where u.host=%s and cat.id=u.cat_id  
				order by sort, id  desc, count limit 0,1000';
        $links = $this->db->DbGet(3, $sql, $this->config->host, $this->config->host);
        foreach ($links as $link) {
            $data['xml'] .= '<url>' . "\n";
            $data['xml'] .= '<loc>http://' . $this->config->host . $this->tositemap($link['uri']) . '</loc>' . "\n";
            $data['xml'] .= '<changefreq>weekly</changefreq>' . "\n";
            $data['xml'] .= '</url>' . "\n";
        };
        $data['xml'] .= '</urlset>';
        echo $data['xml'];
    }

    public function AddStatistics()
    {
        $sql = 'update ' . $this->hostconfig['content'] . ' set count=count+1 where host=%s and uri=%s ';
        $this->db->DbUpdate($sql, $this->config->host, $this->config->url);
    }

    public function AddDataSiteMap()
    {
        $data['read'] = '';
        $sql = 'select distinct cat.content, cat.alias as uri, cat.id, 1000000 as count, 1 as lvl, cat.sort 
				from ' . $this->hostconfig['cat'] . ' cat where cat.host=%s 
				union all
				select u.content, u.uri, cat.id, u.id as count, 2 as lvl, cat.sort 
				from ' . $this->hostconfig['content'] . ' u, ' . $this->hostconfig['cat'] . ' cat where u.host=%s and cat.id=u.cat_id  
				order by sort, lvl, count limit 0,1000';
        $links = $this->db->DbGet(3, $sql, $this->config->host, $this->config->host);
        $lastlvl = 0;
        foreach ($links as $link) {
            $h = 'Статья';
            $c = new Crawler($link['content']);
            if (count($c)) {
                $h = $c->filter('h1')->text();
            };
            if ($link['lvl'] == 1) {
                if ($lastlvl == 2) $data['read'] .= '</ul>';
                $data['read'] .= '<br><b><a href="' . $link['uri'] . '">' . $h . '</a></b>';
            };
            if ($link['lvl'] == 2) {
                if ($lastlvl == 1) $data['read'] .= '<ul>';
                $data['read'] .= '<li><a href="' . $link['uri'] . '">' . $h . '</a></li>';
            };
            $lastlvl = $link['lvl'];
        };
        if ($lastlvl == 2) $data['read'] .= '</ul>';
        $data['h1'] = 'Карта сайта';

        $this->data['h1'] = $data['h1'];
        $this->data['read'] = $data['read'];
    }

    public function ReadContent()
    {
        $sql = 'select * from ' . $this->hostconfig['content'] . ' where host=%s and uri=%s';
        $content = $this->db->DbGet(2, $sql, $this->config->host, $this->config->url);

        if (!$content) {
            $content['content'] = '';
            $content['description'] = '';
            $content['id'] = 0;
            $content['h'] = '';
        } else {
            $c = (new Crawler($content['content']))->filter('h1');
            $content['h'] = $c->count() ? $c->text() : '';
        }
        $this->content = $content;
    }

    public function AddDataRead()
    {
        $data['read'] = '';
        $h = '';
        $sql = 'select * from ' . $this->hostconfig['content'] . ' where (locate("href",content)=0 or sape=2) and host=%s and ((id=%d+1) or (mod(id,20)=mod(%d,19))) order by sape desc limit 0,3';
        $links = $this->db->DbGet(3, $sql, $this->config->host, $this->content["id"], $this->content["id"], $this->content["id"]);
        foreach ($links as $link) {
            $c = (new Crawler($link['content']))->filter('h1');
            $h = $c->count() ? $c->text() : '';
            $data['read'] .= '<li><a href="' . $link['uri'] . '">' . $h . '</a></li>';
        };
        $this->data['read'] = $data['read'];
    }

    public function AddDataProperties()
    {
        $this->data['keys'] = $this->data['h1'];
        if ($this->content['description'] == '') {
            if ($this->hostconfig['desc'] == '') {
                $this->data['title'] = $this->data['h1'];
                $this->data['desc'] = $this->data['h1'];
            } else {
                $this->data['title'] = $this->hostconfig['desc'] . '. ' . $this->data['h1'];
                $this->data['desc'] = $this->hostconfig['desc'] . '. ' . $this->data['h1'];
            };
        } else {
            $this->data['title'] = $this->content['description'];
            $this->data['desc'] = $this->content['description'];
        };
        $this->data['template'] = '/../templates/' . $this->hostconfig['template'];
    }

    public function AddDataContent()
    {
        $data['content'] = '';
        if ($this->config->podstroka == 0) {
            $pos1 = false;
        } else {
            if (strlen($this->content['content']) > $this->hostconfig['podstrka'] * $this->config->podstroka) {
                $pos1 = strpos($this->content['content'], '<p', $this->hostconfig['podstrka'] * $this->config->podstroka);
            } else {
                $pos1 = false;
            }
        };
        if (strlen($this->content['content']) > $this->hostconfig['podstrka'] * ($this->config->podstroka + 1)) {
            $pos2 = strpos($this->content['content'], '<p', $this->hostconfig['podstrka'] * ($this->config->podstroka + 1));
        } else {
            $pos2 = false;
        }

        if ($pos1 === false) {
            $data['content'] = $this->content['content'];
        } else {
            if ($pos1 > 0) {
                $data['content'] .= '<h2>' . $this->content['h'] . ' (' . ($this->config->podstroka + 1) . ')</h2>';
            };
            if ($pos2 === false) {
                $data['content'] .= substr($this->content['content'], $pos1);
            } else {
                $data['content'] .= substr($this->content['content'], $pos1, $pos2 - $pos1);
            }
        };
        $data['content'] .= '<br>';
        if ($this->config->podstroka == 1) {
            $data['content'] .= '::<a href="' . $this->config->url . '">Предыдущая страница</a>::';
        };
        if (($this->config->podstroka > 1) && ($this->config->podstroka <> 1000)) {
            $data['content'] .= '::<a href="' . $this->config->url . 'podstroka=' . ($this->config->podstroka - 1) . '">Предыдущая страница</a>::';
        };
        if ($pos2 > 0) {
            $data['content'] .= '::<a href="' . $this->config->url . 'podstroka=' . ($this->config->podstroka + 1) . '">Следующая страница</a>::';
        };
        if ((strlen($this->content['content']) < 1000)) {
            $sql = 'select * from ' . $this->hostconfig['content'] . ' where cat_id=%f and id>%f limit 0,1';
            $content2 = $this->db->DbGet(2, $sql, $this->content['cat_id'], $this->content['id']);
            $data['content'] .= '<br><br><br><br><i>Похожее...</i><hr><br>' . $content2['content'];
        };

        $this->data['content'] = $data['content'];
        $this->data['h1'] = $this->content['h'];
    }

    public function AddDataMultiContent()
    {
        $data['content'] = '';
        $sql = 'select * from ' . $this->hostconfig['content'] 
             . ' where host=%s and (locate(%s,uri)>0 or %s="index.php" '
             . ' or cat_id in (select id from '.$this->hostconfig['cat'].' cat  where cat.alias=%s)) order by id desc, sape desc, count desc limit 0,10 ';
        $contentcat = $this->db->DbGet(3, $sql, $this->config->host, $this->config->url, $this->config->url,$this->config->url);
        $i = 0;
        foreach ($contentcat as $cc) {
            $i++;
            $c2 = (new Crawler($cc['content']))->filter('h1');
            $c3 = (new Crawler($cc['content']))->filter('p')->each(function (Crawler $node, $i) {
                return $node->html();
            });
            if (count($c2) && count($c3)) {
                if ((trim($c3[0])=='') && isset($c3[1])){
                    $p=$c3[1];
                } else {
                    $p=$c3[0];
                }
                $data['content'] .= '<a href="' . $cc['uri'] . '"><h1>' . $c2->text() . '</h1> </a>'
                    . preg_replace('|<a[^>]+>([^<]+)</a>|ism', '$1', '<p>'
                    . $p) . '</p>' . '<a href="' . $cc['uri'] . '">Продолжение ...</a>';
            }
        };
        if (($this->config->url != '/sitemap') && ($i == 0)) {
            header("HTTP/1.0 404 Not Found");
            $this->config->url = '/index.php';
            $this->ReadContent();
        };

        $sql = 'select content from ' . $this->hostconfig['cat'] . ' where  host=%s and locate(alias,%s)>0';
        $html = $this->db->DbGet(1, $sql, $this->config->host, $this->config->url);
        $c = (new Crawler($html))->filter('h1');
        $data['h1'] = $c->count() ? $c->text() : '';
        $this->data['h1'] = $data['h1'];
        $this->data['content'] = $data['content'];
    }


    public function AddDataReadOutlink()
    {
        $data['read'] = '';
        if ($this->hostconfig['outlinks'] != 0) {
            $sql = 'select * from ' . $this->hostconfig['content'] . ' where id=%d';
            $link = $this->db->DbGet(2, $sql, $this->content['link']);
            if (isset($link['content'])) {
               $h = 'Статья';
               $c = (new Crawler($link['content']))->filter('h1');
               $h = $c->count() ? $c->text() : '';
               $data['read'] .= '<li><a href="' . $link['uri'] . '">' . $h . '</a></li>';
            };
        };
        $this->data['read'] = $this->data['read'] . $data['read'];
    }

    public function AddDataCat()
    {
        $data['cat'] = '';

        $sql = 'select distinct cat.content, cat.alias as uri, cat.id, 1000000 as count, 1 as lvl, cat.sort , 0 as sape
				from ' . $this->hostconfig['cat'] . ' cat where cat.host=%s 
				union all
				select u.content, u.uri, u.id, if(cat.alias="' . $this->config->url . '",1000000+u.id,u.id) as count, 2 as lvl, cat.sort, u.sape 
				from ' . $this->hostconfig['content'] . ' u, ' . $this->hostconfig['cat'] . ' cat where u.host=%s and cat.id=u.cat_id and locate(cat.alias,"' . $this->config->url . '")>0  
				order by sort, lvl, id desc, sape desc, count limit 0,50';
        $links = $this->db->DbGet(3, $sql, $this->config->host, $this->config->host);
        $lastlvl = 0;
        foreach ($links as $link) {
            $h = 'Статья';
            $c = (new Crawler($link['content']))->filter('h1');
            $h = $c->count() ? $c->text() : '';
            if ($link['lvl'] == 1) {
                if ($lastlvl == 2) $data['cat'] .= '</ul></li>';
                $data['cat'] .= '<li><b><a href="' . $link['uri'] . '">' . $h . '</a></b></li>';
            };
            if ($link['lvl'] == 2) {
                if ($lastlvl == 1) $data['cat'] .= '<li><ul>';
                $data['cat'] .= '<li><a href="' . $link['uri'] . '">' . $h . '</a></li>';
            };
            $lastlvl = $link['lvl'];
        };
        if ($lastlvl == 2) $data['cat'] .= '</ul></li>';
        $data['cat'] .= '<li><b><a href="/sitemap">КАРТА САЙТА</a></b></li>';
        $this->data['cat'] = $data['cat'];
    }

    public function AddDataSape()
    {
        // Добавляем данные с SAPE.RU
        $data['sape'] = '';
        if (!defined('_SAPE_USER')) {
            define('_SAPE_USER', '9fdf85d295da53ffe252d2a35041414f');
        };
        require_once($_SERVER['DOCUMENT_ROOT'] . '/' . _SAPE_USER . '/sape.php');
        $o['charset'] = 'utf-8';
        $sape = new SAPE_client($o);
        $sape_article = new SAPE_articles($o);
        $data['sape'] .= $sape_article->return_announcements();
        $data['sape'] .= $sape->return_links();
        $this->data['sape'] = $data['sape'];

    }

    public function SendForms(array $post)
    {
        if (isset ($post['cat_content'])) {
            $cat_content = $post['cat_content'];
            if (isset ($post['cat_alias'])) {
                $cat_alias = $post['cat_alias'];
            } else {
                $cat_alias = Utils::encodestring($cat_content);
            }
            $this->db->DbUpdate("insert into " . $this->hostconfig['cat'] . " set host=%s, content=%s, alias=%s ", $this->config->host, '<h1>' . $cat_content . '</h1>', '/' . $cat_alias);
            $this->db->DbUpdate("update " . $this->hostconfig['cat'] . " set sort=id where sort=0");
        };

        if (isset ($post['h1'])) {
            $h1 = $post['h1'];
            if (isset ($post['content'])) {
                $content = $post['content'];
            };
            if (isset ($post['cat_id'])) {
                $cat_id = $post['cat_id'];
                $alias = $this->db->DbGet(1, 'select alias from ' . $this->hostconfig['cat'] . ' cat where cat.host=%s and id=%d ', $this->config->host, $cat_id);
            };
            if (isset ($post['uri'])) {
                $uri = $post['uri'];
            };
            if ($uri == '') $uri = ('/') . Utils::encodestring($h1);

            $this->db->DbUpdate("insert into " . $this->hostconfig['content'] . " set content=%s, host=%s, yandex=0, link=0, sape=2,uri=%s, cat_id=%d, description=%s ",  ('<h1>') . $h1 . ('</h1>') . $content, $this->config->host, $alias . $uri, $cat_id,"");
        };

        if (isset ($post['pass']) && $post['repl1'] && isset ($post['repl2'])) {
            if ($post['pass'] == 'editor') {
                $repl1 = $post['repl1'];
                $repl2 = $post['repl2'];
                $this->db->DbUpdate("insert into add_replace  (dat, repl1, repl2) values  (now(),%s,%s) ", $repl1, $repl2);
                $links = $this->db->DbGet(3, 'select distinct content from hosts');
                foreach ($links as $link) {
                    $this->db->DbUpdate("update " . $link['content'] . " set content = replace (content,%s,%s )", $repl1, $repl2);
                };
            };
        };
        $data['content'] = '<h1>Добавление страниц и настройка</h1>
<form name="cat" method="post" action="aadd.php">
Пункт меню<br><input type="text" name="cat_content" size="70" value="">
Алиас меню<br><input type="text" name="cat_alias" size="70" value="">
<input type="submit" value="Отправить"> 
</form><br><hr><br>
<form method="post" action="aadd.php">
Название статьи<br><input type="text" name="h1" size="70" value="">
Алиас статьи<br><input type="text" name="uri" size="70" value="">
Меню<br><select class="input" name="cat_id" >';
        $data['content'] .= '<option value="0">не выбран</option>';

        $cats = $this->db->DbGet(3, 'select * from ' . $this->hostconfig['cat'] . ' where host=%s order by sort', $this->config->host);

        foreach ($cats as $c) {
            $data['content'] .= '<option value="' . $c['id'] . '">' . $c['content'] . '</option>';
        };

        $data['content'] .= '</select><br>
Текст статьи<br>
<textarea name="content" cols="65" rows="20">
<p>
</p>
<p>
</p>
<img src="../images/  " width="500">
</textarea>
<input type="submit" value="Отправить"> 
</form><br><hr><br>';

        $data['content'] .= '<form method="post" action="aadd.php">
<br>Пароль админа:<br><input type="text" name="pass" size="70" value="">
<br>Искомое словосочетание:<br><input type="text" name="repl1" size="70" value="">
<br>Конечное словосочетание:<br><input type="text" name="repl2" size="70" value="">
<input type="submit" value="Отправить"> 
</form><br><hr><br>';
        $this->data['content']=$data['content'];
        $this->data['template'] = '/../templates/' . $this->hostconfig['template'];
        $this->data['title'] = 'Настройка сайта';

    }
}
