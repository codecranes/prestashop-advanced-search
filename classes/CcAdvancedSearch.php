<?php

/**
 * Codecranes
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://codecranes.com/modules/prestashop-advanced-search/license
 *
 * @author     Codecranes
 * @copyright  Copyright (c) Codecranes (https://codecranes.com)
 * @license    https://codecranes.com/modules/prestashop-advanced-search/license
 */

class CcAdvancedSearch extends Search
{
    protected static $instance;
    protected $config;
    protected $result = [
        'returnminwordlength' => true,
        'minwordlength'       => 3,
        'total_products'      => 0,
        'total_categories'    => 0,
        'products'            => [],
        'product_words'       => [],
        'categories'          => [],
        'category_words'      => [],
        'search_words'        => []
    ];

    public static function getInstance()
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function search($string, int $id_category = 0, $only_products = false, $queryProvider = false)
    {
        $search_words = [];

        $this->result['minwordlength'] = (int) Configuration::get('PS_SEARCH_MINWORDLEN');

        $query = Tools::replaceAccentedChars(urldecode(trim($string)));

        if (empty($query) || strlen($query) < $this->result['minwordlength']) {
            return $this->result;
        }

        $context = Context::getContext();
        $id_lang = $context->language->id;
        $words = Search::extractKeyWords($query, $id_lang, false, $context->language->iso_code);

        foreach ($words as $word) {
            if (!empty($word) && strlen($word) >= $this->result['minwordlength']) {
                $search_words[] = $word;
            }
        }

        if (count($search_words) < '1') {
            return $this->result;
        }

        $this->result['search_words'] = $search_words;
        $this->result['returnminwordlength'] = false;
        $settings = $this->getSettings();
        $id_shop = $context->shop->id;
        $link = $context->link;

        if ($products = $this->searchProducts($search_words, $settings, $context, $id_lang, $id_shop, $id_category, $queryProvider)) {
            foreach ($products['result'] as &$product) {
                $product['format_price_tax_exc'] = Tools::displayPrice($product['price_tax_exc']);
                $product['format_price'] = Tools::displayPrice($product['price']);
                $product['format_price_without_reduction'] = Tools::displayPrice($product['price_without_reduction']);

                $product['img_url'] = $link->getImageLink($product['link_rewrite'], $product['id_image'], 'home_default');
            }

            $this->result['total_products'] = $products['total'];
            $this->result['products'] = $products['result'];
            $this->result['product_words'] = $products['words'];
        }

        if (!$only_products && $categories = $this->searchCategories($search_words, $settings, $context, $id_lang, $id_shop, $id_category)) {
            foreach ($categories['result'] as &$category) {
                $category['name_highlight'] = $this->highlight($category['name'], $category['words']);

                $category['link'] = $link->getCategoryLink($category['id_category'], $category['link_rewrite']);
            }

            $this->result['total_categories'] = $categories['total'];
            $this->result['categories'] = $categories['result'];
            $this->result['category_words'] = $categories['words'];
        }

        return $this->result;
    }

    protected function searchCategories($words, $settings, $context, $id_lang, $id_shop, $id_category)
    {
        $word_categories = [];

        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        if ($settings['category'] == '1' && $id_category > '0') {
            $parentCategory = new Category((int) $id_category);

            if (!Validate::isLoadedObject($parentCategory)) {
                return false;
            }

            $children = $parentCategory->getAllChildren();
            $children[] = $parentCategory;
            $categories = [];

            foreach ($children as $cat) {
                $categories[] = $cat->id;
            }

            $categories = array_unique($categories);

            if (Group::isFeatureActive()) {
                $groups = FrontController::getCurrentCustomerGroups();
                $sqlGroups = 'AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) Group::getCurrent()->id);

                $sql = "SELECT c.*, cl.*
                    FROM `" . _DB_PREFIX_ . "category` c, `" . _DB_PREFIX_ . "category_lang` cl, `" . _DB_PREFIX_ . "category_group` cg
                    WHERE c.`id_category` IN ('" . implode("', '", $categories) . "')
                        AND c.`active` = '1'
                        AND c.`id_category` != '" . (int) Configuration::get('PS_HOME_CATEGORY') . "'
                        AND cg.`id_category` = c.`id_category`
                        {$sqlGroups}
                        AND cl.`id_category` = cg.`id_category`
                        AND cl.`id_lang` = '{$id_lang}'
                        AND cl.`id_shop` = '{$id_shop}'
                        AND cl.`name` LIKE
                ";
            } else {
                $sql = "SELECT c.*, cl.*
                    FROM `" . _DB_PREFIX_ . "category` c, `" . _DB_PREFIX_ . "category_lang` cl
                    WHERE c.`id_category` IN ('" . implode("', '", $categories) . "')
                        AND c.`active` = '1'
                        AND c.`id_category` != '" . (int) Configuration::get('PS_HOME_CATEGORY') . "'
                        AND cl.`id_category` = c.`id_category`
                        AND cl.`id_lang` = '{$id_lang}'
                        AND cl.`id_shop` = '{$id_shop}'
                        AND cl.`name` LIKE
                ";
            }
        } else {
            $sql = "SELECT c.*, cl.*
                FROM `" . _DB_PREFIX_ . "category` c, `" . _DB_PREFIX_ . "category_lang` cl
                WHERE c.`active` = '1'
                    AND c.`id_category` != '" . (int) Configuration::get('PS_HOME_CATEGORY') . "'
                    AND cl.`id_category` = c.`id_category`
                    AND cl.`id_lang` = '{$id_lang}'
                    AND cl.`id_shop` = '{$id_shop}'
                    AND cl.`name` LIKE
            ";
        }

        foreach ($words as $key => $word) {
            $word = pSQL($word);

            if ($results = $db->executeS($sql . "'%{$word}%';", true, false)) {
                foreach ($results as $result) {
                    $word_categories[$word][$result['id_category']] = $result;
                }
            } elseif ($settings['or_and'] == '2');
            return false;
        }

        if (count($word_categories) < '1') {
            return false;
        }

        if ($settings['or_and'] == '0') {
            $categories = [];

            foreach ($word_categories as $word_category => $single_word_categories) {
                foreach ($single_word_categories as $word_category_id => $category) {
                    if (!isset($categories[$word_category_id])) {
                        $categories[$word_category_id] = $category;
                        $categories[$word_category_id]['words'] = [];
                    }

                    $categories[$word_category_id]['words'][] = $word_category;
                }
            }
        } else {
            $categories = array_shift(array_values($word_categories));

            foreach ($word_categories as $word_category => $single_word_categories) {
                foreach ($categories as $id_category => $category) {
                    if (!isset($single_word_categories[$id_category])) {
                        unset($categories[$id_category]);
                    } else {
                        if (!isset($categories[$id_category]['words'])) {
                            $categories[$id_category]['words'] = [];
                        }

                        $categories[$id_category]['words'][] = $word_category;
                    }
                }
            }
        }

        if (count($categories) < '1') {
            return false;
        }

        return ['total' => count($categories), 'result' => $categories, 'words' => $word_categories];
    }

    protected function searchProducts($words, $settings, $context, $id_lang, $id_shop, $id_category, $queryProvider = false)
    {
        $word_products = [];
        $fuzzyLoop = 0;

        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        $psFuzzySearch = (int) Configuration::get('PS_SEARCH_FUZZY');

        $fuzzyMaxLoop = (int) Configuration::get('PS_SEARCH_FUZZY_MAX_LOOP');

        if ($queryProvider) {
            $orderBy = $queryProvider->getSortOrder()->toLegacyOrderBy();
            $orderWay = $queryProvider->getSortOrder()->toLegacyOrderWay();
            $productsPerPage = (int) $queryProvider->getResultsPerPage();
            $page = (int) $queryProvider->getPage();

            switch ($orderBy) {
                case 'date_upd':
                case 'date_add':
                case 'price':
                    $orderBy = "product_shop.`{$orderBy}`";
                    break;
                case 'id_product':
                case 'reference':
                    $orderBy = "p.`{$orderBy}`";
                    break;
                case 'manufacturer':
                    $orderBy = "m.`{$orderBy}`";
                    break;
                case 'position':
                    $orderBy = "cp.`{$orderBy}`";
                    break;
                case 'name':
                    $orderBy = "pl.`{$orderBy}`";
                    break;
                case 'sales':
                    $orderBy = "quantity";
                    break;
            }
        } else {
            $orderBy = 'pl.`name`';
            $orderWay = 'ASC';
            $productsPerPage = '10';
            $page = '1';
        }

        if ($settings['category'] == '1' && $id_category > '0') {
            $parentCategory = new Category((int) $id_category);

            if (!Validate::isLoadedObject($parentCategory)) {
                return false;
            }

            $children = $parentCategory->getAllChildren();
            $children[] = $parentCategory;
            $categories = [];

            foreach ($children as $cat) {
                $categories[] = $cat->id;
            }

            $categories = array_unique($categories);

            if (Group::isFeatureActive()) {
                $groups = FrontController::getCurrentCustomerGroups();
                $sqlGroups = 'AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) Group::getCurrent()->id);

                $sql = "SELECT DISTINCT si.`id_product`
                    FROM `" . _DB_PREFIX_ . "search_word` sw, `" . _DB_PREFIX_ . "search_index` si, `" . _DB_PREFIX_ . "product_shop` product_shop, `" . _DB_PREFIX_ . "category_product` cp, `" . _DB_PREFIX_ . "category_group` cg
                    WHERE sw.`id_lang` = '{$id_lang}'
                        AND sw.`id_shop` = '{$id_shop}'
                        AND si.`id_word` = sw.`id_word`
                        AND product_shop.`id_product` = si.`id_product`
                        AND product_shop.`active` = '1'
                        AND product_shop.`indexed` = '1'
                        AND product_shop.`visibility` IN ('both', 'search')
                        AND cp.`id_product` = product_shop.`id_product`
                        AND cp.`id_category` IN ('" . implode("', '", $categories) . "')
                        AND cg.`id_category` = cp.`id_category`
                        {$sqlGroups}
                        AND sw.word LIKE
                ";
            } else {
                $sql = "SELECT DISTINCT si.`id_product`
                    FROM `" . _DB_PREFIX_ . "search_word` sw, `" . _DB_PREFIX_ . "search_index` si, `" . _DB_PREFIX_ . "product_shop` product_shop, `" . _DB_PREFIX_ . "category_product` cp
                    WHERE sw.`id_lang` = '{$id_lang}'
                        AND sw.`id_shop` = '{$id_shop}'
                        AND si.`id_word` = sw.`id_word`
                        AND product_shop.`id_product` = si.`id_product`
                        AND product_shop.`active` = '1'
                        AND product_shop.`indexed` = '1'
                        AND product_shop.`visibility` IN ('both', 'search')
                        AND cp.`id_product` = product_shop.`id_product`
                        AND cp.`id_category` IN ('" . implode("', '", $categories) . "')
                        AND sw.word LIKE
                ";
            }
        } else {
            $sql = "SELECT DISTINCT si.`id_product`
                FROM `" . _DB_PREFIX_ . "search_word` sw, `" . _DB_PREFIX_ . "search_index` si, `" . _DB_PREFIX_ . "product_shop` product_shop
                WHERE sw.`id_lang` = '{$id_lang}'
                    AND sw.`id_shop` = '{$id_shop}'
                    AND si.`id_word` = sw.`id_word`
                    AND product_shop.`id_product` = si.`id_product`
                    AND product_shop.`active` = '1'
                    AND product_shop.`indexed` = '1'
                    AND product_shop.`visibility` IN ('both', 'search')
                    AND sw.word LIKE
            ";
        }

        foreach ($words as $key => $word) {
            $sql_param_search = static::getSearchParamFromWord($word);

            while (!($result = $db->executeS($sql . "'{$sql_param_search}';", true, false))) {
                if (!$psFuzzySearch || $fuzzyLoop++ > $fuzzyMaxLoop || !($sql_param_search = static::findClosestWeightestWord($context, $word))) {
                    break;
                }
            }

            if (!$result) {
                if ($settings['or_and'] == '2') {
                    return false;
                }

                unset($words[$key]);
                continue;
            }

            $word_products[$sql_param_search] = array_column($result, 'id_product');
        }

        if (!count($words)) {
            return false;
        }

        if ($settings['or_and'] == '0') {
            $ids_product = [];

            foreach ($word_products as $products) {
                foreach ($products as $id_product) {
                    $ids_product[] = $id_product;
                }
            }

            $ids_product = array_unique($ids_product);
        } else {
            $ids_product = array_shift(array_values($word_products));

            foreach ($word_products as $products) {
                $ids_product = array_intersect($ids_product, $products);
            }
        }

        $product_pool = '';

        foreach ($ids_product as $id_product) {
            if ($id_product) {
                $product_pool .= (int) $id_product . ',';
            }
        }

        if (empty($product_pool)) {
            return false;
        }

        $product_pool = ((strpos($product_pool, ',') === false) ? (' = ' . (int) $product_pool . ' ') : (' IN (' . rtrim($product_pool, ',') . ') '));

        $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity,
            pl.`description_short`, pl.`available_now`, pl.`available_later`, pl.`link_rewrite`, pl.`name`, image_shop.`id_image` id_image, il.`legend`, m.`name` manufacturer_name,
            DATEDIFF(
                p.`date_add`,
                DATE_SUB(
                    "' . date('Y-m-d') . ' 00:00:00",
                    INTERVAL ' . (Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY
                )
            ) > 0 new' . (Combination::isFeatureActive() ? ', product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity, IFNULL(product_attribute_shop.`id_product_attribute`,0) id_product_attribute' : '') . '
            FROM ' . _DB_PREFIX_ . 'product p
            ' . Shop::addSqlAssociation('product', 'p') . '
            INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                p.`id_product` = pl.`id_product`
                AND pl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestrictionOnLang('pl') . '
            )
            ' . (Combination::isFeatureActive() ? 'LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop FORCE INDEX (id_product)
                ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int) $context->shop->id . ')' : '') . '
            ' . Product::sqlStock('p', 0) . '
            LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m FORCE INDEX (PRIMARY)
                ON m.`id_manufacturer` = p.`id_manufacturer`
            LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop FORCE INDEX (id_product)
                ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int) $context->shop->id . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) $id_lang . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'category_product` cp ON (cp.`id_product` = p.`id_product` AND cp.`id_category` = product_shop.`id_category_default`)
            WHERE p.`id_product` ' . $product_pool . '
            GROUP BY product_shop.id_product
            ORDER BY ' . $orderBy . ' ' . $orderWay . '
            LIMIT ' . ($page * $productsPerPage - $productsPerPage) . ', ' . $productsPerPage;

        $result = $db->executeS($sql, true, false);

        $sql = 'SELECT COUNT(*)
            FROM ' . _DB_PREFIX_ . 'product p
            ' . Shop::addSqlAssociation('product', 'p') . '
            INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                p.`id_product` = pl.`id_product`
                AND pl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestrictionOnLang('pl') . '
            )
            LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer`
            WHERE p.`id_product` ' . $product_pool;
            
        $total = $db->getValue($sql, false);

        if (!$result) {
            $result_properties = false;
        } else {
            $result_properties = Product::getProductsProperties((int) $id_lang, $result);
        }

        return ['total' => $total, 'result' => $result_properties, 'words' => $word_products];
    }

    protected function highlight(string $string, array $words = [])
    {
        if (empty($string) || count($words) < '1') {
            return $string;
        }

        $iwords = implode('|', $words);
        return preg_replace('/(' . $iwords . ')/si', '<span class="highlight">$1</span>', $string);
    }

    public function getSettings()
    {
        static $return = null;

        if ($return !== null) {
            return $return;
        }

        $id_shop = (int) Context::getContext()->shop->id;

        return $return = [
            'or_and'       => (int) Configuration::get('CODECRANES_ADVANCEDSEARCH_SETTINGS_OR_AND', null, null, $id_shop, '0'),
            'category'     => (int) Configuration::get('CODECRANES_ADVANCEDSEARCH_SETTINGS_CATEGORY', null, null, $id_shop, '0'),
            'active'       => (int) Configuration::get('CODECRANES_ADVANCEDSEARCH_SETTINGS_ACIVE', null, null, $id_shop, '0'),
            'show_in_menu' => (int) Configuration::get('CODECRANES_ADVANCEDSEARCH_SETTINGS_SHOW_IN_MENU', null, null, $id_shop, '0'),
            'show_again'   => (int) Configuration::get('CODECRANES_ADVANCEDSEARCH_SETTINGS_SHOW_AGAIN', null, null, $id_shop, '0'),
            'css'          => Configuration::get('CODECRANES_ADVANCEDSEARCH_SETTINGS_CSS', null, null, $id_shop, '')
        ];
    }
}
