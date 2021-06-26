<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class Jnf_Producttext extends Module
{
    /**
     * @var string $database
     */
    private $database;

    public function __construct()
    {
        $this->name = 'jnf_producttext';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'JnfDev';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->database    = _DB_PREFIX_ . $this->name;
        $this->displayName = $this->trans('Product Text', [], 'Modules.Jnfimporter.Jnfimporter');
        $this->description = $this->trans('This plugins show text on product page. This plugin is an "admission test" for Interfell.');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', [], 'Modules.Jnfimporter.Jnfimporter');
    }

    public function install()
    {
        return parent::install() &&
            $this->createDatabase() &&
            $this->registerHook('displayAdminProductsMainStepLeftColumnBottom') &&
            $this->registerHook('actionAdminProductsControllerSaveBefore') &&
            $this->registerHook('displayReassurance')
        ;
    }

    public function unistall()
    {
        return parent::uninstall() &&
            $this->removeDatabase();
    }

    public function createDatabase()
    {
        $db = Db::getInstance();

        $sql = "CREATE TABLE IF NOT EXISTS `$this->database` (
            `id_producttext`  INT(10) UNSIGNED AUTO_INCREMENT,
            `id_product`      INT(10) unsigned NOT NULL UNIQUE,
            `product_text`    TEXT,
            `date_add`        DATETIME NOT NULL,
            `date_upd`        DATETIME NOT NULL,
            PRIMARY KEY (`id_producttext`, `id_product`)
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=UTF8;";

        return $db->execute($sql);
    }

    public function removeDatabase()
    {
    
        $db  = Db::getInstance();
        $sql = "DROP TABLE IF EXISTS `$this->database`";
       
        return $db->execute($sql);
    }

    public function getProductText($idProduct, $only_text = false)
    {
        $db  = Db::getInstance();

        $colums = "`id_producttext` as `id`, `product_text` as `text`";

        if ($only_text !== false) {
            $colums = "`product_text` as `text`";
        }

        $sql = "SELECT $colums FROM `$this->database`
            WHERE `id_product` = " . (int) $idProduct;

        return ($only_text !== false) ? 
            $db->getValue($sql) : $db->getRow($sql);
    }

    public function updateProductText($idProduct, $productText)
    {
        $db    = Db::getInstance();
        $query = array(
            'product_text' => pSQL($productText),
            'date_upd'     => date('Y-m-d H:i:s'),
        );

        $where = 'id_product = ' . (int) $idProduct;

        return $db->update($this->database, $query, $where, 1, false, true, false);
    }

    public function insertProductText($idProduct, $productText)
    {
        $db    = Db::getInstance();
        $query = array(
            'id_product'   => (int) $idProduct,
            'product_text' => pSQL($productText),
            'date_add'     => date('Y-m-d H:i:s'),
            'date_upd'     => date('Y-m-d H:i:s'),
        );
        
        return $db->insert($this->database, $query, false, true, Db::INSERT, false );
    }

    /** Hooks  */

    public function hookActionAdminProductsControllerSaveBefore($params)
    {
        $idProduct   = (int) Tools::getValue('id_product');
        $productText = Tools::safeOutput(Tools::getValue('product_text'), true);

        if (Tools::getValue('id_producttext')) {
            $this->updateProductText($idProduct, $productText);
        } else {
            $this->insertProductText($idProduct, $productText);
        }
    }

    public function hookDisplayAdminProductsMainStepLeftColumnBottom($params)
    {
        if ($this->isSymfonyContext() && $params['route'] === 'admin_product_form') {

            $productTextRecord = $this->getProductText($params['id_product']);

            $idProductText = isset($productTextRecord['id']) ? (int) $productTextRecord['id'] : false;
            $productText   = isset($productTextRecord['text']) ? Tools::htmlentitiesDecodeUTF8($productTextRecord['text']) : '';

            /**
             * Note: for some reason $this->get('twig') didn't work, 
             * read more here: https://github.com/PrestaShop/PrestaShop/issues/20505#issuecomment-805884349
             */
            return SymfonyContainer::getInstance()->get('twig')->render('@Modules/'. $this->name .'/views/templates/admin/product_text.twig', [
                'id_producttext' => $idProductText,
                'product_text'   => $productText,
            ]);
        }
    }

    public function hookDisplayReassurance($params)
    {
        $_html       = '';
        $idProduct   = (int) Tools::getValue('id_product');
        $productText = $this->getProductText($idProduct, true);
        
        if (!empty($productText)) {
            $_html .= '<div id="product-text">';
            $_html .= Tools::htmlentitiesDecodeUTF8($productText);
            $_html .= '</div>';
        }

        return $_html;
    }
}