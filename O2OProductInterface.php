<?php

namespace App\Repositories\V1_1\Commerce\Product;

interface O2OProductInterface
{
    public function getAll(array $params = [], $is_exhibitor = false);

    public function getById(int $id);

    public function getByUid($id);

    public function getRelatedProducts($product_uid, $is_exhibitor = false);

    public function getRecommendedProducts($product_uid, $is_exhibitor = false);

    public function createData(array $data, $is_organizer = false);

    public function updateData(int $id, array $data, $is_organizer = false);

    public function destroyData(int $id, $is_organizer = false);
}
