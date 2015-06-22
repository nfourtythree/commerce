<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

class Market_VariantService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Market_VariantModel
     */
    public function getById($id)
    {
        return craft()->elements->getElementById($id, 'Market_Variant');
    }

    /**
     * @param int $id
     */
    public function deleteById($id)
    {
        Market_VariantRecord::model()->deleteByPk($id);
    }

    /**
     * @param int $productId
     */
    public function disableAllByProductId($productId)
    {
        $variants = $this->getAllByProductId($productId);
        foreach ($variants as $variant) {
            $this->disableVariant($variant);
        }
    }

    /**
     * @param int  $id
     * @param bool $isMaster null / true / false. All by default
     *
     * @return Market_VariantModel[]
     */
    public function getAllByProductId($id, $isMaster = null)
    {
        $conditions = ['productId' => $id];
        if (!is_null($isMaster)) {
            $conditions['isMaster'] = $isMaster;
        }

        $variants = Market_VariantRecord::model()->with('product')->findAllByAttributes($conditions);

        return Market_VariantModel::populateModels($variants);
    }

    /**
     * @param $variant
     */
    public function disableVariant($variant)
    {
        $variant            = Market_ProductRecord::model()->findById($variant->id);
        $variant->deletedAt = DateTimeHelper::currentTimeForDb();
        $variant->saveAttributes(['deletedAt']);
    }

    /**
     * Apply sales, associated with the given product, to all given variants
     *
     * @param Market_VariantModel[] $variants
     * @param Market_ProductModel   $product
     */
    public function applySales(array $variants, Market_ProductModel $product)
    {
        $sales = craft()->market_sale->getForProduct($product);

        foreach ($sales as $sale) {
            foreach ($variants as $variant) {
                $variant->salePrice = $variant->price + $sale->calculateTakeoff($variant->price);
                if ($variant->salePrice < 0) {
                    $variant->salePrice = 0;
                }
            }
        }
    }

    /**
     * Save a model into DB
     *
     * @param BaseElementModel $model
     *
     * @return bool
     * @throws \CDbException
     * @throws \Exception
     */
    public function save(BaseElementModel $model)
    {
        if ($model->id) {
            $record = Market_VariantRecord::model()->findById($model->id);

            if (!$record) {
                throw new HttpException(404);
            }
        } else {
            $record = new Market_VariantRecord();
        }

        $record->isMaster  = $model->isMaster;
        $record->productId = $model->productId;
        $record->sku       = $model->sku;
        $record->price     = $model->price;
        $record->width     = $model->width;
        $record->height    = $model->height;
        $record->length    = $model->length;
        $record->weight    = $model->weight;
        $record->minQty    = $model->minQty;
        $record->maxQty    = $model->maxQty;

        if ($model->unlimitedStock) {
            $record->unlimitedStock = true;
            $record->stock          = 0;
        }

        if (!$model->unlimitedStock) {
            $record->stock          = $model->stock ? $model->stock : 0;
            $record->unlimitedStock = false;
        }

        $record->validate();
        $model->addErrors($record->getErrors());

        MarketDbHelper::beginStackedTransaction();
        try {
            if (!$model->hasErrors()) {
                if (craft()->elements->saveElement($model)) {
                    $record->id = $model->id;
                    $record->save(false);
                    MarketDbHelper::commitStackedTransaction();

                    return true;
                }
            }
        } catch (\Exception $e) {
            MarketDbHelper::rollbackStackedTransaction();
            throw $e;
        }

        MarketDbHelper::rollbackStackedTransaction();

        return false;
    }

    /**
     * Update Stock count from completed order
     *
     * @param Event $event
     */
    public function orderCompleteHandler(Event $event)
    {
        /** @var Market_OrderModel $order */
        $order = $event->params['order'];

        foreach ($order->lineItems as $lineItem) {
            /** @var Market_VariantRecord $record */
            $record = Market_VariantRecord::model()->findByAttributes(['id' => $lineItem->purchasableId]);
            if (!$record->unlimitedStock) {
                $record->stock = $record->stock - $lineItem->qty;
                $record->save(false);
            }
        }
    }

}