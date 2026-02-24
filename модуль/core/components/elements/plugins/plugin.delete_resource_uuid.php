<?php
if ($modx->event->name == 'OnBeforeEmptyTrash') {
    $mSync = $modx->getService('msync','mSync', $modx->getOption('core_path').'components/msync/model/msync/');
    $mSync->initialize($modx->context->key);

    $deletedids = $modx->event->params['ids'];
    foreach ($deletedids as $resourceid) {
        if( $category = $modx->getObject( 'mSyncCategoryData', array('category_id' => $resourceid)) ){
            $category->remove();
        }
        if( $product = $modx->getObject( 'mSyncProductData', array('product_id' => $resourceid)) ){
            $product->remove();
        }
        if( $offer = $modx->getObject( 'mSyncOfferData', array('data_id' => $resourceid)) ){
            $offer->remove();
        }
    }
}
return;