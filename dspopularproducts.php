<?php
/**
* 2007-2021 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

require 'vendor/autoload.php';

class Dspopularproducts extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'dspopularproducts';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Dark-Side.pro';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('DS: Popular products');
        $this->description = $this->l('Display a popular product list on the homepage');

        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('DSPOPULARPRODUCTS_VIEW', false);

        include(dirname(__FILE__).'/sql/install.php');
        $this->genereteFreshData();

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('actionProductSave') &&
            $this->registerHook('actionProductUpdate') &&
            $this->registerHook('displayAdminProductsExtra') &&
            $this->registerHook('actionProductDelete') &&
            $this->registerHook('displayHome');
    }

    public function uninstall()
    {
        Configuration::deleteByName('DSPOPULARPRODUCTS_LIVE_MODE');
        $this->deleteAllData();

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    protected function deleteAllData()
    {
        $db = \Db::getInstance();
        $sql = 'DELETE FROM '._DB_PREFIX_.'dspopularproducts';
        $result = $db->execute($sql);
    }

    protected function genereteFreshData()
    {
        $db = \Db::getInstance();
        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'dspopularproducts (id_product, status, position) SELECT id_product, 0, 0 FROM ' . _DB_PREFIX_ . 'product';
        $result = $db->execute($sql);
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitDspopularproductsModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitDspopularproductsModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('View mode'),
                        'name' => 'DSPOPULARPRODUCTS_VIEW',
                        'is_bool' => true,
                        'desc' => $this->l('Display products as'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('List')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Carousel')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'DSPOPULARPRODUCTS_VIEW' => Configuration::get('DSPOPULARPRODUCTS_VIEW', true),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
        $this->context->controller->addCSS($this->_path.'/views/css/owl.carousel.min.css');
        $this->context->controller->addCSS($this->_path.'/views/css/owl.theme.default.min.css');
        $this->context->controller->addJS($this->_path.'/views/js/owl.carousel.min.js');
        $this->context->controller->addJS($this->_path.'/views/js/front.js');



        $this->context->controller->registerJavascript(1, $this->_path.'/views/js/owl.carousel.min.js');
        $this->context->controller->registerJavascript(4, $this->_path.'/views/js/front.js'); 
        $this->context->controller->registerStylesheet(1, $this->_path.'/views/css/front.css');
        $this->context->controller->registerStylesheet(2, $this->_path.'/views/css/owl.carousel.min.css');
        $this->context->controller->registerStylesheet(3, $this->_path.'/views/css/owl.theme.default.min.css');
    }

    public function hookDisplayHome()
    {
        $productsIds = $this->getProducts();
        $productsData = array();
        $id_lang = $this->context->cookie->id_lang;

        foreach ($productsIds as $product) {
            $productId = $product['id_product'];
            $productDetails = $this->getProductDetails($productId, $id_lang);
            $productName = $productDetails->name;
            $productLink = $this->context->link->getProductLink($productId);
            $image = Product::getCover($productId);
            $imageurl = $this->context->link->getImageLink($productDetails->link_rewrite, $image['id_image'], 'home_default');

            $productPriceNetto = Product::getPriceStatic($productId, false);
            $productPriceBrutto = Product::getPriceStatic($productId, true);

            array_push($productsData, [
                'product_name' => $productName, 
                'product_image' => $imageurl, 
                'product_id' => $productId, 
                'product_price_netto' => Tools::displayPrice($productPriceNetto), 
                'product_price_brutto' => Tools::displayPrice($productPriceBrutto),
                'product_link' => $productLink
                ]
            );
        }

        $this->context->smarty->assign('products', $productsData);
        $this->context->smarty->assign('viewMode', Configuration::get('DSPOPULARPRODUCTS_VIEW'));

        return $this->display(__FILE__, 'displayHome.tpl');
    }

    protected function getProducts(): array
    {
        $sql = new DbQuery;
        $sql->select('*')
            ->from('dspopularproducts')
            ->orderBy('position DESC')
            ->where('status = 1');

        $result = Db::getInstance()->executeS($sql);      

        return $result;
    }

    protected function getProductDetails(int $id_product, int $id_lang): Product
    {
        $product = new Product($id_product, false, $id_lang);

        return $product;
    }

    protected function deleteData(int $id): void
    {
        $dspopularproduct = new DSPopularProduct($id);
        $dspopularproduct->delete();

    }

    protected function createPopularProduct(int $id_product, int $status, int $position = 0): int
    {
        $dspopularproduct = new DSPopularProduct();
        $dspopularproduct->id_product = $id_product;
        $dspopularproduct->status = $status;
        $dspopularproduct->position = $position;
        $dspopularproduct->add();

        return $dspopularproduct->id;
    }

    protected function updateData(int $id_product, int $status, int $position = 0): int
    {
        $sql = new DbQuery;
        $sql->select('id')
            ->from('dspopularproducts')
            ->where('id_product ='.$id_product)
            ->limit(1);

        $result = Db::getInstance()->executeS($sql); 
        $id = $result[0]['id'];

        $dspopularproduct = new DSPopularProduct($id);
        $dspopularproduct->status = $status;
        $dspopularproduct->position = $position;
        $dspopularproduct->update();

        return $dspopularproduct->id;
    }

    public function hookActionProductSave($params)
    {
        $id_product = $params['id_product'];
        $status = (int) Tools::getValue('dsppStatus');
        $dspopularproductId = $this->getDSPopularProductByIdProduct($id_product);

        if ($dspopularproductId == false) {
            $this->createPopularProduct($id_product, $status);
        } else {
            $this->updateData($id_product, $status);
        }
    }

    public function hookActionProductUpdate($params)
    {
        $id_product = $params['id_product'];
        $status = (int) Tools::getValue('dsppStatus');

        $this->updateData($id_product, $status);
    }

    public function hookdisplayAdminProductsExtra($params)
    {
        $id_product = $params['id_product'];
        $dspopularproductId = $this->getDSPopularProductByIdProduct($id_product);
        $dspopularproduct = $this->getDSPopularProduct($dspopularproductId);
        $status = $dspopularproduct->status;

        $this->context->smarty->assign('status', $status);

        return $this->context->smarty->fetch($this->local_path.'views/templates/hook/displayAdminProductsExtra.tpl');
    }
    

    protected function getDSPopularProductByIdProduct(int $id_product)
    {
        $sql = new DbQuery;
        $sql->select('id')
            ->from('dspopularproducts')
            ->where('id_product ='.$id_product);

        $result = Db::getInstance()->executeS($sql); 
        
        if (!empty($result)) {
            return (int) $result[0]['id'];
        }

        return false;
    }

    public function hookActionProductDelete($params)
    {
        $id_product = $params['id_product'];
        $this->deleteData($id_product);
    }

    protected function getDSPopularProduct(int $id): DSPopularProduct
    {
        return new DSPopularProduct($id);
    }
}
