<?php
/**
 * Settings Russian Lexicon Entries for mSync
 *
 * @package msync
 * @subpackage lexicon
 */

$_lang['area_msync_main'] = 'Основные';
$_lang['area_msync_1c'] = 'Подключение к 1c';

$_lang['setting_msync_debug'] = 'Режим отладки';
$_lang['setting_msync_debug_desc'] = 'Во время синхронизации справочника товаров в папку logs сохраняются полученные и отправленные REST запросы';
$_lang['setting_msync_1c_sync_login'] = 'Логин для CommerceML';
$_lang['setting_msync_1c_sync_login_desc'] = 'Используется для установления соединения и синхронизации посредством CommerceML';
$_lang['setting_msync_1c_sync_pass'] = 'Пароль для CommerceML';
$_lang['setting_msync_1c_sync_pass_desc'] = 'Используется для установления соединения и синхронизации посредством CommerceML';
$_lang['setting_msync_price_by_feature_tv'] = 'Tv параметр цены с учетом характеристики';
$_lang['setting_msync_price_by_feature_tv_desc'] = 'Имя параметра, для сохранения цен с учетом характеристики при синхронизации';
$_lang['setting_msync_order_accept_status_id'] = 'Id статуса обработанного заказа';
$_lang['setting_msync_order_accept_status_id_desc'] = 'Устанавливается вместо статуса "Новый" заказам обработанным посредством CommerceML';
$_lang['setting_msync_catalog_root_id'] = 'Id категории каталога';
$_lang['setting_msync_catalog_root_id_desc'] = 'В эту категорию будет производиться импорт. По умолчанию - 0 (корень)';
$_lang['setting_msync_catalog_context'] = 'Контекст каталога';
$_lang['setting_msync_catalog_context_desc'] = 'Контекст каталога, в который производится импорт.';
$_lang['setting_msync_user_id_import'] = 'Id пользователя';
$_lang['setting_msync_user_id_import_desc'] = 'Id пользователя от имени которого будет производится импорт. По умолчанию - 1. Пользователю должны быть назначены права на создание и публикацию ресурсов.';
$_lang['setting_msync_publish_default'] = 'Публиковать по умолчанию';
$_lang['setting_msync_publish_default_desc'] = 'Выберите «Да» если хотите, чтобы все импортированные ресурсы сразу становились опубликованными';
$_lang['setting_msync_template_product_default'] = 'Шаблон по умолчанию для новых товаров';
$_lang['setting_msync_template_product_default_desc'] = 'Выберете шаблон, который будет установлен по умолчанию при создании товара.  При установке берется из настроек minishop2.';
$_lang['setting_msync_template_category_default'] = 'Шаблон по умолчанию для новых категорий';
$_lang['setting_msync_template_category_default_desc'] = 'Выберете шаблон, который будет установлен по умолчанию при создании категории.';
$_lang['setting_msync_catalog_currency'] = 'Валюта каталога';
$_lang['setting_msync_catalog_currency_desc'] = 'По умолчанию "руб", используется для синхронизации посредством CommerceML';
$_lang['setting_msync_time_limit'] = 'Лимит выполнения по времени';
$_lang['setting_msync_time_limit_desc'] = 'Лимит выполнения по времени одного пакета при импорте. По умолчанию "5". Данная настройка не связана со временем выполнения скрипта на хостинге. Она ограничивает время одного из многих запросов синхронизации и влияет на то, сколько товаров успеет пройти за один запрос. Крайне не рекомендуется ставить на обычных хостингах значение больше 5 секунд, т.к. чем больше товаров, тем больше памяти на запрос потребуется.';
$_lang['setting_msync_create_properties_tv'] = 'Создавать tv под свойства';
$_lang['setting_msync_create_properties_tv_desc'] = 'Выберите «Да» если хотите, чтобы автоматически создавались недостающие tv для свойств товара';
$_lang['setting_msync_create_prices_tv'] = 'Создавать tv под цены';
$_lang['setting_msync_create_prices_tv_desc'] = 'Выберите «Да» если хотите, чтобы автоматически создавались недостающие tv для цен товара';
$_lang['setting_msync_save_properties_to_tv'] = 'Сохранять все свойства товара в одно tv';
$_lang['setting_msync_save_properties_to_tv_desc'] = 'Имя параметра, для сохранения всех свойств товара в формате JSON';
$_lang['setting_msync_import_all_prices'] = 'Импортировать все цены';
$_lang['setting_msync_import_all_prices_desc'] = 'Выберите «Да» если хотите, чтобы импортировались все цены товара, а не только первая. Настройте связи цен в компоненте mSync или включите настройку msync_create_prices_tv.';
$_lang['setting_msync_alias_with_id'] = 'Добавление к псевдониму id товара';
$_lang['setting_msync_alias_with_id_desc'] = 'Выберите «Да» если хотите, чтобы к псевдониму товара добавлялся id (решает проблему повторяющихся псевдонимов).';
$_lang['setting_msync_publish_by_quantity'] = 'Публиковать товар если есть в наличии';
$_lang['setting_msync_publish_by_quantity_desc'] = 'Выберите «Да» если хотите, чтобы товар публиковался в зависимости от наличия. Для работы необходима настроенная в компоненте, связь параметра "Количество" и включенную настройку "msync_publish_default". Работает только для одного товарного предложения каждому товару.';
$_lang['setting_msync_last_orders_sync'] = 'Дата последней синхронизации';
$_lang['setting_msync_last_orders_sync_desc'] = 'При синхронизации выбираются только те заказы которые были созданы или изменены после указанной даты.';
$_lang['setting_msync_orders_delay_time'] = 'Кол-во секунд обработки заказов до синхронизации';
$_lang['setting_msync_orders_delay_time_desc'] = 'Установите время, за которое выгружать заказы, появившиеся во время предыдущей синхронизации заказов. Рекомендуется - 30 секунд.';
$_lang['setting_msync_remove_temp'] = 'Удалять временные файлы';
$_lang['setting_msync_remove_temp_desc'] = 'Выберите «Да» если хотите, чтобы временные файлы удалялись из каталога 1c_temp во время синхронизации';
$_lang['setting_msync_category_by_name'] = 'Сопоставлять категории по имени';
$_lang['setting_msync_category_by_name_desc'] = 'Выберите «Да» если хотите, чтобы категории 1С сопоставлялись с категориями сайта по имени (pagetitle). Дерево категорий на сайте и в 1С должны совпадать.';
$_lang['setting_msync_no_categories'] = 'Не создавать категории';
$_lang['setting_msync_no_categories_desc'] = 'Выберите «Да» если хотите, чтобы категории не создавались совсем, а все товары грузились в одну категорию, выбранную в настройке msync_catalog_root_id.';
$_lang['setting_msync_hidemenu_by_quantity'] = 'Убирать из меню при отсутствии';
$_lang['setting_msync_hidemenu_by_quantity_desc'] = 'Выберите «Да» если хотите, чтобы товары убирались из меню при нулевых остатках и наоборот.';
$_lang['setting_msync_import_only_offers'] = 'Импортировать только торговые предложения';
$_lang['setting_msync_import_only_offers_desc'] = 'Выберите «Да» если хотите, чтобы импортировались только торговые предложения по ключевым характеристикам (цены и остатки, категории и товары не создаются).';
$_lang['setting_msync_import_temp_count'] = 'Количество импортируемых категорий и товаров за одну итерацию';
$_lang['setting_msync_import_temp_count_desc'] = 'Для подстройки количества импортируемых объектов под мощности сервера.';
$_lang['setting_msync_rewrite_import_files'] = 'Всегда перезаписывать импортируемые файлы';
$_lang['setting_msync_rewrite_import_files_desc'] = 'Выберите «Да», если хотите, чтобы импортируемые файлы всегда перезаписывались. Выберите «Нет», если ваши файлы слишком велики, чтобы быть загруженными за 1 раз.';

