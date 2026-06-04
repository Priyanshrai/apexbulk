<?php

namespace App\Services;

use Gnikyt\BasicShopifyAPI\ResponseAccess;

class ShopifyGraphQL
{
    public static function query($shop, string $query): array
    {
        $response = $shop->api()->graph($query);

        return static::unwrap($response['body']['data'] ?? []);
    }

    public static function fetchProducts($shop, ?array $productIds = null): array
    {
        $allEdges = [];
        $cursor = null;

        do {
            $afterArg = $cursor ? ', after: "' . $cursor . '"' : '';

            $query = <<<GQL
                {
                    products(first: 250{$afterArg}) {
                        edges {
                            node {
                                id
                                title
                                variants(first: 100) {
                                    edges {
                                        node { id price }
                                        cursor
                                    }
                                    pageInfo { hasNextPage }
                                }
                            }
                            cursor
                        }
                        pageInfo { hasNextPage }
                    }
                }
            GQL;

            $data = static::query($shop, $query);
            $products = $data['products'] ?? [];
            $edges = $products['edges'] ?? [];

            foreach ($edges as &$edge) {
                $edge = static::fetchAllVariants($shop, $edge);
            }

            $allEdges = array_merge($allEdges, $edges);

            $pageInfo = $products['pageInfo'] ?? [];
            if (!empty($pageInfo['hasNextPage']) && !empty($edges)) {
                $lastEdge = end($edges);
                $cursor = $lastEdge['cursor'] ?? null;
            } else {
                $cursor = null;
            }
        } while ($cursor);

        if ($productIds && count($productIds) > 0) {
            $allEdges = array_filter($allEdges, function ($edge) use ($productIds) {
                $gid = $edge['node']['id'] ?? '';
                return in_array(static::extractId($gid), $productIds);
            });
        }

        return array_values($allEdges);
    }

    public static function updateVariantPrices($shop, string $productId, array $variants): array
    {
        $parts = [];
        foreach ($variants as $v) {
            $parts[] = '{id: "' . $v['id'] . '", price: "' . $v['price'] . '"}';
        }
        $variantsStr = implode(', ', $parts);

        $query = <<<GQL
            mutation {
                productVariantsBulkUpdate(
                    productId: "{$productId}",
                    variants: [{$variantsStr}]
                ) {
                    product { id }
                    productVariants { id price }
                    userErrors { field message }
                }
            }
        GQL;

        $data = static::query($shop, $query);

        return $data['productVariantsBulkUpdate'] ?? [];
    }

    public static function extractId(string $gid): string
    {
        $parts = explode('/', $gid);

        return end($parts);
    }

    private static function fetchAllVariants($shop, array $edge): array
    {
        $variants = $edge['node']['variants'] ?? [];
        $variantEdges = $variants['edges'] ?? [];
        $pageInfo = $variants['pageInfo'] ?? [];
        $variantCursor = null;

        if (!empty($pageInfo['hasNextPage']) && !empty($variantEdges)) {
            $lastVariant = end($variantEdges);
            $variantCursor = $lastVariant['cursor'] ?? null;
        }

        $productId = $edge['node']['id'];

        while ($variantCursor) {
            $query = <<<GQL
                {
                    product(id: "{$productId}") {
                        variants(first: 100, after: "{$variantCursor}") {
                            edges {
                                node { id price }
                                cursor
                            }
                            pageInfo { hasNextPage }
                        }
                    }
                }
            GQL;

            $data = static::query($shop, $query);
            $nextVariants = $data['product']['variants'] ?? [];
            $nextEdges = $nextVariants['edges'] ?? [];

            $variantEdges = array_merge($variantEdges, $nextEdges);

            $nextPageInfo = $nextVariants['pageInfo'] ?? [];
            if (!empty($nextPageInfo['hasNextPage']) && !empty($nextEdges)) {
                $lastNext = end($nextEdges);
                $variantCursor = $lastNext['cursor'] ?? null;
            } else {
                $variantCursor = null;
            }
        }

        $edge['node']['variants']['edges'] = $variantEdges;

        return $edge;
    }

    private static function unwrap($data): array
    {
        if ($data instanceof ResponseAccess) {
            return $data->toArray();
        }

        if (is_array($data)) {
            return $data;
        }

        return [];
    }
}
