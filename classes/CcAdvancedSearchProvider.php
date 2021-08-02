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

    if (!defined('_PS_VERSION_'))
    {
        exit;
    }

    use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
    use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
    use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
    use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
    use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
    use PrestaShop\PrestaShop\Core\Product\Search\SortOrderFactory;
    use Symfony\Component\Translation\TranslatorInterface;

    class CcAdvancedSearchProvider implements ProductSearchProviderInterface
    {
        private $translator;
        private $sortOrderFactory;

        public function __construct(Codecranes_AdvancedSearch $module, TranslatorInterface $translator)
        {
            $this->module = $module;
            $this->translator = $translator;
            $this->sortOrderFactory = new SortOrderFactory($this->translator);
        }

        public function getSortOrders($includeAll = false, $includeDefaultSortOrders = true)
        {
            return $this->sortOrderFactory->getDefaultSortOrders();
        }

        public function runQuery(
            ProductSearchContext $context,
            ProductSearchQuery $query
        )
        {
            $resultsPerPage = (int) Tools::getValue('resultsPerPage');

            if ($resultsPerPage <= 0)
            {
                $resultsPerPage = Configuration::get('PS_PRODUCTS_PER_PAGE');
            }

            $query->setResultsPerPage($resultsPerPage);

            $result = new ProductSearchResult();
            $sortOrders = $this->getSortOrders();
            $result->setAvailableSortOrders(
                $sortOrders
            );

            if ($query->getSortOrder() == null && !$result->getCurrentSortOrder())
            {
                $query->setSortOrder(new SortOrder('product', Tools::getProductsOrder('by'), Tools::getProductsOrder('way')));
            }

            $search = CcAdvancedSearch::getInstance()->search(Tools::getValue('s'), (int) Tools::getValue('c', '0'), false, $query);

            $result->setProducts($search['products']);
            $result->setTotalProductsCount($search['total_products']);
            $result->setEncodedFacets(implode('%20', $search['search_words']));
            return $result;
        }
    }
