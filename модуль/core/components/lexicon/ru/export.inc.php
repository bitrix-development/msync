<?php
/**
 * Export Russian Lexicon Entries for mSync
 *
 * @package msync
 * @subpackage lexicon
 */

$_lang['msync_export_msg'] = 'Внимание! При синхронизации через .csv файл, не сохраняется привязка у категорий. При последующей синхронизации посредством API категории будут продублированы.';

$_lang['msync_export_prepare'] = 'Экспорт товаров в .csv';
$_lang['msync_export_limit'] = 'Максимум товаров за раз';
$_lang['msync_export_prepare_from'] = 'из';
$_lang['msync_export_prepare_ok'] = 'Экспорт завершен';
$_lang['msync_export_file'] = 'Получение ссылки на файл';
$_lang['msync_export_download'] = 'Скачать:';

$_lang['msync_export_err_no_product'] = 'Не найдено товаров для экспорта.';
$_lang['msync_export_err_no_name'] = 'Не задана дата файла.';

$_lang['msync_import_msg'] = 'Для ручного импорта товаров (без 1С) загрузите XML файлы с каталогом и торговыми
предложениями с помощью кнопки "Загрузить файлы". Если картинки находятся в отдельных каталогах, то загрузите их
с помощью дерева файлов MODX в папку assets/components/msync/1c_temp. Затем нажмите на кнопку "Ручной импорт" и
дождитесь окончания процесса.';
$_lang['msync_import_filename'] = 'Файл каталога товаров';
$_lang['msync_offers_filename'] = 'Файл предложений';

$_lang['msync_show_sales_xml'] = 'Показать XML заказов';