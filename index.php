<?php
/**
 * @author Lukmanov Mikhail <lukmanof92@gmail.com>
 */

use IIT\Entity\Lead,
    IIT\Entity\Deal,
    IIT\Entity\Status;

include_once 'config.php';

$arResponse = [
    'result' => true
];

$arRequest = (array)json_decode(file_get_contents("php://input"), true);

if (isset($arRequest) && isset($arRequest['action'])) {
    $arActions = [];

    $arLeads = Lead::getByPhone($arRequest['phone']);

    if ($arLeads) {
        foreach ($arLeads as $k => $arLead) {
            $arDeal = Deal::getByLeadId($arLead['ID']);
            if ($arDeal) {
                $arLeads[$k]['DEAL'] = $arDeal;
            }
        }
    }

    switch ($arRequest['action'])
    {
        //Входящий звонок
        case 'incoming_call':
            if (is_array($arLeads))
            {
                //Загружаем статусы лидов и сделок
                Status::loadStatus('DEAL');
                Status::loadStatus('LEAD');

                //Если найден один лид
                if (count($arLeads) == 1)
                {
                    $arLead = current($arLeads);

                    //Если есть сделка
                    if (isset($arLead['DEAL']) && !empty($arLead['DEAL']))
                    {
                        switch (Status::getTypeStatus($arLead['DEAL']['STAGE_ID'], 'DEAL')) {
                            //Сделка "в работе"
                            case 'PROCESS':
                                $arActions['IS_DEAL_UPDATE'][] = [
                                    'LEAD_ID' => $arLead['ID'],
                                    'DEAL_ID' => $arLead['DEAL']['ID']
                                ];
                                break;

                            //Сделка "успешно завершена" или "проиграна"
                            case 'SUCCESS':
                            case 'UNSUCCESS':
                                $arActions['ADD_NEW_LEAD'] = true;
                                break;
                        }
                    }
                    else
                    {
                        switch (Status::getTypeStatus($arLead['STATUS_ID'])) {
                            //Лид "в работе"
                            case 'PROCESS':
                            //Лид "не обработан"
                            case 'APOLOGY':
                                $arActions['IS_LEAD_UPDATE'][] = $arLead['ID'];
                                break;

                            //Лид "успешно завершен" или "проигран"
                            case 'SUCCESS':
                            case 'FAILURE':
                                $arActions['ADD_NEW_LEAD'] = true;
                                break;
                        }
                    }
                }
                //Если лидов больше 1
                else
                {
                    //Группируем по типу статусов
                    $arStatusTypes = [];

                    foreach ($arLeads as $arLead)
                    {
                        $arStatusTypes[Status::getTypeStatus($arLead['STATUS_ID'])][$arLead['ID']] = $arLead['ID'];

                        //Если есть сделки считаем в отдельном типе
                        if ($arLead['DEAL'])
                        {
                            $arStatusTypes['CONVERTED'][$arLead['ID']] = $arLead['DEAL'];
                        }
                    }

                    //Если все найденные лиды в "забракован"
                    if (count($arStatusTypes['FAILURE']) == count($arLeads))
                    {
                        $arActions['ADD_NEW_LEAD'] = true;
                    }

                    //Если есть в "забракован" и "в работе"
                    elseif (count($arStatusTypes['FAILURE']) > 0 && count($arStatusTypes['PROCESS']) > 0)
                    {
                        //Обновляем все лиды которые в работе
                        foreach ($arStatusTypes['PROCESS'] as $ID => $leadIDs)
                        {
                            $arActions['IS_LEAD_UPDATE'][] = $ID;
                        }
                    }

                    //Если все лиды сконвертированы
                    elseif (count($arStatusTypes['CONVERTED']) == count($arLeads))
                    {
                        foreach ($arStatusTypes['CONVERTED'] as $leadId => $arDeal)
                        {
                            switch (Status::getTypeStatus($arDeal['STAGE_ID'], 'DEAL'))
                            {
                                case 'PROCESS':
                                    $arActions['IS_DEAL_UPDATE'][] = [
                                        'LEAD_ID' => $arLead['ID'],
                                        'DEAL_ID' => $arLead['DEAL']['ID']
                                    ];

                                    break;
                                case 'SUCCESS':
                                case 'UNSUCCESS':
                                    $arActions['ADD_NEW_LEAD'] = true;

                                    break;
                            }
                        }
                    }

                    //Если есть "сконвентированые" лиды и "забракованные"
                    elseif (count($arStatusTypes['CONVERTED']) > 0 && count($arStatusTypes['FAILURE']) > 0)
                    {
                        foreach ($arStatusTypes['CONVERTED'] as $leadId => $arDeal)
                        {
                            switch (Status::getTypeStatus($arDeal['STAGE_ID'], 'DEAL'))
                            {
                                case 'PROCESS':
                                    $arActions['IS_DEAL_UPDATE'][] = [
                                        'LEAD_ID' => $arLead['ID'],
                                        'DEAL_ID' => $arLead['DEAL']['ID']
                                    ];

                                    break;
                                case 'SUCCESS':
                                case 'UNSUCCESS':
                                    $arActions['ADD_NEW_LEAD'] = true;

                                    break;
                            }
                        }
                    }

                    //Если все лиды "в работе"
                    elseif (count($arStatusTypes['PROCESS']) == count($arLeads))
                    {
                        $arActions['IS_LEAD_UPDATE'][] = $arLeads[0]['ID'];
                    }

                    //Если есть лиды в "в работе" и "сконвертирован"
                    elseif (count($arStatusTypes['PROCESS']) > 0 && count($arStatusTypes['CONVERTED']) > 0)
                    {
                        foreach ($arStatusTypes['CONVERTED'] as $leadId => $arDeal)
                        {
                            switch (Status::getTypeStatus($arDeal['STAGE_ID'], 'DEAL'))
                            {
                                case 'PROCESS':
                                    $arActions['IS_LEAD_UPDATE'][] = $leadId;

                                    break;
                                case 'SUCCESS':
                                case 'UNSUCCESS':
                                    $arActions['IS_LEAD_UPDATE'][] = $arStatusTypes['PROCESS'][0];

                                    $arActions['ADD_NEW_LEAD'] = true;

                                    break;
                            }
                        }
                    }
                }
            }
            else
            {
                $arResponse['result'] = false;
                $arResponse['message'][] = 'Лид не найден';
            }
            break;
    }

    if (!empty($arActions))
    {
        foreach ($arActions as $action => $arValue)
        {
            switch ($action)
            {
                case 'ADD_NEW_LEAD':
                    $arLeadFields = [
                        'STATUS_ID' => 'NEW',
                        'TITLE' => 'Новая заявка: '. $arRequest['phone'],
                        'PHONE' => [
                            "VALUE" => $arRequest['phone'],
                            "VALUE_TYPE" => "WORK"
                        ],
                        'SOURCE_ID' => Status::$leadSource
                    ];

                    $response = Lead::add($arLeadFields);
                    if (!$response)
                    {
                        $arResponse['result'] = false;
                        $arResponse['message'][] = Lead::getError();
                    }
                    else
                    {
                        $arResponse['message'][] = "Создан новый лид id: " .$response;
                    }
                    break;
                case 'IS_LEAD_UPDATE':
                    foreach ($arValue as $leadId)
                    {
                        $arLeadFields = [
                            'STATUS_ID' => 'NEW'
                        ];

                        $response = Lead::update($leadId, $arLeadFields);
                        if (!$response)
                        {
                            $arResponse['result'] = false;
                            $arResponse['message'][] = Lead::getError();
                        }
                        else
                        {
                            $arResponse['message'][] = "Лид id: " .$leadId. " успешно обновлен!";
                        }
                    }
                    break;
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($arResponse);