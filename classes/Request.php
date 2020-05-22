<?php
/**
 * @author Lukmanov Mikhail <lukmanof92@gmail.com>
 * Date: 21.05.2020
 * Time: 11:01
 */

namespace IIT\Service;


class Request
{
    public static $arPortals = [
        [
            'url' => 'razvitie-1c.bitrix24.ru',
            'api' => 'ejpclud9lnua05pz',
            'user_id' => '456'
        ]
    ];

    public static function send($method, $arFields = [], $keyPortal = 0)
    {
        $arResult = [
            'result' => false
        ];
        $arPortal = self::$arPortals[$keyPortal];

        if ($arPortal) {
            $queryUrl = 'https://' . $arPortal['url'] . '/rest/' . $arPortal['user_id'] . '/' . $arPortal['api'] . '/' . $method;
            $queryData = http_build_query($arFields);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_POST => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $queryUrl,
                CURLOPT_POSTFIELDS => $queryData,
            ));
            $result = curl_exec($curl);
            curl_close($curl);
            $arResult = json_decode($result, 1);
        }
        return $arResult;
    }
}