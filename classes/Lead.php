<?php
/**
 * @author Lukmanov Mikhail <lukmanof92@gmail.com>
 * Date: 21.05.2020
 * Time: 17:35
 */

namespace IIT\Entity;

use IIT\Service\Request,
    IIT\Entity\Status;

class Lead
{
    public static $keyPortal;
    public static $action;
    public static $error;

    public static function add($arFields)
    {
        $arResponse = Request::send('crm.lead.add', [
            'fields' => $arFields
        ]);
        if ($arResponse['result'])
        {
            return $arResponse['result'];
        }
        elseif ($arResponse['error_description'] || $arResponse['error'])
        {
            self::$error = $arResponse['error_description'] ?: $arResponse['error'];
            return false;
        }

        return false;
    }

    public static function update($id, $arFields)
    {
        $arResponse = Request::send('crm.lead.update', [
            'id' => $id,
            'fields' => $arFields
        ]);
        if ($arResponse['result'] == true)
        {
            return true;
        }
        elseif ($arResponse['error_description'] || $arResponse['error'])
        {
            self::$error = $arResponse['error_description'] ?: $arResponse['error'];
            return false;
        }

        return false;
    }

    public static function getError()
    {
        return self::$error;
    }

    public static function getByPhone($phone = null)
    {
        if ($phone == null) {
            return false;
        }
        $arFilter = [
            'order' => [
                'ID' => 'DESC'
            ],
            'filter' => [
                'PHONE' => $phone
            ]
        ];

        $arResponse = Request::send('crm.lead.list', $arFilter);
        return $arResponse['result'] ? $arResponse['result'] : false;
    }
}