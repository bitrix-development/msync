<?php
/** @var modX $modx */

define('AUTO_PRICE_DEBUG', true); // сейчас включаем максимальный дебаг

// === Утилита логирования ===
if (!function_exists('ap_log')) {
    function ap_log($msg)
    {
        if (!AUTO_PRICE_DEBUG) {
            return;
        }
        file_put_contents(
            MODX_BASE_PATH . 'debug_autoprice.txt',
            date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL,
            FILE_APPEND
        );
    }
}

// Флаг, чтобы не уйти в рекурсию
if (!isset($modx->autoPriceUpdating)) {
    $modx->autoPriceUpdating = false;
}

// === ФУНКЦИИ ЛОГИКИ ===

/**
 * Обновление цены одного товара по бренд/категориям.
 */
if (!function_exists('ap_updateProductPrice')) {
    function ap_updateProductPrice(modX $modx, $productId)
    {
        $productId = (int)$productId;
        ap_log("ap_updateProductPrice START for product {$productId}");

        if (!empty($modx->autoPriceUpdating)) {
            ap_log("ap_updateProductPrice: autoPriceUpdating flag set, skip");
            return;
        }

        /** @var msProduct $product */
        $product = $modx->getObject('msProduct', $productId);
        if (!$product) {
            ap_log("Product {$productId} not found");
            return;
        }

        /** @var modResource $resource */
        $resource = $modx->getObject('modResource', $productId);
        if (!$resource) {
            ap_log("Resource {$productId} not found");
            return;
        }

        $currentPrice = (float)$product->get('price');
        $oldPrice     = (float)$product->get('old_price');
        $savedPrice   = (float)$resource->getTVValue('price_save');

        ap_log("Product {$productId}: currentPrice={$currentPrice}, oldPrice={$oldPrice}, savedPrice={$savedPrice}");

        if ($currentPrice <= 0) {
            ap_log("Product {$productId}: currentPrice <= 0, skip");
            return;
        }

        $originalPrice = $savedPrice > 0 ? $savedPrice : $currentPrice;

        // --- бренд ---
        $brandIdStr    = $resource->getTVValue('brand');
        $brandId       = (int)$brandIdStr;
        $brandResource = $brandId ? $modx->getObject('modResource', $brandId) : null;
        ap_log("Product {$productId}: brandId={$brandId}");

        // --- категории ---
        $categoryIds = [];
        $tablePrefix = $modx->config['table_prefix'];
        $catTable    = $tablePrefix . 'ms2_product_categories';
        $sql         = "SELECT category_id FROM {$catTable} WHERE product_id = {$productId}";
        $rs          = $modx->query($sql);
        if ($rs) {
            while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                $categoryIds[] = (int)$row['category_id'];
            }
        }
        ap_log("Product {$productId}: categories=[" . implode(',', $categoryIds) . ']');

        $applyDiscount = false;
        $promoPercent  = 0.0;

        // --- скидка по бренду ---
        if ($brandResource) {
            $activePromoValueBrand  = $brandResource->getTVValue('active_promo');
            $promoPercentValueBrand = $brandResource->getTVValue('promo_percent');

            ap_log("Product {$productId}: brand TVs: active_promo={$activePromoValueBrand}, promo_percent={$promoPercentValueBrand}");

            // дублирующая проверка через modTemplateVarResource (как у тебя)
            $qActive = $modx->newQuery('modTemplateVarResource');
            $qActive->where(['contentid' => $brandId, 'tmplvarid' => 34]);
            if ($tv = $modx->getObject('modTemplateVarResource', $qActive)) {
                $activePromoValueBrand = $tv->get('value');
            }

            $qPercent = $modx->newQuery('modTemplateVarResource');
            $qPercent->where(['contentid' => $brandId, 'tmplvarid' => 35]);
            if ($tv = $modx->getObject('modTemplateVarResource', $qPercent)) {
                $promoPercentValueBrand = $tv->get('value');
            }

            ap_log("Product {$productId}: brand TVs (DB): active_promo={$activePromoValueBrand}, promo_percent={$promoPercentValueBrand}");

            $isPromoActiveBrand = $activePromoValueBrand !== null
                && in_array(mb_strtolower(trim($activePromoValueBrand)), ['да', 'yes', 'true', '1'], true);

            if ($isPromoActiveBrand && (float)$promoPercentValueBrand > 0) {
                $applyDiscount = true;
                $promoPercent  = (float)$promoPercentValueBrand;
                ap_log("Product {$productId}: brand discount {$promoPercent}%");
            } else {
                ap_log("Product {$productId}: brand discount not active");
            }
        } else {
            ap_log("Product {$productId}: no brand resource");
        }

        // --- если нет бренда, ищем по категориям ---
        if (!$applyDiscount && !empty($categoryIds)) {
            foreach ($categoryIds as $catId) {
                /** @var modResource $catRes */
                $catRes = $modx->getObject('modResource', $catId);
                if (!$catRes) {
                    ap_log("Product {$productId}: category {$catId} resource not found");
                    continue;
                }

                $activePromoValRel  = $catRes->getTVValue('active_category_price');
                $promoPercentValRel = $catRes->getTVValue('category_percent');

                // ещё раз через modTemplateVarResource (как у тебя)
                $qActive = $modx->newQuery('modTemplateVarResource');
                $qActive->where(['contentid' => $catId, 'tmplvarid' => 36]);
                if ($tv = $modx->getObject('modTemplateVarResource', $qActive)) {
                    $activePromoValRel = $tv->get('value');
                }

                $qPercent = $modx->newQuery('modTemplateVarResource');
                $qPercent->where(['contentid' => $catId, 'tmplvarid' => 37]);
                if ($tv = $modx->getObject('modTemplateVarResource', $qPercent)) {
                    $promoPercentValRel = $tv->get('value');
                }

                ap_log("Product {$productId}: category {$catId} TVs: active={$activePromoValRel}, percent={$promoPercentValRel}");

                $isActiveRel = $activePromoValRel !== null
                    && in_array(mb_strtolower(trim($activePromoValRel)), ['да', 'yes', 'true', '1'], true);

                if ($isActiveRel && (float)$promoPercentValRel > 0) {
                    $applyDiscount = true;
                    $promoPercent  = (float)$promoPercentValRel;
                    ap_log("Product {$productId}: category {$catId} discount {$promoPercent}%");
                    break;
                }
            }
        }

        // Применение
        if ($applyDiscount && $promoPercent > 0) {
            $discountedPrice = ceil($originalPrice - ($originalPrice * ($promoPercent / 100)));
            /** @var msProductData $productData */
            $productData = $product->getOne('Data');
            if ($productData) {
                $productData->set('price', $discountedPrice);
                $productData->save();
            }
            $product->set('old_price', $originalPrice);
            $product->save();

            ap_log("Product {$productId}: {$originalPrice} -> {$discountedPrice} ({$promoPercent}%)");
        } else {
            // Без скидки: возвращаем price из TV 'price_save' или оставляем текущую
            $restorePrice = (float)$resource->getTVValue('price_save');
            if ($restorePrice <= 0) {
                $restorePrice = $currentPrice;
            }
            $product->set('price', $restorePrice);
            $product->set('old_price', 0);
            $product->save();

            ap_log("Product {$productId}: restore price {$restorePrice} (no discounts)");
        }

        ap_log("ap_updateProductPrice END for product {$productId}");
    }
}

/**
 * Обновление всех товаров бренда.
 */
if (!function_exists('ap_updateProductsByBrand')) {
    function ap_updateProductsByBrand(modX $modx, $brandId)
    {
        $brandId = (int)$brandId;
        ap_log("ap_updateProductsByBrand START for brand {$brandId}");

        if (!empty($modx->autoPriceUpdating)) {
            ap_log("ap_updateProductsByBrand: autoPriceUpdating flag set, skip");
            return;
        }
        $modx->autoPriceUpdating = true;

        $tvBrand = $modx->getObject('modTemplateVar', ['name' => 'brand']);
        $tvId    = $tvBrand ? (int)$tvBrand->get('id') : 34;

        $tablePrefix = $modx->config['table_prefix'];
        $sql         = "SELECT contentid FROM {$tablePrefix}site_tmplvar_contentvalues
                        WHERE tmplvarid = {$tvId} AND value = {$brandId}";
        $rs = $modx->query($sql);
        if ($rs) {
            while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                $prodId = (int)$row['contentid'];
                ap_log("Brand {$brandId}: update product {$prodId}");
                ap_updateProductPrice($modx, $prodId);
            }
        }

        $modx->autoPriceUpdating = false;
        ap_log("ap_updateProductsByBrand END for brand {$brandId}");
    }
}

/**
 * Обновление всех товаров категории.
 */
if (!function_exists('ap_updateProductsByCategory')) {
    function ap_updateProductsByCategory(modX $modx, $categoryId)
    {
        $categoryId = (int)$categoryId;
        ap_log("ap_updateProductsByCategory START for category {$categoryId}");

        if (!empty($modx->autoPriceUpdating)) {
            ap_log("ap_updateProductsByCategory: autoPriceUpdating flag set, skip");
            return;
        }
        $modx->autoPriceUpdating = true;

        $tablePrefix = $modx->config['table_prefix'];
        $tableName   = $tablePrefix . 'ms2_product_categories';
        $sql         = "SELECT product_id FROM {$tableName} WHERE category_id = {$categoryId}";
        $rs = $modx->query($sql);
        if ($rs) {
            while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                $prodId = (int)$row['product_id'];
                ap_log("Category {$categoryId}: update product {$prodId}");
                ap_updateProductPrice($modx, $prodId);
            }
        }

        $modx->autoPriceUpdating = false;
        ap_log("ap_updateProductsByCategory END for category {$categoryId}");
    }
}

// === ФИЛЬТРЫ КОНТЕКСТА ===

// Не трогаем импорты 1С / mSync
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '1c_exchange.php') !== false) {
    ap_log('Skip in 1c_exchange context');
    return;
}
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'assets/components/msync') !== false) {
    ap_log('Skip in mSync connector context');
    return;
}

ap_log('PLUGIN LOADED');

// === ОБРАБОТКА СОБЫТИЯ ===

if ($modx->event->name === 'OnDocFormSave') {
    // В MODX 2.x параметры события в массиве params
    $params   = $modx->event->params;
    $resource = isset($params['resource']) && $params['resource'] instanceof modResource
        ? $params['resource']
        : (isset($modx->event->object) && $modx->event->object instanceof modResource
            ? $modx->event->object
            : null);

    if (!$resource) {
        ap_log('OnDocFormSave: no resource object, exit');
        return;
    }

    $id       = (int)$resource->get('id');
    $classKey = $resource->get('class_key');
    $mode     = isset($params['mode']) ? $params['mode'] : 'upd';

    ap_log("OnDocFormSave: id={$id}, class_key={$classKey}, mode={$mode}");

    // Если сохранён товар miniShop2
    if ($classKey === 'msProduct') {
        ap_updateProductPrice($modx, $id);
    } else {
        // Бренд
        $activePromo = $resource->getTVValue('active_promo');
        $isBrand     = ($classKey === 'modDocument'
            && $activePromo !== null
            && trim($activePromo) !== '');
        ap_log("OnDocFormSave: activePromo=" . var_export($activePromo, true) . ", isBrand=" . ($isBrand ? 'true' : 'false'));

        if ($isBrand) {
            ap_updateProductsByBrand($modx, $id);
        }

        // Категория
        $activePromoCat = $resource->getTVValue('active_category_price');
        $isCategory     = ($classKey === 'msCategory'
            && $activePromoCat !== null
            && trim($activePromoCat) !== '');
        ap_log("OnDocFormSave: activePromoCat=" . var_export($activePromoCat, true) . ", isCategory=" . ($isCategory ? 'true' : 'false'));

        if ($isCategory) {
            ap_updateProductsByCategory($modx, $id);
        }
    }
}
