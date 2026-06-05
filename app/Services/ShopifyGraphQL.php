<?php

namespace App\Services;

use Gnikyt\BasicShopifyAPI\ResponseAccess;

class ShopifyGraphQL
{
    public static function query($shop, string $query): array
    {
        $response = $shop->api()->graph($query);
        $body = $response['body'] ?? [];
        $data = static::unwrap($body['data'] ?? []);

        return $data;
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

    public static function fetchProductsWithTags($shop, ?array $productIds = null): array
    {
        $allEdges = [];
        $cursor = null;

        do {
            $afterArg = $cursor ? ', after: "' . $cursor . '"' : '';

            $query = <<<GQL
                {
                    products(first: 250{$afterArg}) {
                        edges {
                            node { id title tags }
                            cursor
                        }
                        pageInfo { hasNextPage }
                    }
                }
            GQL;

            $data = static::query($shop, $query);
            $products = $data['products'] ?? [];
            $edges = $products['edges'] ?? [];
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

    public static function fetchLocations($shop): array
    {
        $query = <<<GQL
            {
                locations(first: 10) {
                    edges {
                        node { id name isActive }
                    }
                }
            }
        GQL;

        $data = static::query($shop, $query);
        $edges = $data['locations']['edges'] ?? [];

        return array_filter(array_map(function ($e) {
            $n = $e['node'] ?? [];
            return $n['isActive'] ? $n : null;
        }, $edges));
    }

    public static function fetchProductsWithInventory($shop, ?array $productIds = null): array
    {
        $allEdges = [];
        $cursor = null;

        do {
            $afterArg = $cursor ? ', after: "' . $cursor . '"' : '';

            $query = <<<GQL
                {
                    products(first: 25{$afterArg}) {
                        edges {
                            node {
                                id
                                title
                                variants(first: 50) {
                                    edges {
                                        node {
                                            id
                                            inventoryItem {
                                                id
                                                tracked
                                                inventoryLevels(first: 5) {
                                                    edges {
                                                        node {
                                                            id
                                                            location { id }
                                                            quantities(names: ["available"]) {
                                                                name
                                                                quantity
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
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

    public static function setInventoryQuantities($shop, array $quantities): array
    {
        $parts = [];
        foreach ($quantities as $q) {
            $from = $q['changeFromQuantity'] ?? 0;
            $parts[] = '{inventoryItemId: "' . $q['inventoryItemId'] . '", locationId: "' . $q['locationId'] . '", quantity: ' . $q['quantity'] . ', changeFromQuantity: ' . $from . '}';
        }
        $qtyStr = implode(', ', $parts);
        $idemKey = 'inv_set_' . bin2hex(random_bytes(6));

        $query = <<<GQL
            mutation {
                inventorySetQuantities(
                    input: {
                        reason: "correction",
                        name: "available",
                        quantities: [{$qtyStr}]
                    }
                ) @idempotent(key: "{$idemKey}") {
                    inventoryAdjustmentGroup { reason changes { name delta } }
                    userErrors { field message }
                }
            }
        GQL;

        $data = static::query($shop, $query);

        return $data['inventorySetQuantities'] ?? [];
    }

    public static function adjustInventoryQuantities($shop, array $changes): array
    {
        $parts = [];
        foreach ($changes as $c) {
            $from = $c['changeFromQuantity'] ?? 0;
            $parts[] = '{inventoryItemId: "' . $c['inventoryItemId'] . '", locationId: "' . $c['locationId'] . '", delta: ' . $c['delta'] . ', changeFromQuantity: ' . $from . '}';
        }
        $changesStr = implode(', ', $parts);
        $idemKey = 'inv_adj_' . bin2hex(random_bytes(6));

        $query = <<<GQL
            mutation {
                inventoryAdjustQuantities(
                    input: {
                        reason: "correction",
                        name: "available",
                        changes: [{$changesStr}]
                    }
                ) @idempotent(key: "{$idemKey}") {
                    inventoryAdjustmentGroup { reason changes { name delta } }
                    userErrors { field message }
                }
            }
        GQL;

        $data = static::query($shop, $query);

        return $data['inventoryAdjustQuantities'] ?? [];
    }

    public static function updateVariantSettings($shop, string $productId, array $variants): array
    {
        $parts = [];
        foreach ($variants as $v) {
            $fields = ['id: "' . $v['id'] . '"'];
            if (isset($v['tracked'])) {
                $fields[] = 'inventoryItem: { tracked: ' . ($v['tracked'] ? 'true' : 'false') . ' }';
            }
            if (isset($v['inventoryPolicy'])) {
                $fields[] = 'inventoryPolicy: ' . $v['inventoryPolicy'];
            }
            $parts[] = '{' . implode(', ', $fields) . '}';
        }
        $variantsStr = implode(', ', $parts);

        $query = <<<GQL
            mutation {
                productVariantsBulkUpdate(
                    productId: "{$productId}",
                    variants: [{$variantsStr}]
                ) {
                    product { id }
                    productVariants { id }
                    userErrors { field message }
                }
            }
        GQL;

        $data = static::query($shop, $query);

        return $data['productVariantsBulkUpdate'] ?? [];
    }

    public static function updateProductTags($shop, string $productGid, string $action, array $tags = []): array
    {
        if ($action === 'clear') {
            $tags = [];
            $action = 'replace';
        }

        if ($action === 'replace') {
            $tagsStr = !empty($tags) ? '"' . implode('", "', $tags) . '"' : '';
            $query = <<<GQL
                mutation {
                    productUpdate(input: {id: "{$productGid}", tags: [{$tagsStr}]}) {
                        product { id tags }
                        userErrors { field message }
                    }
                }
            GQL;
        } elseif ($action === 'add') {
            $tagsStr = '"' . implode('", "', $tags) . '"';
            $query = <<<GQL
                mutation {
                    tagsAdd(id: "{$productGid}", tags: [{$tagsStr}]) {
                        node { id }
                        userErrors { field message }
                    }
                }
            GQL;
        } elseif ($action === 'remove') {
            $tagsStr = '"' . implode('", "', $tags) . '"';
            $query = <<<GQL
                mutation {
                    tagsRemove(id: "{$productGid}", tags: [{$tagsStr}]) {
                        node { id }
                        userErrors { field message }
                    }
                }
            GQL;
        } else {
            return ['userErrors' => [['field' => 'action', 'message' => 'Unknown action: ' . $action]]];
        }

        $data = static::query($shop, $query);

        return $data['productUpdate'] ?? $data['tagsAdd'] ?? $data['tagsRemove'] ?? [];
    }

    public static function fetchCurrentQuantities($shop, array $items): array
    {
        // items: [['inventoryItemId' => '...', 'locationId' => '...'], ...]
        $result = [];
        foreach (array_chunk($items, 10) as $chunk) {
            $ids = array_map(fn($i) => '"' . $i['inventoryItemId'] . '"', $chunk);
            $idsStr = implode(', ', $ids);

            $query = <<<GQL
                {
                    nodes(ids: [{$idsStr}]) {
                        ... on InventoryItem {
                            id
                            inventoryLevel(locationId: "{$chunk[0]['locationId']}") {
                                quantities(names: ["available"]) {
                                    name
                                    quantity
                                }
                            }
                        }
                    }
                }
            GQL;

            $data = static::query($shop, $query);
            $nodes = $data['nodes'] ?? [];

            foreach ($nodes as $node) {
                $qty = 0;
                foreach (($node['inventoryLevel']['quantities'] ?? []) as $q) {
                    if ($q['name'] === 'available') {
                        $qty = (int) $q['quantity'];
                        break;
                    }
                }
                $result[$node['id']] = $qty;
            }

            usleep(100000);
        }

        return $result;
    }

    private static function unwrap($data): array
    {
        if ($data instanceof ResponseAccess) {
            return json_decode(json_encode($data->toArray()), true);
        }

        if (is_array($data)) {
            return $data;
        }

        return [];
    }
}
