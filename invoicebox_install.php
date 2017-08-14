<?php

$className = "invoicebox";
$paymentName = "Invoicebox Payment";

include "standalone.php";

$objectTypesCollection = umiObjectTypesCollection::getInstance();
$internalTypeId = $objectTypesCollection->getTypeIdByGUID("emarket-paymenttype");

$sel = new selector('objects');
$sel->types('object-type')->id($internalTypeId);
$sel->where('class_name')->equals($className);
$sel->limit(0, 1);

$bAdd = $sel->length() == 0;

$fieldTypesCollection = umiFieldTypesCollection::getInstance();
$typeBoolean = $fieldTypesCollection->getFieldTypeByDataType('boolean');
$typeString = $fieldTypesCollection->getFieldTypeByDataType('string');
$typeInt = $fieldTypesCollection->getFieldTypeByDataType('int');

if($bAdd) {
    $objectsCollection = umiObjectsCollection::getInstance();

    $parentTypeId = $objectTypesCollection->getTypeIdByGUID("emarket-payment");

    $typeId = $objectTypesCollection->addType($parentTypeId, $paymentName);

    $objectType = $objectTypesCollection->getType($typeId);

    $groupdId = $objectType->addFieldsGroup('settings', 'Параметры', true, true);
    $group = $objectType->getFieldsGroupByName('settings');

    $fieldsCollection = umiFieldsCollection::getInstance();

    $fieldTypesCollection = umiFieldTypesCollection::getInstance();
    $typeBoolean = $fieldTypesCollection->getFieldTypeByDataType('boolean')->getId();
    $typeString = $fieldTypesCollection->getFieldTypeByDataType('string')->getId();
    $typeInt = $fieldTypesCollection->getFieldTypeByDataType('int')->getId();
    $typeSelect = $fieldTypesCollection->getFieldTypeByDataType('relation')->getId();

    $fields = array(
        'payment_type_id' => array(
            'name' => 'Метод оплаты',
            'type' => $typeSelect,
            'guide_id' => $internalTypeId,
            'required' => true,
        ),
        'invoicebox_participant_id' => array(
            'name' => 'ID магазина',
            'tip' => 'Предоставляется системой',
            'type' => $typeString,
            'required' => true,
        ),
		'invoicebox_participant_ident' => array(
            'name' => 'Региональный код магазина',
            'tip' => 'Предоставляется системой',
            'type' => $typeString,
            'required' => true,
        ),
        'invoicebox_api_key' => array(
            'name' => 'Ключ безопасности',
            'tip' => 'Предоставляется системой',
            'type' => $typeString,
            'required' => true,
        ),
		'invoicebox_testmode' => array(
            'name' => 'Тестовый режим',
            'tip' => '1 - включить тестовый режим, 0 - выключить',
            'type' => $typeString,
            'required' => true,
        )
    );

    foreach($fields as $code => $arField) {
        $fieldId = $fieldsCollection->addField($code, $arField['name'], $arField['type']);
        $field = $fieldsCollection->getField($fieldId);

        $isRequired = isset($arField['required']) ? $arField['required'] : false;
        if(isset($arField['guide_id'])){
            $field->setGuideId($arField['guide_id']);
        }
        $field->setIsRequired($isRequired);
        $field->setIsInSearch(false);
        $field->setIsInFilter(false);
        $field->commit();

        $group->attachField($fieldId);
    }

// Создаем внутренний объект
    $internalObjectId = $objectsCollection->addObject($paymentName, $internalTypeId);
    $internalObject = $objectsCollection->getObject($internalObjectId);
    $internalObject->setValue("class_name", $className); // имя класса для реализации

// связываем его с типом
    $internalObject->setValue("payment_type_id", $typeId);
    $internalObject->setValue("payment_type_guid", "emarket-payment-" . $typeId);
    $internalObject->commit();

// Связываем внешний тип и внутренний объект
    $objectType = $objectTypesCollection->getType($typeId);
    $objectType->setGUID($internalObject->getValue("payment_type_guid"));
    $objectType->commit();

    echo "Создан тип данных с id #". $typeId.". Теперь в админке Вы можете создать и настроить способ оплаты Invoicebox. Закройте эту страницу.";
} else {
    echo "Способ оплаты с классом ". $className. " уже существует";
}

unlink(__FILE__);