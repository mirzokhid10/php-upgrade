<?php

use app\models\Region;

function identity() {
    return Yii::$app->user->identity;
}

function lang() {
    return Yii::$app->language;
}

function role() {
    $name = 'name_' . lang();
    $role = '';
    $identity = identity();
    $region = Region::findOne(identity()->region_id);

    if ($identity->isAdmin) {
        $role = $identity->fullname . '(' . Yii::t('app', 'Administrator') . ')';
    } elseif ($identity->isCourier) {
        $role = $region->$name . '(' . Yii::t('app', 'Courier') . ')';
    } elseif ($identity->isAgent) {
        $role = $region->$name . '(' . Yii::t('app', 'Agent') . ')';
    } elseif ($identity->isLegal) {
        $role = Yii::t('app', 'Legal Client') . "(" . $identity->username . ")";
    }
    return $role;
}

function getCurrentYear() {
    return date('Y');
}

function getCurrentMonth() {
    return date('m');
}

function getCurrentDay() {
    return date('d');
}

function getCurrentDate() {
    return date('Y-m-d');
}

function month() {
    $lang = lang();

    switch ($lang) {
        case 'uz':
            return [
                '01' => 'Yanvar',
                '02' => 'Fevral',
                '03' => 'Mart',
                '04' => 'Aprel',
                '05' => 'May',
                '06' => 'Iyun',
                '07' => 'Iyul',
                '08' => 'Avgust',
                '09' => 'Sentyabr',
                '10' => 'Oktyabr',
                '11' => 'Noyabr',
                '12' => 'Dekabr'
            ];
        break;
        case 'en':
            return [
                '01' => 'January',
                '02' => 'February',
                '03' => 'March',
                '04' => 'April',
                '05' => 'May',
                '06' => 'June',
                '07' => 'July',
                '08' => 'August',
                '09' => 'September',
                '10' => 'October',
                '11' => 'November',
                '12' => 'December'
            ];
        break;
        default:
            return [
                '01' => 'Январь',
                '02' => 'Февраль',
                '03' => 'Март',
                '04' => 'Апрель',
                '05' => 'Май',
                '06' => 'Июнь',
                '07' => 'Июль',
                '08' => 'Август',
                '09' => 'Сентябрь',
                '10' => 'Октябрь',
                '11' => 'Ноябрь',
                '12' => 'Декабрь'
            ];
        break;
    }
}

function month_for_day() {
    return [
        '01' => 'Января',
        '02' => 'Февраля',
        '03' => 'Марта',
        '04' => 'Апреля',
        '05' => 'Мая',
        '06' => 'Июня',
        '07' => 'Июля',
        '08' => 'Августа',
        '09' => 'Сентября',
        '10' => 'Октября',
        '11' => 'Ноября',
        '12' => 'Декабря'
    ];
}

function cyrillic_to_latin($text) {

    $cyrillic = array('а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у',
        'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я',
        'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У',
        'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я');

    $latin = array('a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u',
        'f', 'h', 'ts', 'ch', 'sh', 'sht', 'i', 'y', 'y', 'e', 'yu', 'ya', 'A', 'B', 'V', 'G', 'D', 'E', 'E', 'Zh',
        'Z', 'I', 'Y', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U',
        'F', 'H', 'Ts', 'Ch', 'Sh', 'Sht', 'I', 'Y', 'Y', 'E', 'Yu', 'Ya');

    $result = str_replace($cyrillic, $latin, $text);
    return $result;
}

function latin_to_cyrillic($text) {

    $cyrillic = array('а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у',
        'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я',
        'А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У',
        'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я');

    $latin = array('a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u',
        'f', 'h', 'ts', 'ch', 'sh', 'sht', 'i', 'y', 'y', 'e', 'yu', 'ya', 'A', 'B', 'V', 'G', 'D', 'E', 'E', 'Zh',
        'Z', 'I', 'Y', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U',
        'F', 'H', 'Ts', 'Ch', 'Sh', 'Sht', 'I', 'Y', 'Y', 'E', 'Yu', 'Ya');

    $result = str_replace($latin, $cyrillic, $text);
    return $result;
}

function number_to_words($number) {
    $f = new NumberFormatter("ru", NumberFormatter::SPELLOUT);
    return $f->format($number);
}
?>
