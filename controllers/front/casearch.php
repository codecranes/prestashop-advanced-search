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

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Product\Search\FacetsRendererInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

class Codecranes_AdvancedsearchCaSearchModuleFrontController extends ProductListingFrontController
{
    public function init()
    {
        parent::init();

        if (!isset($this->module) || !is_object($this->module)) {
            $this->module = Module::getInstanceByName('codecranes_advancedsearch');
        }

        if ($this->ajax) {
            $this->display_header = false;
            $this->display_footer = false;
        }

        $search_string = Tools::getValue('s');

        if (!$search_string) {
            $search_string = Tools::getValue('search_query');
        }

        $this->context->smarty->assign([
            'search_string'   => $search_string,
            'search_tag'      => Tools::getValue('tag'),
            'search_category' => (int) Tools::getValue('c')
        ]);
    }

    public function getListingLabel()
    {
        return $this->getTranslator()->trans('Search results', [], 'Shop.Theme.Catalog') . ': ' . Tools::getValue('s');
    }

    protected function getProductSearchQuery()
    {
        return new ProductSearchQuery();
    }

    protected function getDefaultProductSearchProvider()
    {
        return new CcAdvancedSearchProvider(
            $this->module,
            $this->getTranslator(),
        );
    }

    protected function getProductSearchVariables()
    {
        $context = $this->getProductSearchContext();
        $query = $this->getProductSearchQuery();
        $provider = $this->getDefaultProductSearchProvider();

        $resultsPerPage = (int) Tools::getValue('resultsPerPage');

        if ($resultsPerPage <= 0 || $resultsPerPage > 36) {
            $resultsPerPage = Configuration::get('PS_PRODUCTS_PER_PAGE');
        }

        $query
            ->setResultsPerPage($resultsPerPage)
            ->setPage(max((int) Tools::getValue('page'), 1));

        if (Tools::getValue('order')) {
            $encodedSortOrder = Tools::getValue('order', null);
        } else {
            $encodedSortOrder = Tools::getValue('orderby', null);
        }

        if ($encodedSortOrder) {
            $selectedSortOrder = SortOrder::newFromString($encodedSortOrder);
            $query->setSortOrder($selectedSortOrder);
        }

        $encodedFacets = Tools::getValue('s');

        $query->setEncodedFacets($encodedFacets);

        $result = $provider->runQuery(
            $context,
            $query
        );

        if (!$result->getCurrentSortOrder()) {
            $result->setCurrentSortOrder($query->getSortOrder());
        }

        $products = $this->prepareMultipleProductsForTemplate(
            $result->getProducts()
        );

        if ($provider instanceof FacetsRendererInterface) {
            $rendered_facets = $provider->renderFacets(
                $context,
                $result
            );
            $rendered_active_filters = $provider->renderActiveFilters(
                $context,
                $result
            );
        } else {
            $rendered_facets = $this->renderFacets(
                $result
            );
            $rendered_active_filters = $this->renderActiveFilters(
                $result
            );
        }

        $pagination = $this->getTemplateVarPagination(
            $query,
            $result
        );

        $sort_orders = $this->getTemplateVarSortOrders(
            $result->getAvailableSortOrders(),
            $query->getSortOrder()->toString()
        );

        $sort_selected = false;
        if (!empty($sort_orders)) {
            foreach ($sort_orders as $order) {
                if (isset($order['current']) && true === $order['current']) {
                    $sort_selected = $order['label'];
                    break;
                }
            }
        }

        $currentUrlParams = [
            's'    => $result->getEncodedFacets(),
            'c'    => Tools::getValue('c', null),
            'ajax' => null
        ];

        if ((Tools::getIsset('order') || Tools::getIsset('orderby')) && $result->getCurrentSortOrder() != null) {
            $currentUrlParams['order'] = $result->getCurrentSortOrder()->toString();
        }

        $searchVariables = [
            'result'                  => $result,
            'label'                   => $this->getListingLabel(),
            'products'                => $products,
            'sort_orders'             => $sort_orders,
            'sort_selected'           => $sort_selected,
            'pagination'              => $pagination,
            'rendered_facets'         => $rendered_facets,
            'rendered_active_filters' => $rendered_active_filters,
            'js_enabled'              => $this->ajax,
            'current_url'             => $this->updateQueryString($currentUrlParams)
        ];

        Hook::exec('actionProductSearchComplete', $searchVariables);

        if (version_compare(_PS_VERSION_, '1.7.1.0', '>=')) {
            Hook::exec('filterProductSearch', ['searchVariables' => &$searchVariables]);
            Hook::exec('actionProductSearchAfter', $searchVariables);
        }

        return $searchVariables;
    }

    public function initContent()
    {
        parent::initContent();
        $this->doProductSearch('catalog/listing/search');
    }

    protected function getTemplateVarSortOrders(array $sortOrders, $currentSortOrderURLParameter)
    {
        return array_map(function ($sortOrder) use ($currentSortOrderURLParameter) {
            $order = $sortOrder->toArray();
            $order['current'] = $order['urlParameter'] === $currentSortOrderURLParameter;
            $order['url'] = $this->updateQueryString([
                'order' => $order['urlParameter'],
                'page'  => null,
                'ajax'  => null
            ]);

            return $order;
        }, $sortOrders);
    }

    protected function getAjaxProductSearchVariables()
    {
        $listing = parent::getAjaxProductSearchVariables();

        $this->context->smarty->assign([
            'listing'  => $listing,
            'ccresult' => CcAdvancedSearch::getInstance()->getResult()
        ]);
        
        $listing['rendered_search_popup'] = $this->module->fetch("module:{$this->module->name}/views/templates/hook/popups/theme.tpl");

        return $listing;
    }
}
