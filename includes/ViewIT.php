<?php


abstract class ViewIT
{

    public static function Print($s_tpl, $data)
    {
        //echo 'Шаблон: '.$s_tpl;
        $tpl = new HTML_Template_IT("./templates");
        $tpl->loadTemplatefile($s_tpl . '/index.php', true, true);

        $tpl->setCurrentBlock("row");
        foreach ($data as $key => $cell) {
        // Assign data to the inner block
            $tpl->setVariable($key, $cell);
        };
        $tpl->parseCurrentBlock("row");
        // print the output
        $tpl->show();
    }

}
