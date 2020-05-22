<?php
/**
 * @author Lukmanov Mikhail <lukmanof92@gmail.com>
 * Date: 21.05.2020
 * Time: 11:07
 */

namespace IIT\Service;


class Log
{
    public static function add($arResult, $type = 'response')
    {
        $log = '[' . date('D M d H:i:s Y', time()) . '] ';
        if ($type) {
            $log .= $type . ': ';
        }
        $log .= json_encode($arResult, JSON_UNESCAPED_UNICODE);
        $log .= "\n";
        $rs = file_put_contents(dirname(__FILE__) . "../log/" . $type . "/" . date('D M d H:i:s Y', time()) . ".log", $log, FILE_APPEND);
        if ($rs) {
            return true;
        }
        return false;
    }
}