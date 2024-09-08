<?php
/**
 * Скрипт за конвертиране на cPanel имейл филтри (filter.yaml) в Sieve скриптове.
 * 
 * Използвайте: Поставете вашия filter.yaml в директорията и стартирайте скрипта.
 */

// Път към cPanel filter.yaml файл
$inputFile = 'filter.yaml';

// Път за запазване на изходния Sieve скрипт
$outputFile = 'converted.sieve';

// Четене на YAML файла (изисква инсталирането на yaml extension или библиотека)
if (!file_exists($inputFile)) {
    die("Файлът filter.yaml не е намерен!\n");
}

// Четене на YAML файла
$yamlContent = file_get_contents($inputFile);

// Преобразуване на YAML в PHP масив
$filters = yaml_parse($yamlContent);

// Отваряме файла за запис на Sieve скрипта
$output = fopen($outputFile, 'w');

// Проверка за грешка при отваряне на файла
if (!$output) {
    die("Грешка при създаването на изходния Sieve файл!\n");
}

// Писане на Sieve заглавка
fwrite($output, "require [\"fileinto\"];\n\n");

// Преобразуване на всеки филтър от cPanel в Sieve
foreach ($filters['filter'] as $filter) {
    if (isset($filter['filtername'])) {
        // Писане на заглавие на филтъра
        fwrite($output, "# rule:[{$filter['filtername']}]\n");

        // Създаване на if блок
        $conditions = [];
        foreach ($filter['rules'] as $rule) {
            $part = $rule['part'];
            $val = $rule['val'];
            $match = $rule['match'];
            
            $operator = ($match === 'contains') ? ':contains' : ':is';
            $part = str_replace('$header_subject:', 'header :contains "subject"', $part);
            $part = str_replace('$header_from:', 'header :contains "from"', $part);
            $part = str_replace('$message_body', 'body', $part);

            $conditions[] = "$part \"$val\"";
        }
        
        if ($conditions) {
            $conditionString = implode(",\n        ", $conditions);
            fwrite($output, "if anyof (\n        $conditionString) {\n");
            
            // Действие - преместване в папка или отказ
            foreach ($filter['actions'] as $action) {
                if ($action['action'] === 'save') {
                    $folder = isset($action['dest']) ? $action['dest'] : 'INBOX';
                    fwrite($output, "    fileinto \"$folder\";\n");
                } elseif ($action['action'] === 'fail') {
                    $message = isset($action['dest']) ? $action['dest'] : 'Message rejected';
                    fwrite($output, "    reject \"$message\";\n");
                }
            }
            
            // Затваряне на if блока
            fwrite($output, "}\n\n");
        }
    }
}

// Затваряне на файла
fclose($output);

echo "Конвертирането е завършено. Проверете файла $outputFile.\n";
?>

