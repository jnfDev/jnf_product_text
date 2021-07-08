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
            $this->removeDatabase()
        ;
    }

    public function createDatabase()
    {
        $db = Db::getInstance();

        $database     = $this->database;
        $databaseLang = $database . '_lang';

        $sql1 = "CREATE TABLE IF NOT EXISTS `$database` (
            `id_producttext`  INT(10) UNSIGNED AUTO_INCREMENT,
            `id_product`      INT(10) UNSIGNED NOT NULL UNIQUE,
            `date_add`        DATETIME NOT NULL,
            `date_upd`        DATETIME NOT NULL,
            PRIMARY KEY (`id_producttext`, `id_product`)
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=UTF8;";

        $sql2 = "CREATE TABLE IF NOT EXISTS `$databaseLang` (
            `id_producttext`  INT(10) UNSIGNED NOT NULL,
            `id_lang`         INT(10) UNSIGNED NOT NULL UNIQUE,
            `product_text`    TEXT,
            PRIMARY KEY (`id_producttext`, `id_lang`)
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=UTF8;";

        return $db->execute($sql1) && $db->execute($sql2);
    }

    public function removeDatabase()
    {
        $db  = Db::getInstance();
        
        $database     = $this->database;
        $databaseLang = $database . '_lang';

        $sql1 = "DROP TABLE IF EXISTS `$database`";
        $sql2 = "DROP TABLE IF EXISTS `$databaseLang`";
       
        return $db->execute($sql1) && $db->execute($sql1);
    }

    public function getProductText($idProduct, $id_lang, $only_text = true)
    {
        $db  = Db::getInstance();

        $colums = "pt.`id_producttext` as `id`, ptl.`product_text` as `text`";

        if ($only_text !== false) {
            $colums = "ptl.`product_text` as `text`";
        }

        $database     = $this->database;
        $databaseLang = $database . '_lang';
        $idProduct    = (int) $idProduct;
        $id_lang      = (int) $id_lang;

        $sql = "SELECT $colums FROM `$database` pt
            INNER JOIN `$databaseLang` ptl ON ptl.`id_producttext` = pt.`id_producttext`
            WHERE pt.`id_product` = $idProduct AND 
                  ptl.`id_lang` = $id_lang
        ";

        return ($only_text !== false) ? 
            $db->getValue($sql) : $db->getRow($sql);
    }

    public function updateProductText($idProduct, $productText, $id_lang)
    {     
        $database     = $this->database;
        $databaseLang = $database . '_lang';
        $idProduct    = (int) $idProduct;
        $id_lang      = (int) $id_lang;
        $productText  = pSQL($productText);
        $dataUpd      = date('Y-m-d H:i:s');

        $sql = "UPDATE `$database` pt
            INNER JOIN `$databaseLang` ptl ON ptl.`id_producttext` = pt.`id_producttext`
            SET pt.`date_upd`      = '$dataUpd',
                ptl.`product_text` = '$productText'

            WHERE pt.`id_product`  = $idProduct AND ptl.`id_lang` = $id_lang;
        ";

        return Db::getInstance()->execute($sql);
    }

    public function insertProductText($idProduct, $productText, $id_lang)
    {
        $db     = Db::getInstance();
        $query1 = array(
            'id_product'   => (int) $idProduct,
            'date_add'     => date('Y-m-d H:i:s'),
            'date_upd'     => date('Y-m-d H:i:s'),
        );

        $result = $db->insert($this->database, $query1, false, true, Db::INSERT, false);

        if ($result === true) {
            $sql = "SELECT `id_producttext` FROM `$this->database` WHERE `id_product`=" . (int) $idProduct;
            $idProductText = $db->getValue($sql);

            if (empty($idProductText)) {
                return false;
            }

            $query2 = array(
                'id_producttext' => (int) $idProductText,
                'id_lang'        => $id_lang,
                'product_text'   => $productText,
            );

            $result &= $db->insert($this->database, $query2, false, true, Db::INSERT, false);
        }
        
        return $result;
    }

    /** Hooks  */

    public function hookActionAdminProductsControllerSaveBefore($params)
    {
        $idProduct = (int) Tools::getValue('id_product');
        $languages = $this->context->language->getLanguages();

        foreach ($languages as $lang) {
            $langIsoCode = $lang['iso_code'];
            $currentProductText = $this->getProductText($idProduct, $lang['id_lang']);
            $productText        = Tools::getValue('product_text_'. $langIsoCode);

            if (empty($productText) || $productText === $currentProductText) {
                continue;
            }

            if ($currentProductText !== false) {
                if(!$this->updateProductText($idProduct, Tools::safeOutput($productText, true), $lang['id_lang'])) {
                    throw new Exception("Error updaging your record");
                }
            } else {
                if(!$this->insertProductText($idProduct, Tools::safeOutput($productText, true), $lang['id_lang'])) {
                    throw new Exception("Error inserting your record");
                }
            }
        }
    }

    public function hookDisplayAdminProductsMainStepLeftColumnBottom($params)
    {
        if ($this->isSymfonyContext() && $params['route'] === 'admin_product_form') {

            $productText = array();
            $languages   = $this->context->language->getLanguages();

            foreach ($languages as $lang) {
                $langIsoCode = $lang['iso_code'];
                $_productText = $this->getProductText($params['id_product'], $lang['id_lang']);
                $productText[$langIsoCode] = Tools::htmlentitiesDecodeUTF8($_productText);
            }

            /**
             * Note: for some reason $this->get('twig') didn't work, 
             * read more here: https://github.com/PrestaShop/PrestaShop/issues/20505#issuecomment-805884349
             */
            return SymfonyContainer::getInstance()->get('twig')->render('@Modules/'. $this->name .'/views/templates/admin/product_text.twig', [
                'product_text'   => $productText,
                'languages'      => array_column($languages, 'iso_code'),
                'default_lag'    => $this->context->language->iso_code,
            ]);
        }
    }

    public function hookDisplayReassurance($params)
    {
        $_html       = '';
        $idProduct   = (int) Tools::getValue('id_product');
        $id_lang     = $this->context->language->id;
        $productText = $this->getProductText($idProduct, $id_lang);
        
        if (!empty($productText)) {
            $_html .= '<div id="product-text">';
            $_html .= Tools::htmlentitiesDecodeUTF8($productText);
            $_html .= '</div>';
        }

        return $_html;
    }
}