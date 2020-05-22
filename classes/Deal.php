<?php
/**
 * @author Lukmanov Mikhail <lukmanof92@gmail.com>
 * Date: 22.05.2020
 * Time: 4:20
 */

namespace IIT\Entity;

use IIT\Service\Request;

class Deal
{
    public static function getByLeadId($id)
    {
        $arDeal = false;
        $arResponse = Request::send('crm.deal.list', ['filter' => ['LEAD_ID' => $id]]);
        if ($arResponse['result'] && $arResponse['result'][0]) {
            $arDeal = $arResponse['result'][0];

            if ($arDeal['STAGE_ID'][0] == 'C') {
                $pos = strpos($arDeal['STAGE_ID'], ':');
                if ($pos !== false) {
                    $arDeal['CATEGORY_ID'] = str_replace('C', '', substr($arDeal['STAGE_ID'], 0, $pos));
                }
            } else {
                $arDeal['CATEGORY_ID'] = '';
            }
        }
        return $arDeal;
    }
}