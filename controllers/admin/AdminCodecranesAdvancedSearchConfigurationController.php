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

class AdminCodecranesAdvancedSearchConfigurationController extends ModuleAdminController
{
    public function init()
    {
        parent::init();
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', false) . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&configure=' . Tools::safeOutput($this->module->name));
    }
}
