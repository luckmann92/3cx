<?php
/**
 * @author Lukmanov Mikhail <lukmanof92@gmail.com>
 * Date: 22.05.2020
 * Time: 9:04
 */

namespace IIT\Entity;


use IIT\Service\Request;

class Activity
{
    public static function add($entityId, $entityType, $arFields = [])
    {
        $arActivityFields = [
            'OWNER_TYPE_ID' => $entityId,
            'OWNER_ID' => $entityType == 'LEAD' ? 1 : 2,
            'TYPE_ID' => 2,
            'COMMUNICATIONS' => [
                [
                    'VALUE' => $arFields['phone'],
                    'ENTITY_ID' => $entityId,
                    'ENTITY_TYPE' => $entityType == 'LEAD' ? 1 : 2
                ]
            ],
            'SUBJECT' => 'Новый звонок'
        ];
        if (!empty($arFields['files'])) {
            foreach ($arFields['files'] as $arFile) {
                $arActivityFields['FILES'][] = [
                    'fileData' => [$arFile]
                ];
            }
        }

        $arResponse = Request::send('crm.activity.add', [
            'fields' => $arActivityFields
        ]);

        if ($arResponse['result']['ID']) {
            return $arResponse['result']['ID'];
        }
        return false;
   }
}