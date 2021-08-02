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

require_once dirname(__FILE__) . '/classes/CcAdvancedSearch.php';
require_once dirname(__FILE__) . '/classes/CcAdvancedSearchProvider.php';

class Codecranes_AdvancedSearch extends Module
{
    protected $_html = '';
    protected $_cc_tabs = [];

    public function __construct()
    {
        $this->name = 'codecranes_advancedsearch';
        $this->tab = 'front_office_features';
        $this->version = '1.0.3';
        $this->author = 'Codecranes';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Advanced Search');
        $this->description = $this->l('The plugin extends the search box functionality. Product suggestions will appear with attractive product information and images instead of the simple name list.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall? Remember that you can go back to the native Prestashop search box by disabling the module.');

        $this->_cc_tabs = [
            'AdminCodecranesSettings' => [
                'name'     => 'Codecranes',
                'position' => '0',
                'module'   => false,
                'tabs'     => [
                    'AdminCodecranesAdvancedSearchConfiguration' => [
                        'name'     => 'Advanced Search',
                        'position' => '10',
                        'icon'     => 'search'
                    ]
                ]
            ]
        ];
    }

    public function install()
    {
        if (
            !parent::install()
            || !$this->registerHook('header')
            || !$this->registerHook('actionCategoryAdd')
            || !$this->registerHook('actionCategoryDelete')
            || !$this->registerHook('actionCategoryUpdate')
            || !$this->registerHook('moduleRoutes')
            || !$this->registerHook('actionAdminControllerSetMedia')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->installConfiguration()
        ) {
            parent::uninstall();
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        return (parent::uninstall()
            && $this->unregisterHook('header')
            && $this->unregisterHook('actionCategoryAdd')
            && $this->unregisterHook('actionCategoryDelete')
            && $this->unregisterHook('actionCategoryUpdate')
            && $this->unregisterHook('moduleRoutes')
            && $this->unregisterHook('actionAdminControllerSetMedia')
            && $this->unregisterHook('displayBackOfficeHeader')
            && $this->uninstallTab()
            && $this->uninstallConfiguration());
    }

    public function enable($force_all = false)
    {
        return parent::enable($force_all)
            && $this->installTab();
    }

    public function disable($force_all = false)
    {
        return parent::disable($force_all)
            && $this->uninstallTab();
    }

    protected function getDefaultConfiguration()
    {
        return [
            'CODECRANES_ADVANCEDSEARCH_SETTINGS_OR_AND'       => '0',
            'CODECRANES_ADVANCEDSEARCH_SETTINGS_CATEGORY'     => '0',
            'CODECRANES_ADVANCEDSEARCH_SETTINGS_ACIVE'        => '0',
            'CODECRANES_ADVANCEDSEARCH_SETTINGS_SHOW_AGAIN'   => '0',
            'CODECRANES_ADVANCEDSEARCH_SETTINGS_SHOW_IN_MENU' => '0',
            'CODECRANES_ADVANCEDSEARCH_SETTINGS_CSS'          => ''
        ];
    }

    protected function installConfiguration()
    {
        $config = $this->getDefaultConfiguration();
        $shops = Shop::getShops();

        foreach ($config as $key => $value) {
            foreach ($shops as $id_shop => $shop) {
                Configuration::updateValue($key, $value, false, null, (int) $id_shop);
            }
        }

        return true;
    }

    protected function uninstallConfiguration()
    {
        $config = $this->getDefaultConfiguration();

        foreach ($config as $key => $value) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    protected function installTab()
    {
        $show_in_menu = (int) Configuration::get('CODECRANES_ADVANCEDSEARCH_SETTINGS_SHOW_IN_MENU', null, null, (int) $this->context->shop->id, '0');

        if ($show_in_menu != '1') {
            return;
        }

        if (!$this->addTab('0', $this->_cc_tabs)) {
            return false;
        }

        return true;
    }

    protected function addTab($id_parent, $tabs)
    {
        $langs = Language::getLanguages();

        foreach ($tabs as $class_name => $tab_row) {
            $tab_class = false;

            if ($id_tab = (int) Tab::getIdFromClassName($class_name)) {
                $tab_class = new Tab($id_tab);

                if ($tab_class->active != '0' || $tab_class->id_parent != '0') {
                    $tab_class = false;
                }
            }

            if (!$id_tab || $tab_class) {
                if ($tab_class) {
                    $tab = $tab_class;
                } else {
                    $tab = new Tab();
                }

                $tab->active = isset($tab_row['active']) ? (int) $tab_row['active'] : 1;
                $tab->class_name = $class_name;
                $tab->position = $tab_row['position'];
                $tab->icon = isset($tab_row['icon']) ? $tab_row['icon'] : null;
                $tab->id_parent = $id_parent;
                $tab->module = isset($tab_row['module']) && !$tab_row['module'] ?: $this->name;
                $tab->name = [];

                foreach ($langs as $lang) {
                    $tab->name[$lang['id_lang']] = $this->l($tab_row['name'], false, $lang['locale']);
                }

                if (!$tab->save()) {
                    $this->_errors[] = $this->l('Problem with adding a bookmark in panel: ') . Db::getInstance()->getMsgError();
                    return false;
                }

                $id_tab = $tab->id;
            }

            if (isset($tab_row['tabs'])) {
                if (!$this->addTab($id_tab, $tab_row['tabs'])) {
                    return false;
                }
            }
        }
        return true;
    }

    protected function uninstallTab()
    {
        if (!$this->deleteTab($this->_cc_tabs)) {
            return false;
        }

        return true;
    }

    protected function deleteTab($tabs)
    {
        foreach ($tabs as $class_name => $tab) {
            if (isset($tab['tabs'])) {
                if (!$this->deleteTab($tab['tabs'])) {
                    return false;
                }
            }

            $id_tab = (int) Tab::getIdFromClassName($class_name);

            if (!$id_tab) {
                continue;
            }

            if ($this->sqlGetCountTabs($id_tab) < '1') {
                $tab_class = new Tab($id_tab);

                if (!$tab_class->delete()) {
                    $this->_errors[] = $this->l('Problem with delete a bookmark in panel: ') . Db::getInstance()->getMsgError();
                    return false;
                }
            }
        }

        return true;
    }

    protected function sqlGetCountTabs($id_parent)
    {
        $and = '';

        if (defined('_PS_HOST_MODE_')) {
            $and = ' AND `hide_host_mode` = 0';
        }

        $sql = Db::getInstance()->getRow("
                SELECT COUNT(`id_tab`) as count_items
                FROM `" . _DB_PREFIX_ . "tab`
                WHERE `id_parent` = '{$id_parent}'
                    {$and}
            ;");

        if (!$sql || !is_array($sql) || count($sql) < '1') {
            return '0';
        }

        return $sql['count_items'];
    }

    public function getContent()
    {
        $this->getInfo();
        $this->getFormConfirmation();

        $this->saveForm();
        $this->getPageForm();

        $this->getAdv();

        return $this->_html;
    }

    protected function getInfo()
    {
        $id_lang = $this->context->language->id;
        $id_shop = $this->context->shop->id;

        $cache_id = "{$this->name}_info";
        $compile_id = "{$id_shop}_{$id_lang}";

        $file = "module:{$this->name}/views/templates/admin/info.tpl";

        if (!$this->isCached($file, $cache_id, $compile_id)) {
            $this->smarty->assign('cc_ajax_info', $this->getAjaxInfo());
        }

        $this->_html .= $this->fetch($file, $cache_id, $compile_id);

        if ($this->isMultishop()) {
            $this->_html .= '<div class="alert alert-info">' . $this->l('Selected store: ') . $this->context->shop->name . '</div>';
        }
    }

    protected function getAdv()
    {
        $id_lang = $this->context->language->id;
        $id_shop = $this->context->shop->id;

        $cache_id = "{$this->name}_adv";
        $compile_id = "{$id_shop}_{$id_lang}";

        $file = "module:{$this->name}/views/templates/admin/adv.tpl";

        $this->_html .= $this->fetch($file, $cache_id, $compile_id);
    }

    protected function getFormConfirmation()
    {
        if (!$notice = Tools::getValue('notice')) {
            return;
        }

        switch ($notice) {
            case '2':
                $this->_html .= '<div class="alert alert-success">' . $this->l('Successfully updated.') . '</div>';
                break;
        }
    }

    protected function isMultishop()
    {
        $shops = Shop::getShops();
        return count($shops) > '1' ? true : false;
    }

    protected function saveForm()
    {
        if (Tools::isSubmit('updatesearchsettings')) {
            $config = CcAdvancedSearch::getInstance()->getSettings();

            $post = [
                'CODECRANES_ADVANCEDSEARCH_SETTINGS_OR_AND'       => (int) Tools::getValue('or_and', '0'),
                'CODECRANES_ADVANCEDSEARCH_SETTINGS_CATEGORY'     => (int) Tools::getValue('category', '0'),
                'CODECRANES_ADVANCEDSEARCH_SETTINGS_ACIVE'        => (int) Tools::getValue('active', '0'),
                'CODECRANES_ADVANCEDSEARCH_SETTINGS_SHOW_IN_MENU' => (int) Tools::getValue('show_in_menu', '0'),
                'CODECRANES_ADVANCEDSEARCH_SETTINGS_SHOW_AGAIN'   => (int) Tools::getValue('show_again', '0'),
                'CODECRANES_ADVANCEDSEARCH_SETTINGS_CSS'          => trim(Tools::getValue('cccsstextarea_css', ''))
            ];

            foreach ($post as $key => $value) {
                Configuration::updateValue($key, $value, false, null, (int) $this->context->shop->id);
            }

            if ($config['css'] != $post['CODECRANES_ADVANCEDSEARCH_SETTINGS_CSS']) {
                file_put_contents(dirname(__FILE__) . "/views/templates/front/_assets/css/custom/styles-{$this->context->shop->id}.css", $post['CODECRANES_ADVANCEDSEARCH_SETTINGS_CSS']);
            }

            if ($config['show_in_menu'] != $post['CODECRANES_ADVANCEDSEARCH_SETTINGS_SHOW_IN_MENU']) {
                if ($post['CODECRANES_ADVANCEDSEARCH_SETTINGS_SHOW_IN_MENU'] == '1') {
                    $this->installTab();
                } else {
                    $this->uninstallTab();
                }
            }

            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false) . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&configure=' . $this->name . '&notice=2');
            exit;
        }
    }

    protected function getPageForm()
    {
        $fields_form1 = [
            'form' => [
                'legend'      => [
                    'title' => $this->l('Settings'),
                    'icon'  => 'icon-link'
                ],
                'description' => str_replace(['[a]', '[/a]'], ['<a href="' . $this->context->link->getAdminLink('AdminSearchConf', false) . '&token=' . Tools::getAdminTokenLite('AdminSearchConf') . '#alias_fieldset_relevance' . '">', '</a>'], $this->l('The fields the search engine is to search for are set by the scales in the "[a]Configure / Shop Parameters / Search / Weight[/a]" section.')),
                'input'       => [
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Enable'),
                        'name'    => 'active',
                        'is_bool' => true,
                        'values'  => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            ]
                        ]
                    ],
                    [
                        'type'     => 'select',
                        'label'    => $this->l('Search type'),
                        'name'     => 'or_and',
                        'required' => true,
                        'options'  => [
                            'query' => [
                                [
                                    'id'   => '0',
                                    'name' => $this->l('one word or another (OR)')
                                ],
                                [
                                    'id'   => '2',
                                    'name' => $this->l('all words (AND)')
                                ]
                            ],
                            'id'    => 'id',
                            'name'  => 'name'
                        ]
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Search within main categories'),
                        'name'    => 'category',
                        'is_bool' => true,
                        'desc'    => $this->l('Displays a list of categories next to the search input.'),
                        'values'  => [
                            [
                                'id'    => 'category_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ],
                            [
                                'id'    => 'category_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $fields_form2 = [
            'type' => 'collapse',
            'form' => [
                'legend' => [
                    'title' => $this->l('Advanced settings') . ' <i class="material-icons sub-tabs-arrow">keyboard_arrow_down</i>',
                    'icon'  => 'icon-link'
                ],
                'input'  => [
                    [
                        'type'  => 'cccsstextarea',
                        'label' => $this->l('CSS'),
                        'name'  => 'css',
                        'desc'  => str_replace(['[a]', '[/a]'], ['<a href="' . $this->context->link->getAdminLink('AdminPerformance', true) . '">', '</a>'], $this->l("[a]If you don't see the changes, you may need to refresh the cache (click to open).[/a]."))

                    ],

                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Show results again'),
                        'name'    => 'show_again',
                        'is_bool' => true,
                        'desc'    => $this->l('Always show results after clicking in the search input.'),
                        'values'  => [
                            [
                                'id'    => 'show_again_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ],
                            [
                                'id'    => 'show_again_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            ]
                        ]
                    ],

                    [
                        'type'    => 'switch',
                        'label'   => $this->l('Show module in menu'),
                        'name'    => 'show_in_menu',
                        'is_bool' => true,
                        'desc'    => $this->l('Shows the link in the back-office menu in the left-hand panel.'),
                        'values'  => [
                            [
                                'id'    => 'show_in_menu_on',
                                'value' => 1,
                                'label' => $this->l('On')
                            ],
                            [
                                'id'    => 'show_in_menu_off',
                                'value' => 0,
                                'label' => $this->l('Off')
                            ]
                        ]
                    ]

                ]
            ]
        ];

        $fields_form3 = [
            'type' => 'blank',
            'form' => [
                'legend' => [
                    'title' => $this->l('Save'),
                    'icon'  => 'icon-link'
                ],
                'submit' => [
                    'name'  => 'updatesearchsettings',
                    'title' => $this->l('Save')
                ]
            ]
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->fields_value = CcAdvancedSearch::getInstance()->getSettings();
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&configure=' . $this->name;

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->languages = Language::getLanguages();
        $helper->module = $this;
        $helper->default_form_language = (int) $this->context->language->id;

        return $this->_html .= $helper->generateForm([$fields_form1, $fields_form2, $fields_form3]);
    }

    protected function categoryClearCache($params)
    {
        switch ($params['type']) {
            case 'category':
                $cache_id = "{$this->name}_category";
                $file = "module:{$this->name}/views/templates/hook/category_select.tpl";

                foreach (Language::getLanguages() as $lang) {
                    $compile_id = "{$params['id_shop']}_{$lang['id_lang']}";
                    $this->_clearCache($file, $cache_id, $compile_id);
                }
                break;
        }
    }

    protected function getCategorySelect()
    {
        $id_lang = $this->context->language->id;
        $id_shop = $this->context->shop->id;
        $customer_groups = $this->context->customer->getGroups();

        $cache_id = "{$this->name}_category";
        $compile_id = "{$id_shop}_{$id_lang}_" . implode('|', $customer_groups);

        $file = "module:{$this->name}/views/templates/hook/category_select.tpl";

        if (!$this->isCached($file, $cache_id, $compile_id)) {
            $category = new Category(Category::getRootCategory((int) $id_lang, $this->context->shop)->id);
            $this->smarty->assign('cc_categories', $category->getSubCategories((int) $id_lang));
        }

        return $this->fetch($file, $cache_id, $compile_id);
    }

    protected function getAjaxInfo()
    {
        static $return = null;

        if ($return !== null) {
            return $return;
        }

        return $return = [
            'module'     => $this->name,
            'storev'     => _PS_VERSION_,
            'modulev'    => $this->version,
            'domain'     => Tools::getShopDomain(),
            'phpv'       => phpversion(),
            'isocode'    => $this->context->language->iso_code,
            'multistore' => (int) $this->isMultishop()
        ];
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        if (Tools::getValue('configure', '') == 'codecranes_advancedsearch') {
            $this->context->controller->addCSS(($this->_path) . 'views/templates/admin/_assets/css/styles.css', 'all');
        }
    }

    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('configure', '') == 'codecranes_advancedsearch') {
            Media::addJsDef(['codecranes_advancedsearch_ajax_info' => $this->getAjaxInfo()]);

            $this->context->controller->addJS(($this->_path) . 'views/templates/admin/_libs/ace/ace.js', 'all');
            $this->context->controller->addJS(($this->_path) . 'views/templates/admin/_assets/js/scripts.js', 'all');
        }
    }

    public function hookModuleRoutes()
    {
        return [
            'module-codecranes_advancedsearch-casearch' => [
                'controller' => 'casearch',
                'rule'       => 'casearch',
                'keywords'   => [
                    's' => ['regexp' => '.*', 'param' => 's']
                ],
                'params'     => [
                    'fc'     => 'module',
                    'module' => 'codecranes_advancedsearch'
                ]
            ]
        ];
    }

    public function hookDisplayHeader()
    {
        $config = CcAdvancedSearch::getInstance()->getSettings();

        if ($config['active'] != '1') {
            return;
        }

        if (Module::isEnabled('ps_searchbar')) {
            $this->context->controller->unregisterJavascript('modules-searchbar');
        }

        $this->context->controller->registerJavascript('codecranes_advancedsearch', ($this->_path) . 'views/templates/front/_assets/js/scripts.js', ['position' => 'bottom', 'priority' => 150]);
        $this->context->controller->addCSS("{$this->_path}views/templates/front/_assets/css/styles.css", 'all');

        $config['ajax_link'] = $this->context->link->getModuleLink($this->name, 'casearch', []);
        $config['categories_select'] = $config['category'] == '1' ? $this->getCategorySelect() : false;

        Media::addJsDef(['codecranes_advancedsearch_config' => $config]);

        $style = trim($config['css']);

        if (!empty($style)) {
            $this->context->controller->addCSS("{$this->_path}views/templates/front/_assets/css/custom/styles-{$this->context->shop->id}.css", 'all');
        }
    }

    public function hookActionCategoryAdd($params)
    {
        $this->categoryClearCache(['id_category' => $params['id_category'], 'id_shop' => $this->context->shop->id, 'type' => 'category']);
    }

    public function hookActionCategoryUpdate($params)
    {
        $this->categoryClearCache(['id_category' => $params['id_category'], 'id_shop' => $this->context->shop->id, 'type' => 'category']);
    }

    public function hookActionCategoryDelete($params)
    {
        $this->categoryClearCache(['id_category' => $params['id_category'], 'id_shop' => $this->context->shop->id, 'type' => 'category']);
    }
}
