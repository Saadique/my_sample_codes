<?php

namespace App\Repositories\V1_1\Commerce\Product;

use App\Enums\V1_1\Commerce\AttributeType;
use App\Enums\V1_1\Commerce\ProductStatus;
use App\Enums\V1_1\Commerce\ProductType;
use App\Events\V1_1\Commerce\ProductCreateEvent;
use App\Events\V1_1\Commerce\ProductDeleteEvent;
use App\Events\V1_1\Commerce\ProductUpdateEvent;
use App\Models\V1_1\Catalog\Category;
use App\Models\V1_1\Commerce\Collection;
use App\Models\V1_1\Commerce\Option;
use App\Models\V1_1\Commerce\OptionVariant;
use App\Models\V1_1\Commerce\Product;
use App\Models\V1_1\Commerce\ProductView;
use App\Models\V1_1\Network\Business;
use App\Repositories\V1_1\Commerce\Document\O2ODocumentInterface;
use App\Repositories\V1_1\Commerce\Image\O2OImageInterface;
use App\Repositories\V1_1\Commerce\Metadata\O2OMetadataInterface;
use App\Repositories\V1_1\Commerce\Option\O2OOptionInterface;
use App\Repositories\V1_1\Commerce\Variant\O2OVariantInterface;
use App\Repositories\V1_1\Commerce\Video\O2OVideoInterface;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class O2OProductRepository implements O2OProductInterface
{
    private $model;
    private $image;
    private $document;
    private $variant;
    private $option;
    private $businessModel;
    private $categoryModel;
    private $video;
    private $optionVariantModel;
    private $optionModel;

    public function __construct(Product $model,
        O2OImageInterface $image,
        O2ODocumentInterface $document,
        O2OMetadataInterface $metadata,
        O2OOptionInterface $option,
        O2OVariantInterface $variant,
        Business $businessModel,
        Category $categoryModel,
        O2OVideoInterface $video,
        OptionVariant $optionVariant,
        Option $optionModel) {
        $this->model = $model;
        $this->image = $image;
        $this->document = $document;
        $this->metadata = $metadata;
        $this->option = $option;
        $this->variant = $variant;
        $this->businessModel = $businessModel;
        $this->categoryModel = $categoryModel;
        $this->video = $video;
        $this->optionVariantModel = $optionVariant;
        $this->optionModel = $optionModel;
    }

    public function getAll(array $params = [], $is_exhibitor = false)
    {
        $data = $this->model->with('images', 'documents', 'metadata', 'options', 'variants');

//        $data = ProductView::with('images', 'documents', 'metadata', 'options', 'variants');
//        dd($data->get()[0]);

        $params['order_by'] = (isset($params['order_by']) and ! empty($params['order_by'])) ? $params['order_by'] : 'desc';

        if (isset($params['account_id'])) {
            $data = $data->where('account_id', $params['account_id']);
        }

        if (isset($params['business_uid'])) {
            $business = $this->businessModel->where('uid', $params['business_uid'])->first();
            $data = $data->where('vendor_id', $business->id);
        }

        if ($is_exhibitor) {
            $data->where('action_type', '=', 9);
        }

        if (isset($params['order_by']) and ! empty($params['order_by'])) {
            $data = $data->orderBy('id', $params['order_by']);
        }

        $data = $this->search($data, $params);

        return $data->paginate(10);
    }

    public function getRelatedProducts($product_uid, $is_exhibitor = false)
    {
        $product = $this->getByUid($product_uid);
        $business_id = $product->vendor_id;
        $data = $this->model->where('vendor_id', $business_id)
            ->whereNotIn('id', [$product->id]);

        if ($is_exhibitor) {
            $data->where('action_type', '=', 9);
        }

        return $data->paginate(10);
    }

    public function getRecommendedProducts($product_uid, $is_exhibitor = false)
    {
        $product = $this->getByUid($product_uid);
        $account_id = $product->account_id;

        $companyCategories = $this->categoryModel->where('account_id', $account_id)->pluck('id');

        $product_ids = Collection::whereIn('category_id', $companyCategories)
            ->whereNotIn('product_id', [$product->id])
            ->pluck('product_id')->unique();

        $data = $this->model->whereIn('id', $product_ids);

        if ($is_exhibitor) {
            $data->where('action_type', '=', 9);
        }

        return $data->paginate(10);
    }

    public function getById(int $id)
    {
        return $this->model->findOrFail($id);
    }

    public function getByUid($uid)
    {
        $data = $this->model->where('uid', $uid)->firstOrFail();

        return $data;
    }

    public function createData(array $data, $is_organizer = false)
    {
        $business = $this->businessModel->where('uid', $data['business_uid'])->first();

        $product = new Product();
        $product->account_id = $data['account_id'];
        $product->vendor_id = $business->id;
        $product->title = $data['title'];
        $product->slug = $data['title'];
        $product->description = $data['description'] ?? '';
        $product->except = $data['except'] ?? '';
        $product->published_scope = isset($data['published_scope']) ? ProductType::getValue($data['published_scope']) : ProductType::getValue('WEB');
        $product->currency = $data['currency'] ?? '';
        $product->price = $data['price'] ?? '';
        $product->compare_at_price = $data['compare_at_price'] ?? '';
        $product->inventory_quantity = $data['inventory_quantity'] ?? '';
        $product->min_purchase_quantity = $data['min_purchase_quantity'] ?? '';
        $product->metric = $data['metric'] ?? '';
        $product->save();
        $product->action_date = Carbon::now()->toDateTimeString();

        /**
         * To insert the images.
         */
        if (isset($data['images']) && ! empty($data['images'])) {
            $product->action_type = ProductStatus::getValue('LIVE');
            foreach ($data['images'] as $item) {
                $imageData = [
                    'position' => $item['position'] ?? '',
                    'alt' => $item['alt'],
                    'src' => $item['src'],
                    'width' => $item['width'] ?? 0,
                    'height' => $item['height'] ?? 0,
                ];
                $this->image->createData($product, $imageData);
            }
        } else {
            $product->action_type = ProductStatus::getValue('DRAFT');
        }

        if (isset($data['categories'])) {
            foreach ($data['categories'] as $slug) {
                $category = $this->categoryModel->where('slug', $slug)->first();
                if ($category) {
                    $collection = new Collection();
                    $collection->product_id = $product->id;
                    $collection->category_id = $category->id;
                    $collection->save();
                }
            }
        }

        /**
         * To insert the videos.
         */
        if (isset($data['videos'])) {
            foreach ($data['videos'] as $item) {
                $videoData = [
                    'title' => $item['title'] ?? '',
                    'url' => $item['url'] ?? '',
                    'description' => $item['description'] ?? '',
                ];
                $this->video->createData($product, $videoData);
            }
        }

        if (isset($data['documents'])) {
            foreach ($data['documents'] as $item) {
                $documentData = [
                    'product_id' => $product->id,
                    'title' => $item['title'],
                    'description' => $item['description'] ?? '',
                    'src' => $item['src'],
                    'kind' => $item['kind'],
                    'position' => $item['position'],
                ];
                $this->document->createData($documentData);
            }
        }

        if (isset($data['metadata'])) {
            foreach ($data['metadata'] as $item) {
                $kind = AttributeType::getValue($item['kind']);
                if ($kind == 7) {
                    $metaData = [
                        'metafield_id' => $item['metafield_id'],
                        'name' => isset($item['name']) ? json_encode($item['name']) : '',
                        'label' => $item['label'] ?? '',
                        'kind' => $kind,
                    ];
                } else {
                    $metaData = [
                        'uid' => "sasas",
                        'metafield_id' => $item['metafield_id'],
                        'name' => $item['name'] ?? '',
                        'label' => $item['label'] ?? '',
                        'kind' => $kind,
                    ];
                }
                $product->metadata()->create($metaData);
            }
        }

        if (isset($data['options'])) {
            foreach ($data['options'] as $item) {
                $OptionData = [
                    'product_id' => $product->id,
                    'name' => $item['name'],
                    'value' => json_encode($item['value']),
                    'position' => $item['position'] ?? '',
                ];
                $this->option->createData($OptionData);
            }
        }

        if (isset($data['variants'])) {
            foreach ($data['variants'] as $item) {
                $VariantData = [
                    'product_id' => $product->id,
                    'title' => $item['title'],
                    'price' => $item['price'] ?? '',
                    'sku' => $item['sku'] ?? '',
                    'position' => $item['position'] ?? '',
                    'inventory_policy' => $item['inventory_policy'] ?? '',
                    'compare_at_price' => $item['compare_at_price'] ?? '',
                    'fulfillment_service' => $item['fulfillment_service'] ?? '',
                    'inventory_management' => $item['inventory_management'] ?? '',
                    'taxable' => $item['taxable'] ?? '',
                    'barcode' => $item['barcode'] ?? '',
                    'grams' => $item['grams'] ?? '',
                    'weight' => $item['weight'] ?? '',
                    'weight_unit' => $item['weight_unit'] ?? '',
                    'inventory_quantity' => $item['inventory_quantity'] ?? '',
                    'requires_shipping' => $item['requires_shipping'] ?? '',
                ];

                $variant = $this->variant->createData($VariantData);
                if (isset($item['options']) and ! empty($item['options'])) {
                    foreach ($item['options'] as $option) {
                        $optionVariant = new OptionVariant();

                        $optionObj = $this->optionModel->where([
                            ['name', $option['option']],
                            ['product_id', $product->id],
                        ])->first();

                        $optionVariant->option_id = $optionObj->id;
                        $optionVariant->variant_id = $variant->id;
                        $optionVariant->value = $option['value'];
                        $optionVariant->save();
                    }
                }
            }
        }

        /**
         * To insert tags.
         *
         * @todo this has to be revised
         */
        // if (isset($data['tags'])) {
        //     $tags = $data['tags'];
        //     foreach ($tags as $tag) {

        //         $tagExists = CommerceTag::where([
        //             ['account_id', $product->account_id],
        //             ['title', $tag['title']],
        //         ])->first();

        //         if (!$tagExists) {
        //             $product->tags()->create([
        //                 'account_id' => $product->account_id,
        //                 'title' => $tag['title'],
        //                 'slug' => $tag['slug'],
        //                 'position' => $tag['position'],
        //             ]);
        //         }
        //     }
        // }

        $product->save();

        event(new ProductCreateEvent($business, $product));

        return $product;
    }

    public function updateData($id, array $data, $is_organizer = false)
    {
        $product = Product::findOrFail($id);

        if ($product->title != $data['title']) {
            $product->slug = $data['title'];
        }

        $product->title = $data['title'];
        $product->description = $data['description'] ?? '';
        $product->except = $data['except'] ?? '';
        $product->published_scope = isset($data['published_scope']) ? ProductType::getValue($data['published_scope']) : ProductType::getValue('WEB');
        $product->currency = $data['currency'] ?? '';
        $product->price = $data['price'] ?? '';
        $product->compare_at_price = $data['compare_at_price'] ?? '';
        $product->inventory_quantity = $data['inventory_quantity'] ?? '';
        $product->min_purchase_quantity = $data['min_purchase_quantity'] ?? '';
        $product->metric = $data['metric'] ?? '';
        $product->save();

        $product->images()->delete();
        $product->collections()->delete();
        $product->videos()->delete();
        $product->variants()->delete();
        $product->options()->delete();
        $product->documents()->delete();
        $product->metadata()->delete();

        /**
         * To insert the images.
         */
        if ($product->action_type == 9) {
            if (isset($data['images']) && ! empty($data['images'])) {
                $this->createImages($data['images'], $product);
            } else {
                $product->action_type = ProductStatus::getValue('DRAFT');
                $product->action_date = Carbon::now()->toDateTimeString();
            }
        } elseif ($product->action_type == 1) {
            if (isset($data['images']) && ! empty($data['images'])) {
                $this->createImages($data['images'], $product);
                $product->action_type = ProductStatus::getValue('LIVE');
                $product->action_date = Carbon::now()->toDateTimeString();
            }
        }

        /**
         * To insert the videos.
         */
        if (isset($data['videos'])) {
            foreach ($data['videos'] as $item) {
                $videoData = [
                    'title' => $item['title'] ?? '',
                    'url' => $item['url'] ?? '',
                    'description' => $item['description'] ?? '',
                ];
                $this->video->createData($product, $videoData);
            }
        }

        if (isset($data['categories'])) {
            foreach ($data['categories'] as $slug) {
                $category = $this->categoryModel->where('slug', $slug)->first();
                if ($category) {
                    $collection = new Collection();
                    $collection->product_id = $product->id;
                    $collection->category_id = $category->id;
                    $collection->save();
                }
            }
        }

        if (isset($data['documents'])) {
            foreach ($data['documents'] as $item) {
                $documentData = [
                    'product_id' => $product->id,
                    'title' => $item['title'],
                    'description' => $item['description'] ?? '',
                    'src' => $item['src'],
                    'kind' => $item['kind'] ?? '',
                    'position' => $item['position'] ?? '',
                ];
                $this->document->createData($documentData);
            }
        }

        if (isset($data['metadata'])) {
            foreach ($data['metadata'] as $item) {
                $kind = AttributeType::getValue($item['kind']);
                if ($kind == 7) {
                    $metaData = [
                        'metafield_id' => $item['metafield_id'],
                        'name' => isset($item['name']) ? json_encode($item['name']) : '',
                        'label' => $item['label'] ?? '',
                        'kind' => AttributeType::getValue($item['kind']),
                    ];
                } else {
                    $metaData = [
                        'metafield_id' => $item['metafield_id'],
                        'name' => $item['name'],
                        'label' => $item['label'] ?? '',
                        'kind' => AttributeType::getValue($item['kind']),
                    ];
                }
                $product->metadata()->create($metaData);
            }
        }

        if (isset($data['options'])) {
            foreach ($data['options'] as $item) {
                $OptionData = [
                    'product_id' => $product->id,
                    'name' => $item['name'],
                    'value' => json_encode($item['value']),
                    'position' => $item['position'] ?? '',
                ];
                $this->option->createData($OptionData);
            }
        }

        if (isset($data['variants'])) {
            foreach ($data['variants'] as $item) {
                $VariantData = [
                    'product_id' => $product->id,
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'sku' => $item['sku'],
                    'position' => $item['position'] ?? '',
                    'inventory_policy' => $item['inventory_policy'] ?? '',
                    'compare_at_price' => $item['compare_at_price'] ?? '',
                    'fulfillment_service' => $item['fulfillment_service'] ?? '',
                    'inventory_management' => $item['inventory_management'] ?? '',
                    'taxable' => $item['taxable'] ?? '',
                    'barcode' => $item['barcode'] ?? '',
                    'grams' => $item['grams'] ?? '',
                    'weight' => $item['weight'] ?? '',
                    'weight_unit' => $item['weight_unit'] ?? '',
                    'inventory_quantity' => $item['inventory_quantity'] ?? '',
                    'requires_shipping' => $item['requires_shipping'] ?? '',
                ];

                $variant = $this->variant->createData($VariantData);
                if (isset($item['options']) and ! empty($item['options'])) {
                    foreach ($item['options'] as $option) {
                        $optionVariant = new OptionVariant();

                        $optionObj = $this->optionModel->where([
                            ['name', $option['option']],
                            ['product_id', $product->id],
                        ])->first();

                        $optionVariant->option_id = $optionObj->id;
                        $optionVariant->variant_id = $variant->id;
                        $optionVariant->value = $option['value'];
                        $optionVariant->save();
                    }
                }
            }
        }

        /**
         * To insert tags.
         *
         * @todo  this need to be checked again
         */
        // if (isset($data['tags'])) {
        //     $tags = $data['tags'];
        //     foreach ($tags as $tag) {

        //         $tagExists = CommerceTag::where([
        //             ['account_id', $product->account_id],
        //             ['title', $tag['title']],
        //         ])->first();

        //         if (!$tagExists) {
        //             $product->tags()->create([
        //                 'account_id' => $product->account_id,
        //                 'title' => $tag['title'],
        //                 'slug' => $tag['slug'],
        //                 'position' => $tag['position'],
        //             ]);
        //         }
        //     }
        // }

        $product->save();

        $business = $this->businessModel->find($product->vendor_id);

        event(new ProductUpdateEvent($business, $product));

        return $product;
    }

    public function destroyData($id, $is_organizer = false)
    {

        try {
            $product = $this->getById($id);
            $business = $this->businessModel->find($product->vendor_id);

            $product->images()->delete();
            $product->variants()->delete();
            $product->options()->delete();
            $product->documents()->delete();
            $product->metadata()->delete();
            $product->collections()->delete();
            event(new ProductDeleteEvent($business, $product));
            $product->forceDelete();

            return $product;
        } catch (Exception $e) {

            return abort(400);
        }

    }

    /**
     * @param $images
     * @param $product
     * @return mixed
     */
    public function createImages($images, $product)
    {
        foreach ($images as $item) {
            $imageData = [
                'position' => $item['position'],
                'alt' => $item['alt'],
                'src' => $item['src'],
                'width' => $item['width'] ?? 0,
                'height' => $item['src'] ?? 0,
            ];
            $this->image->createData($product, $imageData);
        }

        return $item;
    }


    public function search($products, array $params = null){

        if (isset($params['q']) and ($params['q'])) {
            $search = urldecode($params['q']);
            $products = $products->where(function ($query) use ($search) {
                $query->where('o2o_products.title', 'like', '%'.$search.'%');
            });
        }

        if ((isset($params['min_price']) and ! empty($params['min_price'])) or (isset($params['max_price']) and ! empty($params['max_price']))){
            $params['min_price'] = empty($params['min_price']) ? 0 : $params['min_price'];
            $params['max_price'] = empty($params['max_price']) ? 0 : $params['max_price'];
            $products = $products->whereBetween('price', [$params['min_price'], $params['max_price']]);
        }

        if (isset($params['start']) and ! empty($params['start']) and ! isset($params['end'])) {
            $products = $products->whereDate('o2o_products.created_at', '>=', Carbon::parse($params['start']));
        }

        if (isset($params['end']) and ! empty($params['end']) and ! isset($params['start'])) {
            $products = $products->whereDate('o2o_products.created_at', '<=', Carbon::parse($params['end']));
        }

        if (isset($params['end']) and isset($params['start']) and ! empty($params['end']) and ! empty($params['start'])) {
            $products = $products->whereBetween(DB::raw('DATE(o2o_products.created_at)'), [Carbon::parse($params['start']), Carbon::parse($params['end'])]);
        }

        if (isset($params['is_published']) and $params['is_published'] == true) {
            $products = $products->where('published_scope', 1);
        }

        if (isset($params['category']) and ! empty($params['category'])) {
            $categories = explode(',', $params['category']);
            $products = $products->whereHas('collections', function ($q) use ($categories) {
                $q->whereIn('category_id', $categories);
            });
        }

        return $products;
    }

}
