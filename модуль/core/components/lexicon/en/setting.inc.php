<?php
/**
* Settings English Lexicon Entries for mSync
*
* @package msync
* @subpackage lexicon
*/

$_lang['area_msync_main'] = 'Main';
$_lang['area_msync_1c'] = 'Connect to 1c';

$_lang['setting_msync_debug'] = 'Debug mode';
$_lang['setting_msync_debug_desc'] = 'During synchronization of merchandise in the folder logs are saved received and sent requests  REST';
$_lang['setting_msync_1c_sync_login'] = 'Login for CommerceML';
$_lang['setting_msync_1c_sync_login_desc'] = 'Used to establish a connection and synchronization through CommerceML';
$_lang['setting_msync_1c_sync_pass'] = 'Password for CommerceML';
$_lang['setting_msync_1c_sync_pass_desc'] = 'Used to establish a connection and synchronization through CommerceML';
$_lang['setting_msync_price_by_feature_tv'] = 'Tv параметр цены с учетом характеристики';
$_lang['setting_msync_price_by_feature_tv_desc'] = 'Имя параметра, для сохранения цен с учетом характеристики при синхронизации';
$_lang['setting_msync_order_accept_status_id'] = 'Id status of the accepted order';
$_lang['setting_msync_order_accept_status_id_desc'] = 'Installed instead of the status "New" orders processed through the CommerceML';
$_lang['setting_msync_catalog_root_id'] = 'Id shop category';
$_lang['setting_msync_catalog_root_id_desc'] = 'This category will be imported. Default - 0 (root)';
$_lang['setting_msync_catalog_context'] = 'Catalog context';
$_lang['setting_msync_catalog_context_desc'] = 'Context of the catalog in which you are importing.';
$_lang['setting_msync_user_id_import'] = 'User id';
$_lang['setting_msync_user_id_import_desc'] = 'Id user that will be imported. Default - 1. The user must be assigned the rights to create and publish resources.';
$_lang['setting_msync_publish_default'] = 'Published default';
$_lang['setting_msync_publish_default_desc'] = 'Select "Yes" to make all imported resources published by default.';
$_lang['setting_msync_template_product_default'] = 'Default template for new product';
$_lang['setting_msync_template_product_default_desc'] = 'Select template which will be set by default when you creating new product.  When you install borrowed from the settings minishop2.';
$_lang['setting_msync_template_category_default'] = 'Default template for new category';
$_lang['setting_msync_template_category_default_desc'] = 'Select template which will be set by default when you creating new product.';
$_lang['setting_msync_catalog_currency'] = 'Catalog currency';
$_lang['setting_msync_catalog_currency_desc'] = 'By default, the "руб" is used to synchronize through CommerceML';
$_lang['setting_msync_time_limit'] = 'Time limit';
$_lang['setting_msync_time_limit_desc'] = 'Peak performance of one package when importing. The default is "60", display value according to the settings hosting.';
$_lang['setting_msync_create_properties_tv'] = 'Create tv for product properties';
$_lang['setting_msync_create_properties_tv_desc'] = 'Choose «Yes» to automatically created not existed tv for product properties';
$_lang['setting_msync_create_prices_tv'] = 'Create tv for product prices';
$_lang['setting_msync_create_prices_tv_desc'] = 'Choose «Yes» to automatically create not existed tv for product prices';
$_lang['setting_msync_save_properties_to_tv'] = 'Save all product properties to TV';
$_lang['setting_msync_save_properties_to_tv_desc'] = 'The name of TV to save all product properties in JSON';
$_lang['setting_msync_import_all_prices'] = 'Import all prices';
$_lang['setting_msync_import_all_prices_desc'] = 'Choose «Yes» if you want to import all prices instead of first only. Set up price links in component mSync or turn on setting "msync_create_prices_tv".';
$_lang['setting_msync_alias_with_id'] = 'Add product id to alias';
$_lang['setting_msync_alias_with_id_desc'] = 'Choose «Yes» to add product id to alias (solution to the problem with repeated product aliases).';
$_lang['setting_msync_publish_by_quantity'] = 'Publish product by quantity';
$_lang['setting_msync_publish_by_quantity_desc'] = 'Choose «Yes» to publish product by value of its quantity. For proper work you should set up a link "Количество" and turn on setting "msync_publish_default". Works only for one product offer on product.';
$_lang['setting_msync_last_orders_sync'] = 'Last sync datetime';
$_lang['setting_msync_last_orders_sync_desc'] = 'Export orders only after this time. Updated automatically.';
$_lang['setting_msync_orders_delay_time'] = 'Number of seconds to sync orders';
$_lang['setting_msync_orders_delay_time_desc'] = 'Set time of orders to export before last sync. Recommended - 30 seconds.';
$_lang['setting_msync_remove_temp'] = 'Remove temporary files';
$_lang['setting_msync_remove_temp_desc'] = 'Choose «Yes» to remove temporary files while synchronization';
$_lang['setting_msync_category_by_name'] = 'Link categories by name';
$_lang['setting_msync_category_by_name_desc'] = 'Choose «Yes» to link categories 1C to site categories by name (pagetitle). Category tree on site and in 1C should be equal';
$_lang['setting_msync_no_categories'] = 'Do not create categories';
$_lang['setting_msync_no_categories_desc'] = 'Choose «Yes» if you do not want to create categories at all, and all products to be imported to root category with setting msync_catalog_root_id.';
$_lang['setting_msync_hidemenu_by_quantity'] = 'Hide from menu by quantity';
$_lang['setting_msync_hidemenu_by_quantity_desc'] = 'Choose «Yes» if you want to hide products from menu if their quantity is 0';
$_lang['setting_msync_import_only_offers'] = 'Import only offers';
$_lang['setting_msync_import_only_offers_desc'] = 'Choose «Yes» if you want to import only offers by primary features';
$_lang['setting_msync_import_temp_count'] = 'Count of imported categories and products for one iteration';
$_lang['setting_msync_import_temp_count_desc'] = 'For configuration of count of imported objects by server performance.';
$_lang['setting_msync_rewrite_import_files'] = 'Always rewrite import files';
$_lang['setting_msync_rewrite_import_files_desc'] = 'Choose «Yes» if you want always rewrite import files. Choose «No» if your files are too big and cannot be upload at one time.';

