<?php
/**
 * @author Lukmanov Mikhail <lukmanof92@gmail.com>
 * Date: 22.05.2020
 * Time: 2:37
 */

namespace IIT\Entity;

use IIT\Service\Request;

class Status
{
    public static $arStatus = [];
    public static $leadSource = 9;

    public static function loadStatus($entityType = 'LEAD')
    {
        switch ($entityType) {
            case 'LEAD':
                $arLeadStatus = Request::send('crm.status.list', ['filter' => ['ENTITY_ID' => 'STATUS']]);
                foreach ($arLeadStatus['result'] as $arStatus) {
                    self::$arStatus[$entityType][strtoupper($arStatus['EXTRA']['SEMANTICS'])][$arStatus['STATUS_ID']] = $arStatus;
                }
                break;
            case 'DEAL':
                $arStatusTypes = Request::send('crm.status.entity.types');
                foreach ($arStatusTypes['result'] as $arStatusType) {
                    if (strpos($arStatusType['ID'], 'DEAL_STAGE') !== false) {
                        $arDealStatus = Request::send('crm.status.list', ['filter' => ['ENTITY_ID' => $arStatusType['ID']]]);
                        foreach ($arDealStatus['result'] as $arStatus) {
                            self::$arStatus[$entityType][strtoupper($arStatus['EXTRA']['SEMANTICS'])][$arStatus['STATUS_ID']] = $arStatus;
                        }
                    }
                }
                break;
        }

        return self::$arStatus[$entityType];
    }

    public static function getTypeStatus($statusId, $entityType = 'LEAD')
    {
        foreach (self::$arStatus[$entityType] as $typeStatus => $arStatuses) {
            if (isset($arStatuses[$statusId])) {
                return $typeStatus;
            }
        }
        return false;
    }

    public static function getByName($type, $statusName)
    {
        $arStatuses = Status::getList($type);
        if ($statusName && $arStatuses) {
            foreach ($arStatuses as $arStatus) {
                if (strpos($arStatus['NAME'], $statusName) !== false) {
                    return $arStatus;
                }
            }
        }
        return false;
    }

    public static function getList($type)
    {
        $arSources = Request::send('crm.status.list', [
            'filter' => [
                'ENTITY_ID' => $type
            ]
        ]);
        return $arSources['result'] ? $arSources['result'] : false;
    }
}