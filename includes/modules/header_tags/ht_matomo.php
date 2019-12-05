<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @licence MIT - Portion of osCommerce 2.4
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;

  class ht_matomo
  {
    public $code;
    public $group;
    public $title;
    public $description;
    public $sort_order;
    public $enabled = false;

    public function __construct()
    {
      $this->code = get_class($this);
      $this->group = basename(__DIR__);

      $this->title = CLICSHOPPING::getDef('module_header_tags_matomo_title');
      $this->description = CLICSHOPPING::getDef('module_header_tags_matomo_description');

      if (defined('MODULE_HEADER_TAGS_MATOMO_STATUS')) {
        $this->sort_order = MODULE_HEADER_TAGS_MATOMO_SORT_ORDER;
        $this->enabled = (MODULE_HEADER_TAGS_MATOMO_STATUS == 'True');
      }
    }

    public function execute()
    {
      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_Language = Registry::get('Language');
      $CLICSHOPPING_Db = Registry::get('Db');
      $CLICSHOPPING_Category = Registry::get('Category');
      $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
      $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
      $CLICSHOPPING_Customer = Registry::get('Customer');

      if (MODULE_HEADER_TAGS_MATOMO_EC_TRACKING == 'True') {

        $footer = '<!-- Piwik -->
  <script type="text/javascript">
  var pkBaseURL = '. HTML::outputProtected(MODULE_HEADER_TAGS_MATOMO_HTTP_URL) . ';
  document.write(unescape("%3Cscript src=\'" + pkBaseURL + "piwik.js\' type=\'text/javascript\'%3E%3C/script%3E"));
  </script><script type="text/javascript">
  try {
  var piwikTracker = Piwik.getTracker(pkBaseURL + "piwik.php", ' . (int)MODULE_HEADER_TAGS_MATOMO_ID . ');' . "\n";

        if ( (MODULE_HEADER_TAGS_MATOMO_EC_TRACKING == 'True') && !empty($CLICSHOPPING_Category->getID())) {
          $Qcategories = $CLICSHOPPING_Db->prepare('select c.categories_id,
                                                           c.categories_image,
                                                           cd.categories_name
                                                   from :table_categories_description cd join :table_categories c on c.categories_id = cd.categories_id
                                                   where c.parent_id = 0
                                                   and c.status = 1
                                                   and cd.language_id = :language_id
                                                   and virtual_categories = 0
                                                   order by cd.categories_name
                                                  ');

          $Qcategories->bindInt(':language_id', $CLICSHOPPING_Language->getId());

          $Qcategories->execute();

          if (empty($Qcategories->value('categories_name'))) {
            $footer .= 'piwikTracker.setEcommerceView(productSku = false,productName = false,category = "' . HTML::outputProtected($Qcategories->value('categories_name')) . '");' . "\n";
          }
        }

        if ($CLICSHOPPING_ProductsCommon->getID()) {
          $products_id = (int)$CLICSHOPPING_ProductsCommon->getID();
/*
          $products_query = tep_db_query("select p.products_id, pd.products_name, cd.categories_name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c, ". TABLE_CATEGORIES_DESCRIPTION ." cd WHERE p.products_id = pd.products_id and p2c.categories_id = cd.categories_id and p.products_id = " . (int)$HTTP_GET_VARS['products_id'] . " and pd.language_id ='" . (int)$languages_id . "' and cd.language_id ='".(int)$languages_id."'");
          $products = tep_db_fetch_array($products_query);
*/
          $footer .= 'piwikTracker.setEcommerceView("' . (int)$CLICSHOPPING_ProductsCommon->getID() . '","' . HTML::outputProtected($CLICSHOPPING_ProductsCommon->getProductsName($products_id)) . '","' . HTML::outputProtected($CLICSHOPPING_Category->getTitle()) . '");' . "\n";
//          $header .= 'piwikTracker.setEcommerceView("' . (int)$products['products_id'] . '","' . tep_output_string($products['products_name']) . '","' . tep_output_string($products['categories_name']) . '");' . "\n";
        }

        $products = $CLICSHOPPING_ShoppingCart->get_products();

        if (isset($_GET['Cart']) && $CLICSHOPPING_ShoppingCart->getCountContents() > 0) {
          for ($i=0, $n=count($products); $i<$n; $i++) {
            $Qcategories = $CLICSHOPPING_Db->prepare('select cd.categories_name
                                                       from :table_categories_description cd,
                                                            :table_products_to_categories c
                                                       where cd.categories_id = p2c.categories_id 
                                                       and c.status = 1
                                                       and cd.language_id = :language_id
                                                       and virtual_categories = 0
                                                       and  p2c.products_id = :products_id
                                                       order by cd.categories_name
                                                    ');

            $Qcategories->bindInt(':language_id', $CLICSHOPPING_Language->getId());
            $Qcategories->bindInt(':products_id', $products[$i]['id']);

            $Qcategories->execute();

            $footer .= 'piwikTracker.addEcommerceItem("' . (int)$products[$i]['id'] . '","' . HTML::outputProtected($products[$i]['name']) . '","'. HTML::outputProtected($Qcategories->value('categories_name')) . '",' . $this->format_raw($products[$i]['final_price']) . ',' . (int)$products[$i]['quantity'] . ');' . "\n";
          }

          $footer .= 'piwikTracker.trackEcommerceCartUpdate(' . $this->format_raw($CLICSHOPPING_ShoppingCart->show_total()) . ');' . "\n";
        }

        if (isset($_GET['Checkout']) && isset($_GET['Success']) && $CLICSHOPPING_Customer->isLoggedOn() && $CLICSHOPPING_Customer->getID()) {
          $Qorders = $CLICSHOPPING_Db->get('orders', 'orders_id', ['customers_id' => $CLICSHOPPING_Customer->getID()], 'orders_id desc', 1);
          $last_order = $Qorders->valueInt('orders_id');

          if ($last_order > 1) {
            $totals = [];

            $QorderTotals = $CLICSHOPPING_Db->prepare('select value,
                                                              class
                                                        from :table_orders_total
                                                        where orders_id = :orders_id
                                                     ');

            $QorderTotals->bindInt(':orders_id', (int)$last_order);
            $QorderTotals->execute();

            while ($order_totals = $QorderTotals->fetch()) {
              $totals[$order_totals['class']] = $order_totals['value'];
            }

            $QorderProducts = $CLICSHOPPING_Db->prepare('select op.products_id,
                                                                 pd.products_name,
                                                                 op.final_price,
                                                                 op.products_quantity
                                                            from :table_orders_products op,
                                                                 :table_products_description pd,
                                                                 :table_languages  l
                                                            where op.orders_id = :orders_id
                                                            and op.products_id = pd.products_id
                                                            and l.code = :code
                                                            and l.languages_id = pd.language_id
                                                         ');

            $QorderProducts->bindInt(':orders_id', $last_order);
            $QorderProducts->bindValue(':code', DEFAULT_LANGUAGE);

            $QorderProducts->execute();

            while ($QorderProducts->fetch()) {
              $Qcategory = $CLICSHOPPING_Db->prepare('select cd.categories_name
                                                      from categories_description cd,
                                                           products_to_categories p2c,
                                                           languages  l
                                                      where p2c.products_id = :products_id
                                                      and p2c.categories_id = cd.categories_id
                                                      and l.code = :code
                                                      and l.languages_id = cd.language_id limit 1
                                                     ');

              $Qcategory->bindInt(':products_id', $QorderProducts->valueInt('products_id'));
              $Qcategory->bindValue(':code', DEFAULT_LANGUAGE);

              $Qcategory->execute();

              $footer .= 'piwikTracker.addEcommerceItem("' . (int)$QorderProducts->valueInt('products_id') . '","' . HTML::outputProtected($QorderProducts->value('products_name')) . '","' . HTML::outputProtected($Qcategory->value('categories_name')) . '",' . $this->format_raw($QorderProducts->value('final_price')) . ',' . (int)$QorderProducts->value('products_quantity') . ');' . "\n";
            }

            $footer .= 'piwikTracker.trackEcommerceOrder("' . (int)$last_order . '",' . (isset($totals['TO']) ? $this->format_raw($totals['ot_total']) : 0) . ',' . (isset($totals['ot_subtotal']) ? $this->format_raw($totals['ot_subtotal']) : 0) . ','.(isset($totals['TX']) ? $this->format_raw($totals['TX']) : 0) . ',' . (isset($totals['SH']) ? $this->format_raw($totals['SH']) : 0) . ',false);' . "\n";
          }
        }

       $footer .= 'piwikTracker.trackPageView();
        piwikTracker.enableLinkTracking();
        } catch( err ) {}
        </script><noscript><p><img src="' . HTML::outputProtected(MODULE_HEADER_TAGS_MATOMO_HTTP_URL) . 'piwik.php?idsite=' . (int)MODULE_HEADER_TAGS_MATOMO_ID . '" style="border:0" alt="" /></p></noscript>
        <!-- End Piwik Tracking Code -->' . "\n";
        $CLICSHOPPING_Template->addBlock($footer, 'footer_scripts');
      }
    }

    public function isEnabled()
    {
      return $this->enabled;
    }

    public function check()
    {
      return defined('MODULE_HEADER_TAGS_MATOMO_STATUS');
    }

    public function install()
    {
      $CLICSHOPPING_Db = Registry::get('Db');

      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Do you want to display this module ?',
          'configuration_key' => 'MODULE_HEADER_TAGS_MATOMO_STATUS',
          'configuration_value' => 'True',
          'configuration_description' => 'Do you want to display this module ?',
          'configuration_group_id' => '6',
          'sort_order' => '1',
          'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
          'date_added' => 'now()'
        ]
      );

      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'URL/HTTPS Matomo',
          'configuration_key' => 'MODULE_HEADER_TAGS_MATOMO_HTTPS_URL',
          'configuration_value' => '',
          'configuration_description' => 'The HTTP-URL where Matomo is installed.<br />e.G.: https://www.domain.de/Matomo/',
          'configuration_group_id' => '6',
          'sort_order' => '2',
          'set_function' => '',
          'date_added' => 'now()'
        ]
      );

      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Matomo ID',
          'configuration_key' => 'MODULE_HEADER_TAGS_MATOMO_ID',
          'configuration_value' => '',
          'configuration_description' => 'Profile ID to track',
          'configuration_group_id' => '6',
          'sort_order' => '3',
          'set_function' => '',
          'date_added' => 'now()'
        ]
      );

      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Do you want to enable e-commerce tracking?',
          'configuration_key' => 'MODULE_HEADER_TAGS_MATOMO_EC_TRACKING',
          'configuration_value' => 'True',
          'configuration_description' => 'Do you want to enable e-commerce tracking?',
          'configuration_group_id' => '6',
          'sort_order' => '5',
          'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
          'date_added' => 'now()'
        ]
      );

      $CLICSHOPPING_Db->save('configuration', [
          'configuration_title' => 'Sort Order',
          'configuration_key' => 'MODULE_HEADER_TAGS_MATOMO_SORT_ORDER',
          'configuration_value' => '555',
          'configuration_description' => 'Sort order. Lowest is displayed in first',
          'configuration_group_id' => '6',
          'sort_order' => '10',
          'set_function' => '',
          'date_added' => 'now()'
        ]
      );
    }

    public function remove()
    {
      return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
    }

    public function keys()
    {
      return ['MODULE_HEADER_TAGS_MATOMO_STATUS',
        'MODULE_HEADER_TAGS_MATOMO_HTTPS_URL',
        'MODULE_HEADER_TAGS_MATOMO_ID',
        'MODULE_HEADER_TAGS_MATOMO_EC_TRACKING',
        'MODULE_HEADER_TAGS_MATOMO_SORT_ORDER'
      ];
    }


    /**
     * @param $number
     * @param string $currency_code
     * @param string $currency_value
     * @return string
     */
    private function format_raw($number, $currency_code = '', $currency_value = '')
    {
      $CLICSHOPPING_Currencies = Registry::get('Currencies');

      if (empty($currency_code) || !$this->is_set($currency_code)) {
        $currency_code = $_SESSION['currency'];
      }

      if (empty($currency_value) || !is_numeric($currency_value)) {
        $currency_value = $CLICSHOPPING_Currencies->currencies[$currency_code]['value'];
      }

      return number_format(round($number * $currency_value, $CLICSHOPPING_Currencies->currencies[$currency_code]['decimal_places']), $CLICSHOPPING_Currencies->currencies[$currency_code]['decimal_places'], '.', '');
    }
  }
