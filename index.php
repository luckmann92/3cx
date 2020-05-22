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

    $arLeads = Lead::getByPhone($arRequest['fields']['phone']);

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

                    $arResponse['history'][] = 'Найден 1 лид id: '.$arLead['ID'];

                    //Если есть сделка
                    if (isset($arLead['DEAL']) && !empty($arLead['DEAL']))
                    {
                        $arResponse['history'][] = 'В лиде id: '.$arLead['ID'].' есть сделка id: '.$arLead['DEAL']['ID'];

                        $typeStatus = Status::getTypeStatus($arLead['DEAL']['STAGE_ID'], 'DEAL');

                        $arResponse['history'][] = 'Тип статуса сделки: '.$typeStatus;

                        switch (Status::getTypeStatus($arLead['DEAL']['STAGE_ID'], 'DEAL')) {
                            //Сделка "в работе"
                            case 'PROCESS':
                                $arActions['IS_DEAL_UPDATE'][] = [
                                    'LEAD_ID' => $arLead['ID'],
                                    'DEAL_ID' => $arLead['DEAL']['ID']
                                ];
                                $arResponse['history'][] = 'Записываем событие обновления сделки id: '.$arLead['DEAL']['ID'];
                                break;

                            //Сделка "успешно завершена" или "проиграна"
                            case 'SUCCESS':
                            case 'UNSUCCESS':
                                $arActions['ADD_NEW_LEAD'] = true;
                                $arResponse['history'][] = 'Записываем событие создания нового лида';
                                break;
                        }
                    }
                    else
                    {
                        $arResponse['history'][] = 'В лиде id: '.$arLead['ID'].' сделок нет';

                        $typeStatus = Status::getTypeStatus($arLead['STATUS_ID']);

                        $arResponse['history'][] = 'Тип статуса лида: '.$typeStatus;

                        switch ($typeStatus) {
                            //Лид "в работе"
                            case 'PROCESS':
                            //Лид "не обработан"
                            case 'APOLOGY':
                                $arActions['IS_LEAD_UPDATE'][] = $arLead['ID'];
                                $arResponse['history'][] = 'Записываем событие обновления лида id: '.$arLead['ID'];
                                break;

                            //Лид "успешно завершен" или "проигран"
                            case 'SUCCESS':
                            case 'FAILURE':
                                $arActions['ADD_NEW_LEAD'] = true;
                                $arResponse['history'][] = 'Записываем событие создания нового лида';
                                break;
                        }
                    }
                }
                //Если лидов больше 1
                else
                {
                    //Группируем по типу статусов
                    $arStatusTypes = [];
                    $arLeadIDs = [];

                    foreach ($arLeads as $arLead)
                    {
                        $arLeadIDs[$arLead['ID']] = $arLead['ID'];

                        $arStatusTypes[Status::getTypeStatus($arLead['STATUS_ID'])][$arLead['ID']] = $arLead['ID'];

                        //Если есть сделки считаем в отдельном типе
                        if ($arLead['DEAL'])
                        {
                            $arStatusTypes['CONVERTED'][$arLead['ID']] = $arLead['DEAL'];
                        }
                    }

                    $arResponse['history'][] = 'Найдены лиды ('.count($arLeads).' шт.) id: '.implode(', ', $arLeadIDs);
                    $arResponse['history'][] = 'Сгруппировали лиды по типу статусов';

                    foreach ($arStatusTypes as $statusType => $IDs) {
                        $arResponse['history'][] = 'Тип статуса: ' . $statusType . ' id лидов: '. implode(', ', $IDs);
                    }

                    //Если все найденные лиды в "забракован"
                    if (count($arStatusTypes['FAILURE']) == count($arLeads))
                    {
                        $arActions['ADD_NEW_LEAD'] = true;

                        $arResponse['history'][] = 'Все найденные лиды в статусе типа "Забракован"';
                        $arResponse['history'][] = 'Записываем событие создания нового лида';
                    }

                    //Если есть в "забракован" и "в работе"
                    elseif (count($arStatusTypes['FAILURE']) > 0 && count($arStatusTypes['PROCESS']) > 0)
                    {
                        $arResponse['history'][] = 'Есть лиды в статусе типа "Забракован" и в "Работе"';

                        //Обновляем все лиды которые в работе
                        foreach ($arStatusTypes['PROCESS'] as $ID => $leadIDs)
                        {
                            $arActions['IS_LEAD_UPDATE'][] = $ID;
                            $arResponse['history'][] = 'Записываем событие обновления лида id: '.$ID;
                        }
                    }

                    //Если все лиды сконвертированы
                    elseif (count($arStatusTypes['CONVERTED']) == count($arLeads))
                    {
                        $arResponse['history'][] = 'Все найденные лиды в статусе типа "Сконвертирован"';
                        foreach ($arStatusTypes['CONVERTED'] as $leadId => $arDeal)
                        {
                            switch (Status::getTypeStatus($arDeal['STAGE_ID'], 'DEAL'))
                            {
                                case 'PROCESS':
                                    $arActions['IS_DEAL_UPDATE'][] = [
                                        'LEAD_ID' => $leadId,
                                        'DEAL_ID' => $arDeal['ID']
                                    ];
                                    $arResponse['history'][] = 'Записываем событие обновления сделки id: '.$arDeal['ID'];
                                    break;
                                case 'SUCCESS':
                                case 'UNSUCCESS':
                                    $arActions['ADD_NEW_LEAD'] = true;
                                    $arResponse['history'][] = 'Записываем событие создания нового лида';
                                    break;
                            }
                        }
                    }

                    //Если есть "сконвентированые" лиды и "забракованные"
                    elseif (count($arStatusTypes['CONVERTED']) > 0 && count($arStatusTypes['FAILURE']) > 0)
                    {
                        $arResponse['history'][] = 'Есть лиды в статусе типа "Сконвертирован" и в "Забракован"';

                        foreach ($arStatusTypes['CONVERTED'] as $leadId => $arDeal)
                        {
                            switch (Status::getTypeStatus($arDeal['STAGE_ID'], 'DEAL'))
                            {
                                case 'PROCESS':
                                    $arActions['IS_DEAL_UPDATE'][] = [
                                        'LEAD_ID' => $leadId,
                                        'DEAL_ID' => $arDeal['ID']
                                    ];
                                    $arResponse['history'][] = 'Записываем событие обновления сделки id: '.$arDeal['ID'];
                                    break;
                                case 'SUCCESS':
                                case 'UNSUCCESS':
                                    $arActions['ADD_NEW_LEAD'] = true;
                                    $arResponse['history'][] = 'Записываем событие создания нового лида';
                                    break;
                            }
                        }
                    }

                    //Если все лиды "в работе"
                    elseif (count($arStatusTypes['PROCESS']) == count($arLeads))
                    {
                        $arResponse['history'][] = 'Все найденные лиды в статусе типа "В работе"';
                        $arResponse['history'][] = 'Записываем событие обновления лида id: '.$arLeads[0]['ID'];

                        $arActions['IS_LEAD_UPDATE'][] = $arLeads[0]['ID'];
                    }

                    //Если есть лиды в "в работе" и "сконвертирован"
                    elseif (count($arStatusTypes['PROCESS']) > 0 && count($arStatusTypes['CONVERTED']) > 0)
                    {
                        $arResponse['history'][] = 'Есть лиды в статусе типа "В работе" и в "Сконвертирован"';

                        foreach ($arStatusTypes['CONVERTED'] as $leadId => $arDeal)
                        {
                            switch (Status::getTypeStatus($arDeal['STAGE_ID'], 'DEAL'))
                            {
                                case 'PROCESS':
                                    $arActions['IS_LEAD_UPDATE'][] = $leadId;
                                    $arActions['IS_DEAL_UPDATE'][] = [
                                        'LEAD_ID' => $leadId,
                                        'DEAL_ID' => $arDeal['ID']
                                    ];
                                    $arResponse['history'][] = 'Записываем событие обновления сделки id: '.$arDeal['ID'];
                                    $arResponse['history'][] = 'Записываем событие обновления лида id: '.$leadId;

                                    break;
                                case 'SUCCESS':
                                case 'UNSUCCESS':
                                    $arActions['IS_LEAD_UPDATE'][] = $arStatusTypes['PROCESS'][0];
                                    $arResponse['history'][] = 'Записываем событие обновления лида id: '.$arStatusTypes['PROCESS'][0];


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

        //Исходящий звонок
        case 'outgoing_call':
            break;
    }

    if (!empty($arActions))
    {
        $arResponse['history'][] = 'Обрабатываем события обновления/добавления сущностей';

        foreach ($arActions as $action => $arValue)
        {
            switch ($action)
            {
                case 'ADD_NEW_LEAD':
                    $arLeadFields = [
                        'STATUS_ID' => 'NEW',
                        'TITLE' => 'Новая заявка: '. $arRequest['fields']['phone'],
                        'PHONE' => [
                            "VALUE" => $arRequest['fields']['phone'],
                            "VALUE_TYPE" => "WORK"
                        ],
                        'SOURCE_ID' => Status::$leadSource
                    ];

                    $response = Lead::add($arLeadFields);
                    if (!$response)
                    {
                        $arResponse['result'] = false;
                        $arResponse['message'][] = Lead::getError();
                        $arResponse['history'][] = Lead::getError();
                    }
                    else
                    {
                        $arResponse['message'][] = "Создан новый лид id: " .$response;
                        $arResponse['history'][] = "Создан новый лид id: " .$response;
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
                            $arResponse['history'][] = Lead::getError();
                        }
                        else
                        {
                            $arResponse['message'][] = "Лид id: " .$leadId. " успешно обновлен!";
                            $arResponse['history'][] = "Лид id: " .$leadId. " успешно обновлен!";
                        }
                    }
                    break;
            }
        }
    }
}
$arResponse['history'][] = 'Обработка закончена';

header('Content-Type: application/json');
echo json_encode($arResponse);