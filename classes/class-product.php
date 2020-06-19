<?php
/**
 * Product class.
 *
 * @package megaventory
 * @since 1.0.0
 *
 * Author URI: https://github.com/Megaventory/WooCommerce
 * Developer URI: https://megaventory.com/
 * Developer e-mail: support@megaventory.com
 * Copyright: © 2009-2019 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Imports.
 */
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/api.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'helpers/address.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mv-constants.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-error.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-errors.php';
require_once MEGAVENTORY__PLUGIN_DIR . 'classes/class-mvwc-successes.php';
/**
 * This class works as a model for a product
 * It holds all important attributes of a Megaventory/WooCommerce product
 * WC and Megaventory will store same products at different IDs. Those IDs can be accessed separately
 * SKU is more important than ID and can be used to compare products
 */
class Product {

	/**
	 * WooCommerce product id.
	 *
	 * @var int
	 */
	public $wc_id;

	/**
	 * Megaventory product id.
	 *
	 * @var int
	 */
	public $mv_id;

	/**
	 * Product name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Product sku.
	 *
	 * @var string
	 */
	public $sku;

	/**
	 * Product ean.
	 *
	 * @var string
	 */
	public $ean;

	/**
	 * Product description.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Product long description.
	 *
	 * @var string
	 */
	public $long_description;

	/**
	 * Product quantity.
	 *
	 * @var int
	 */
	public $quantity;

	/**
	 * Product category.
	 *
	 * @var string
	 */
	public $category;

	/**
	 * Product type.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Product image path.
	 *
	 * @var string
	 */
	public $image_url;

	/**
	 * Product regular price.
	 *
	 * @var double
	 */
	public $regular_price;

	/**
	 * Product sales price.
	 *
	 * @var double
	 */
	public $sale_price;

	/**
	 * Product sales status.
	 *
	 * @var string
	 */
	public $sale_active;

	/**
	 * Product length dimension.
	 *
	 * @var double
	 */
	public $length;

	/**
	 * Product breadth dimension.
	 *
	 * @var double
	 */
	public $breadth;

	/**
	 * Product height dimension.
	 *
	 * @var double
	 */
	public $height;

	/**
	 * Product weight dimension.
	 *
	 * @var double
	 */
	public $weight;

	/**
	 * Product version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Product stock.
	 *
	 * @var double
	 */
	public $available_wc_stock;

	/**
	 * Product megaventory quantity.
	 *
	 * @var double
	 */
	public $mv_qty;

	/**
	 * Product Megaventory type.
	 *
	 * @var string
	 */
	public $mv_type;

	/**
	 * Product variations.
	 *
	 * @var array
	 */
	public $variations;

	/**
	 * Product errors.
	 *
	 * @var MVWC-Errors
	 */
	public $errors;

	/**
	 * Product successes.
	 *
	 * @var MVWC_Successes
	 */
	public $successes;

	/*API Calls*/

	/**
	 * Get Product API call.
	 *
	 * @var string
	 */
	private static $product_get_call = 'ProductGet';

	/**
	 * Update Product API call.
	 *
	 * @var string
	 */
	private static $product_update_call = 'ProductUpdate';

	/**
	 * Undelete Product API call.
	 *
	 * @var string
	 */
	private static $product_undelete_call = 'ProductUndelete';

	/**
	 * Get Product stock API call.
	 *
	 * @var string
	 */
	private static $product_stock_call = 'InventoryLocationStockGet';

	/**
	 * Get Inventory Location API call.
	 *
	 * @var string
	 */
	private static $inventory_get_call = 'InventoryLocationGet';

	/**
	 * Get Category API call.
	 *
	 * @var string
	 */
	private static $category_get_call = 'ProductCategoryGet';

	/**
	 * Update Category API call.
	 *
	 * @var string
	 */
	private static $category_update_call = 'ProductCategoryUpdate';

	/**
	 * Delete Category API call.
	 *
	 * @var string
	 */
	private static $category_delete_call = 'ProductCategoryDelete';

	/**
	 * Undelete Category API call.
	 *
	 * @var string
	 */
	private static $category_undelete_call = 'ProductCategoryUndelete';

	/**
	 * Product Constructor.
	 */
	public function __construct() {

		$this->errors    = new MVWC_Errors();
		$this->successes = new MVWC_Successes();

	}

	/**
	 * Get Product Errors.
	 *
	 * @return MVWC_Errors
	 */
	public function errors() {

		return $this->errors;
	}

	/**
	 * Get product Successes.
	 *
	 * @return MVWC_Successes
	 */
	public function successes() {

		return $this->successes;
	}

	/**
	 * Logs Product Errors.
	 *
	 * @param string $problem as problem type.
	 * @param string $full_msg as error's full message.
	 * @param int    $code as error's code.
	 * @param string $type error type default 'error'.
	 * @param string $json_object as string.
	 * @return void
	 */
	public function log_error( $problem, $full_msg, $code, $type = 'error', $json_object ) {

		$args = array(
			'entity_id'   => array(
				'wc' => $this->wc_id,
				'mv' => $this->mv_id,
			),
			'entity_name' => ( empty( $this->sku ) ) ? $this->name : $this->sku,
			'problem'     => $problem,
			'full_msg'    => $full_msg,
			'error_code'  => $code,
			'json_object' => $json_object,
			'type'        => $type,
		);
		$this->errors->log_error( $args );

	}

	/**
	 * Logs Product Successes.
	 *
	 * @param string $transaction_status as success status.
	 * @param string $full_msg as success full message.
	 * @param int    $code as message code.
	 * @return void
	 */
	public function log_success( $transaction_status, $full_msg, $code ) {

		$args = array(
			'entity_id'          => array(
				'wc' => $this->wc_id,
				'mv' => $this->mv_id,
			),
			'entity_type'        => 'product',
			'entity_name'        => ( empty( $this->sku ) ) ? $this->name : $this->sku,
			'transaction_status' => $transaction_status,
			'full_msg'           => $full_msg,
			'success_code'       => $code,
		);

		$this->successes->log_success( $args );
	}

	/**
	 * Gets all Products.
	 *
	 * @return array[Product]
	 */
	public static function wc_all() {

		$args     = array(
			'post_type'   => 'product',
			'numberposts' => -1,
		);
		$products = get_posts( $args );
		$temp     = array();

		foreach ( $products as $product ) {

			array_push( $temp, self::wc_convert( $product ) );
		}

		$products = $temp;

		return $temp;

	}

	/**
	 * Get configurable Products from WooCommerce.
	 *
	 * @return array[Product]
	 */
	public static function wc_all_with_variable() {

		$products = self::wc_all();
		$temp     = array();

		foreach ( $products as $prod ) {

			array_push( $temp, $prod );

			$vars = $prod->wc_get_variations();

			if ( count( $vars ) > 0 ) {

				$temp = array_merge( $temp, $vars );
			}
		}

		return $temp;

	}

	/**
	 * Get all Products from Megaventory.
	 *
	 * @return array[Product]
	 */
	public static function mv_all() {

		$categories = self::mv_get_categories();
		$url        = create_json_url( self::$product_get_call );
		$json_data  = perform_call_to_megaventory( $url );
		$json_prod  = json_decode( $json_data, true );

		/* Map json to Product class. */

		$products = array();

		foreach ( $json_prod['mvProducts'] as $prod ) {

			$product = self::mv_convert( $prod, $categories );

			array_push( $products, $product );
		}

		return $products;
	}

	/**
	 * Get Product from WooCommerce with id.
	 *
	 * @param int $id product id.
	 * @return Product|null
	 */
	public static function wc_find( $id ) {

		$wc_prod = get_post( $id );
		if ( $wc_prod ) {
			$product = self::wc_convert( $wc_prod );
			return $product;// Product after meta fields set.
		} else {

			return null;
		}
	}

	/**
	 * Get Product from Megaventory with id.
	 *
	 * @param int $id as product id.
	 * @return Product|null
	 */
	public static function mv_find( $id ) {

		$url       = create_json_url_filter( self::$product_get_call, 'ProductID', 'Equals', $id );
		$json_data = perform_call_to_megaventory( $url );
		$data      = json_decode( $json_data, true );
		if ( count( $data['mvProducts'] ) <= 0 ) {
			return null; // No such ID.
		}

		return self::mv_convert( $data['mvProducts'][0] );
	}

	/**
	 * Creates a default shipping product in Megaventory.
	 *
	 * @return bool
	 */
	public static function create_default_shipping() {

		$shipping_product              = new Product();
		$shipping_product->name        = 'shipping_product';
		$shipping_product->sku         = 'shipping';
		$shipping_product->mv_type     = 'Service';
		$shipping_product->description = 'shipping method';

		$megaventory_saved_product = $shipping_product->mv_save();

		if ( $megaventory_saved_product['ProductID'] ) {

			return true;
		}

		return false;
	}

	/**
	 * Pull stock from Megaventory.
	 *
	 * @return void
	 */
	public function pull_stock() {

		$url       = create_json_url_filter( self::$product_stock_call, 'productID', 'Equals', $this->mv_id );
		$json_data = perform_call_to_megaventory( $url );
		$response  = json_decode( $json_data, true );

		/* sum product on hand in all inventories */
		$response        = $response['mvProductStockList'];
		$available_stock = 0;
		$mv_qty          = array();

		if ( null !== $response[0]['mvStock'] ) {

			foreach ( $response[0]['mvStock'] as $inventory ) {

				$inventory_name = self::get_inventory_name( $inventory['InventoryLocationID'], true );

				$total           = $inventory['StockPhysical'];
				$on_hand         = $inventory['StockOnHand'];
				$non_shipped     = $inventory['StockNonShipped'];
				$non_allocated   = $inventory['StockNonAllocatedWOs'];
				$non_received_po = $inventory['StockNonReceivedPOs'];
				$non_received_wo = $inventory['StockNonReceivedWOs'];

				$string  = '' . $inventory_name;
				$string .= ';' . $total;
				$string .= ';' . $on_hand;
				$string .= ';' . $non_shipped;
				$string .= ';' . $non_allocated;
				$string .= ';' . $non_received_po;
				$string .= ';' . $non_received_wo;

				array_push( $mv_qty, $string );
				$available_stock += (int) $on_hand;
			}
		} else {

			$this->mv_qty = 'no stock';

			return;
		}

		$this->available_wc_stock = $available_stock;
		$this->mv_qty             = $mv_qty;
	}

	/**
	 * Get Inventory Name from Megaventory.
	 *
	 * @param int     $id as Inventory id.
	 * @param boolean $abbrev boolean to return Inventory's abbreviation or name.
	 * @return null|string
	 */
	public static function get_inventory_name( $id, $abbrev = false ) {

		$url       = create_json_url_filter( self::$inventory_get_call, 'InventoryLocationID', 'Equals', rawurlencode( $id ) );
		$json_data = perform_call_to_megaventory( $url );
		$data      = json_decode( $json_data, true );

		if ( count( $data['mvInventoryLocations'] ) <= 0 ) { // Not found.
			return null;
		}

		if ( $abbrev ) {
			return $data['mvInventoryLocations'][0]['InventoryLocationAbbreviation'];
		} else {
			return $data['mvInventoryLocations'][0]['InventoryLocationName'];
		}
	}

	/**
	 * Get Product from WooCommerce by sku.
	 *
	 * @param string $sku as product's sku.
	 * @return Product|null
	 */
	public static function wc_find_by_sku( $sku ) {

		$prods = self::wc_all_with_variable();
		foreach ( $prods as $prod ) {

			if ( $prod->sku === $sku ) {

				return $prod;
			}
		}

		return null;
	}

	/**
	 * Get Product from Megaventory by sku.
	 *
	 * @param string $sku as product's sku.
	 * @return Product|null
	 */
	public static function mv_find_by_sku( $sku ) {

		$url       = create_json_url_filter( self::$product_get_call, 'ProductSKU', 'Equals', rawurlencode( $sku ) );
		$json_data = perform_call_to_megaventory( $url );
		$data      = json_decode( $json_data, true );
		if ( count( $data['mvProducts'] ) <= 0 ) {
			return null; // No such sku.
		}
		return self::mv_convert( $data['mvProducts'][0] );
	}

	/**
	 * Converts a mvProduct to Product.
	 *
	 * @param array     $mv_prod as Megaventory product.
	 * @param null|bool $categories search with categories.
	 * @return Product
	 */
	private static function mv_convert( $mv_prod, $categories = null ) {
		/*
			Passing categories makes things faster and requires less API calls.
			always use $categories when using this function in a loop with many users
		*/

		if ( null === $categories ) {

			$categories = self::mv_get_categories();
		}

		$product = new Product();

		$product->mv_id            = $mv_prod['ProductID'];
		$product->type             = $mv_prod['ProductType'];
		$product->mv_type          = $mv_prod['ProductType'];
		$product->sku              = $mv_prod['ProductSKU'];
		$product->ean              = ( isset( $mv_prod['ProductEAN'] ) ? $mv_prod['ProductEAN'] : '' );
		$product->description      = $mv_prod['ProductDescription'];
		$product->long_description = $mv_prod['ProductLongDescription'];
		$product->image_url        = $mv_prod['ProductImageURL'];

		$product->regular_price = $mv_prod['ProductSellingPrice'];
		$product->category      = ( isset( $categories[ $mv_prod['ProductCategoryID'] ] ) ? $categories[ $mv_prod['ProductCategoryID'] ] : null );

		$product->weight  = $mv_prod['ProductWeight'];
		$product->length  = $mv_prod['ProductLength'];
		$product->breadth = $mv_prod['ProductBreadth'];
		$product->height  = $mv_prod['ProductHeight'];

		$product->version = $mv_prod['ProductVersion'];

		$product->pull_stock();

		return $product;
	}

	/**
	 * Converts a WooCommerce Product to Product.
	 *
	 * @param WP_Post[]|int[] $wc_prod as WooCommerce product.
	 * @return Product
	 */
	private static function wc_convert( $wc_prod ) {
		/*
		Generate Product data from WC_PRODUCT.
		$woocommerce_product = wc_get_product( $wc_prod->ID );
		*/

		$prod        = new Product();
		$id          = $wc_prod->ID;
		$prod->wc_id = $wc_prod->ID;
		$prod->mv_id = (int) get_post_meta( $id, 'mv_id', true );

		$prod->name             = $wc_prod->post_name;
		$prod->long_description = $wc_prod->post_content;
		$prod->description      = $wc_prod->post_title;

		$terms      = get_the_terms( $id, 'product_type' );
		$prod->type = ( ! empty( $terms ) ) ? sanitize_title( current( $terms )->name ) : 'simple';

		$prod->sku = get_post_meta( $id, '_sku', true );

		/* prices */
		$prod->regular_price = get_post_meta( $id, '_regular_price', true );
		$prod->sale_price    = get_post_meta( $id, '_sale_price', true );
		$sale_from           = get_post_meta( $id, '_sale_price_dates_from', true );
		$sale_to             = get_post_meta( $id, '_sale_price_dates_to', true );

		if ( $prod->sale_price ) {

			if ( ! $sale_from && ! $sale_to ) {

				$prod->sale_active = true;
			} else {

				$sale_from = ( $sale_from ? gmdate( 'd-m-Y', (int) $sale_from ) : null );
				$sale_to   = ( $sale_to ? gmdate( 'd-m-Y', (int) $sale_to ) : null );
				$today     = gmdate( 'Y-m-d' );

				if ( ( null === $sale_from || $sale_from < $today ) && ( null === $sale_to || $sale_to > $today ) ) {

					$prod->sale_active = true;
				}
			}
		}

		$prod->weight  = get_post_meta( $id, '_weight', true );
		$prod->length  = get_post_meta( $id, '_length', true );
		$prod->breadth = get_post_meta( $id, '_width', true );
		$prod->height  = get_post_meta( $id, '_height', true );

		$prod->available_wc_stock = (int) get_post_meta( $id, '_stock', true );
		$prod->mv_qty             = get_post_meta( $id, '_mv_qty', true );
		$cs                       = wp_get_object_terms( $id, 'product_cat' );

		if ( count( $cs ) > 0 ) {

			$prod->category = self::get_full_category_name( $cs[0] ); // Primary category.
		}

		$img = wp_get_attachment_image_src( get_post_thumbnail_id( $id ) );

		if ( $img[0] ) {

			$prod->image_url = $img[0];
		}

		return $prod;
	}

	/**
	 * Generates the category's full name. From root category to corresponding.
	 *
	 * @param WP_Term $wp_term as category data.
	 * @return string
	 */
	public static function get_full_category_name( $wp_term ) {

		$full_category_name = $wp_term->name;

		$parent_category = $wp_term;

		while ( ! empty( $parent_category->parent ) ) {

			$parent_category = get_term( $parent_category->parent );

			$full_category_name = $parent_category->name . MV_Constants::MV_SLASH . $full_category_name;
		}

		return $full_category_name;
	}

	/**
	 * Get WooCommerce variations.
	 *
	 * @return array
	 */
	public function wc_get_variations() {

		$handle = new WC_Product_Variable( $this->wc_id );

		$variations_ids = $handle->get_children();

		$prods = array();

		foreach ( $variations_ids as $variation_id ) {

			$new_variation_product = new Product();
			$new_variation_product = self::wc_variable_convert( $variation_id, $this );

			array_push( $prods, $new_variation_product );
		}

		return $prods;
	}

	/**
	 * Inherit parent values if no values are present.
	 *
	 * @param int     $var_prod_id as product id.
	 * @param Product $parent as parent Product.
	 * @return Product
	 */
	private static function wc_variable_convert( $var_prod_id, $parent ) {

		$var_prod = new WC_Product_Variation( $var_prod_id );
		$prod     = new Product();

		$prod->wc_id         = $var_prod_id;
		$prod->mv_id         = get_post_meta( $var_prod_id, 'mv_id', true );
		$prod->sku           = $var_prod->get_sku();
		$prod->description   = $var_prod->get_name() ? $var_prod->get_name() : $parent->description;
		$prod->type          = 'variable-child';
		$prod->regular_price = $var_prod->get_regular_price() ? $var_prod->get_regular_price() : $parent->regular_price;
		$prod->sale_price    = $var_prod->get_sale_price() ? $var_prod->get_sale_price() : $parent->sale_price;

		$prod->weight  = $var_prod->get_weight() ? $var_prod->get_weight() : $parent->weight;
		$prod->height  = $var_prod->get_height() ? $var_prod->get_height() : $parent->height;
		$prod->length  = $var_prod->get_length() ? $var_prod->get_length() : $parent->length;
		$prod->breadth = $var_prod->get_width() ? $var_prod->get_width() : $parent->breadth;

		$prod->category = $parent->category;

		// Version is | name - var1, var2, var3.
		// Megaventory version should be | var1, var2, var3.
		$version = $var_prod->get_name();
		$version = str_replace( ' ', '', $version ); // remove whitespaces.
		$version = explode( '-', $version )[1]; // disregard name.
		$version = str_replace( ',', '/', $version );

		$prod->version = $version;

		return $prod;
	}

	/**
	 * Update each option of a variable product in Megaventory.
	 *
	 * @param WC_Product_Variation $wc_product as variable product.
	 * @param Product              $product as Product.
	 * @return void
	 */
	public function update_variable_product_in_megaventory( $wc_product, $product ) {

		$variation_ids = $wc_product->get_children();

		foreach ( $variation_ids as $variation_id ) {

			$variation_product = self::wc_variable_convert( $variation_id, $product );

			$variation_product->mv_save();

		}
	}

	/**
	 * Get WooCommerce categories.
	 *
	 * @param null|string $by filter categories.
	 * @return array
	 */
	public function wc_get_prod_categories( $by = null ) {

		$cats = wp_get_object_terms( $this->wc_id, 'product_cat' );

		if ( null === $by ) {

			return $cats;

		} elseif ( 'id' === strtolower( $by ) ) {

			$temp = array();

			foreach ( $cats as $cat ) {

				array_push( $temp, $cat->term_id );
			}

			return $temp;

		} elseif ( 'name' === strtolower( $by ) ) {

			$temp = array();

			foreach ( $cats as $cat ) {

				array_push( $temp, $cat->name );
			}

			return $temp;
		}
	}

	/**
	 * Save Products to WooCommerce.
	 *
	 * @param array[Product] $wc_products as array of products.
	 * @param boolean        $create_upon_save as boolean.
	 * @return bool
	 */
	public function wc_save( $wc_products = null, $create_upon_save = true ) {

		if ( null === $wc_products ) {

			$wc_products = ( null === $this->version ) ? self::wc_all() : self::wc_all_with_variable();
		}

		/*
			Find if SKU exists, if so, update instead of insert
			only insert if $create upon save
		*/

		if ( null === $this->wc_id ) {

			foreach ( $wc_products as $wc_product ) {

				if ( $this->sku === $wc_product->sku ) {

					$this->wc_id = $wc_product->wc_id;
					break;
				}
			}
		}

		if ( null === $this->wc_id && ! $create_upon_save ) {

			return false;
		}

		/* Prevent null on empty */

		if ( null === $this->long_description ) {

			$this->long_description = '';
		}

		if ( '' === wp_strip_all_tags( $this->description ) || null === $this->description ) {

			$this->log_error( 'Product not saved to WooCommerce', 'Short description cannot be empty', -1, 'error', '' );
			return false;
		}

		if ( null === $this->sku ) {

			$this->log_error( 'Product not saved to WooCommerce', 'SKU cannot be empty', -1, 'error', '' );
			return false;
		}

		/* Don't update variables title! */

		if ( null === $this->wc_id ) {

			/*Create product.*/

			$args = array(
				'post_content' => $this->long_description,
				'post_excerpt' => $this->description,
				'post_status'  => 'publish',
				'post_type'    => 'product',
			);

			if ( null === $this->version ) {

				$args['post_title'] = $this->description;
			}

			$post_id = wp_insert_post( $args );

			if ( is_wp_error( $post_id ) ) {

				$this->log_error( 'Product not saved to WooCommerce', $post_id->get_error_message(), $post_id->get_error_code(), 'error', '' );

				return false;
			}

			$this->wc_id = $post_id;

		} else {

			/* Never update product title. only on create.*/

			$post = array(
				'ID'           => $this->wc_id,
				// Don't use it 'post_title' => $this->description, !
				'post_content' => $this->long_description,
				'post_excerpt' => $this->description,
			);

			$return = wp_update_post( $post );

			if ( is_wp_error( $return ) ) {

				$this->log_error( 'Product not saved to WooCommerce', $return->get_error_message(), $return->get_error_code(), 'error', '' );
				return false;
			}
		}

		/*
			Set category to mv category only if product has no categories.
			Otherwise, don't do anything.
			Uncategorized is default.
		*/

		if ( null !== $this->category && count( wp_get_object_terms( $this->wc_id, 'product_cat' ) ) <= 1 ) {

			$category_id = $this->wc_get_category_id_by_name( $this->category, true );

			if ( $category_id ) {

				wp_set_object_terms( $this->wc_id, $category_id, 'product_cat' );
			}
		}

		wp_set_object_terms( $this->wc_id, 'simple', 'product_type' );
		// set other information.
		update_post_meta( $this->wc_id, '_visibility', 'visible' );
		update_post_meta( $this->wc_id, '_regular_price', $this->regular_price );

		if ( $this->sale_price ) {

			update_post_meta( $this->wc_id, '_sale_price', $this->sale_price );
		}

		update_post_meta( $this->wc_id, '_weight', $this->weight );
		update_post_meta( $this->wc_id, '_length', $this->length );
		update_post_meta( $this->wc_id, '_width', $this->breadth );
		update_post_meta( $this->wc_id, '_height', $this->height );
		update_post_meta( $this->wc_id, '_sku', $this->sku );
		update_post_meta( $this->wc_id, '_manage_stock', 'yes' );
		update_post_meta( $this->wc_id, '_stock', (string) $this->available_wc_stock );
		update_post_meta( $this->wc_id, '_stock_status', ( $this->available_wc_stock > 0 ? 'instock' : 'outofstock' ) );

		if ( null !== $this->version ) {

			update_post_meta( $this->wc_id, '_variation_description', $this->description );
		}

		$this->attach_image();

		update_post_meta( $this->wc_id, 'mv_id', $this->mv_id );

		return true;
	}

	/**
	 * Save Product to Megaventory.
	 *
	 * @param null|boolean $categories search with categories.
	 * @return array
	 */
	public function mv_save( $categories = null ) {
		/*
			Passing categories makes things faster and requires less API calls.
			always use $categories when using this function in a loop with many users
		*/

		if ( 'grouped' === $this->type ) {

			return null;
		}
		if ( 'variable' === $this->type ) {

			return null;
		}
		if ( ! wp_strip_all_tags( $this->description ) ) {

			$this->log_error( 'Product not saved to Megaventory', 'Short description cannot be empty', -1, 'error', '' );
			return false;
		}
		if ( ! $this->sku ) {

			$this->log_error( 'Product not saved to Megaventory', 'SKU cannot be empty', -1, 'error', '' );
			return false;
		}

		if ( null === $categories ) {

			$categories = self::mv_get_categories();
		}

		$category_id = null;

		if ( null !== $this->category ) {

			$category_id = array_search( $this->category, $categories, true );

			if ( false === $category_id ) { // we need to create a new category.

				$category_id = self::mv_create_category( $this->category );
			}
		}

		$urljson      = create_json_url( self::$product_update_call );
		$json_request = $this->generate_update_json( $category_id );
		$data         = send_json( $urljson, $json_request );

		/*if product didn't save in Megaventory it will return an error code !=0*/

		if ( '0' !== ( $data['ResponseStatus']['ErrorCode'] ) ) {

			/* the only case we will not log an error is the case that the product is already deleted in Megaventory */

			if ( 'ProductSKUAlreadyDeleted' !== $data['InternalErrorCode'] ) {

				$internal_error_code = ' [' . $data['InternalErrorCode'] . ']';

				$this->log_error( 'Product not saved to Megaventory' . $internal_error_code, $data['ResponseStatus']['Message'], -1, 'error', $data['json_object'] );

				return false;
			}

			$this->mv_id   = $data['entityID'];
			$url           = create_json_url( self::$product_undelete_call );
			$url           = $url . '&ProductIDToUndelete=' . $this->mv_id;
			$undelete_data = perform_call_to_megaventory( $url );

			if ( array_key_exists( 'InternalErrorCode', $undelete_data ) ) {
				$this->log_error( 'Product not saved to Megaventory', 'Product is deleted. Undelete failed', -1, 'error', $data['json_object'] );
				return false;
			}

			$json_request = $this->generate_update_json( $category_id );
			$data         = send_json( $url, $json_request );
		}

		/*Otherwise the product will either be created or updated.*/

		$product_exists = ( null === $this->mv_id || 0 === $this->mv_id );
		$action         = ( $product_exists ? 'created' : 'updated' );

		$this->log_success( $action, 'product successfully ' . $action . ' in Megaventory', 1 );

		update_post_meta( $this->wc_id, 'mv_id', $data['mvProduct']['ProductID'] );

		$this->mv_id = $data['mvProduct']['ProductID'];

		return $data['mvProduct'];
	}

	/**
	 * Create a json.
	 *
	 * @param null|bool $category_id filter with categories.
	 * @return string
	 */
	private function generate_update_json( $category_id = null ) {

		$megaventory_product = new Product();
		$megaventory_product = self::mv_find_by_sku( $this->sku );

		if ( null !== $megaventory_product ) {

			$this->mv_id   = $megaventory_product->mv_id;
			$this->mv_type = $megaventory_product->mv_type;
		} else {
			$this->mv_id = 0;
		}

		$create_new = ( null === $this->mv_id || 0 === $this->mv_id );

		$action = ( ( $create_new ) ? 'Insert' : 'Update' );

		$special_characters = array( '?', '$', '@', '!', '*', '#' );// Special characters that need to be removed in order to be accepted by Megaventory.

		$product_update_object = new \stdClass();
		$product_object        = new \stdClass();

		$product_object->productid              = $create_new ? '' : $this->mv_id;
		$product_object->producttype            = $this->mv_type ? $this->mv_type : 'BuyFromSupplier';
		$product_object->productsku             = $this->sku;
		$product_object->productdescription     = mb_substr( wp_strip_all_tags( str_replace( $special_characters, ' ', $this->description ) ), 0, 400 );
		$product_object->productversion         = $this->version ? $this->version : '';
		$product_object->productlongdescription = $this->long_description ? mb_substr( wp_strip_all_tags( str_replace( $special_characters, ' ', $this->long_description ) ), 0, 400 ) : '';
		$product_object->productcategoryid      = $category_id;
		$product_object->productsellingprice    = $this->regular_price ? $this->regular_price : '';
		$product_object->productweight          = $this->weight ? $this->weight : '';
		$product_object->productlength          = $this->length ? $this->length : '';
		$product_object->productbreadth         = $this->breadth ? $this->breadth : '';
		$product_object->productheight          = $this->height ? $this->height : '';
		$product_object->productimageurl        = $this->image_url ? $this->image_url : '';

		$product_update_object->mvproduct                             = $product_object;
		$product_update_object->mvrecordaction                        = $action;
		$product_update_object->mvinsertupdatedeletesourceapplication = 'woocommerce';

		$json_object = wrap_json( $product_update_object );

		/**
		 * $json_object = wp_json_encode( $product_update_object );
		 */

		return $json_object;

	}

	/**
	 * Delete product from WooCommerce.
	 *
	 * @return void
	 */
	public function wc_destroy() {

		if ( null === $this->wc_id ) {

			$all = ( null === $this->version ) ? self::wc_all() : self::wc_all_with_variable();

			foreach ( $all as $prod ) {

				if ( $prod->sku === $this->sku ) {

					$this->wc_id = $prod->sku;
					break;
				}
			}
		}

		wp_delete_post( $this->wc_id );
	}

	/**
	 * Future work.
	 *
	 * @return void
	 */
	public function mv_destroy() {

	}

	/**
	 * Image is attached only if a product has no image yet.
	 *
	 * @return bool
	 */
	public function attach_image() {

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( null === $this->image_url || '' === $this->image_url ) {

			return false;

		} else { // only upload image id doesn't exist.

			if ( false !== wp_get_attachment_image_src( get_post_thumbnail_id( $this->wc_id ) ) ) {

				return false;
			}
		}

		$dir          = dirname( __FILE__ );
		$image_folder = $dir . '/../import/';
		$image_file   = $this->sku;
		$image_full   = $image_folder . $image_file;

		// image.
		$image = $this->image_url;

		// Magic sideload image returns an HTML image, not an IDs.
		$media = media_sideload_image( $image, $this->wc_id );

		// therefore we must find it so we can set it as featured ID.
		if ( ! empty( $media ) && ! is_wp_error( $media ) ) {

			$args = array(
				'post_type'      => 'attachment',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'post_parent'    => $this->wc_id,
			);

			// reference new image to set as featured.
			$attachments = get_posts( $args );

			if ( isset( $attachments ) && is_array( $attachments ) ) {

				foreach ( $attachments as $attachment ) {

					// grab source of full size images (so no 300x150 nonsense in path).
					$image = wp_get_attachment_image_src( $attachment->ID, 'full' );

					// determine if in the $media image we created, the string of the URL exists.
					if ( strpos( $media, $image[0] ) !== false ) {

						// if so, we found our image. set it as thumbnail.
						set_post_thumbnail( $this->wc_id, $attachment->ID );
						// only want one image.
						break;
					}
				}
			}
		} else {

			return false;
		}

		return true;
	}

	/**
	 * Get WooCommerce categories.
	 *
	 * @return array
	 */
	private static function wc_get_categories() {

		return get_terms( 'product_cat' );
	}

	/**
	 * Get Category from WooCommerce by name.
	 *
	 * @param string  $name as category name.
	 * @param boolean $with_create as boolean.
	 * @return null|string
	 */
	private function wc_get_category_id_by_name( $name, $with_create = false ) {

		$product_categories = self::wc_get_categories();

		$category_id = array();

		foreach ( $product_categories as $item ) {

			if ( $item->name === $this->category ) {

				array_push( $category_id, $item->term_id );
				return $category_id;
			}
		}

		$category_id = array();
		if ( $with_create ) {
			$cid = wp_insert_term(
				$name, // the term.
				'product_cat', // the taxonomy.
				array()
			);
			return array( $cid['term_id'] );
		}

		return null;
	}

	/**
	 * Get Categories from Megaventory.
	 *
	 * @return array
	 */
	public static function mv_get_categories() {
		$url       = create_json_url( self::$category_get_call );
		$json_data = perform_call_to_megaventory( $url );
		$jsoncat   = json_decode( $json_data, true );

		$categories = array();
		foreach ( $jsoncat['mvProductCategories'] as $cat ) {
			$categories[ $cat['ProductCategoryID'] ] = $cat['ProductCategoryName'];
		}

		return $categories;
	}

	/**
	 * Create a new Category on Megaventory.
	 *
	 * @param string $name as category's name.
	 * @return bool|null
	 */
	public static function mv_create_category( $name ) {

		$create_url = create_json_url( self::$category_update_call );
		$url        = $create_url . '&mvProductCategory={ProductCategoryName:' . rawurlencode( $name ) . '}';
		$json_data  = perform_call_to_megaventory( $url );
		$response   = json_decode( $json_data, true );

		if ( 'CategoryWasDeleted' === $response['InternalErrorCode'] ) { // needs to be undeleted.

			$id           = $response['entityID'];
			$undelete_url = create_json_url( self::$category_undelete_call );
			$url          = $undelete_url . '&ProductCategoryIDToUndelete=' . rawurlencode( $id );
			$json_data    = perform_call_to_megaventory( $url );
			$response     = json_decode( $json_data, true );

			if ( $response['result'] ) {

				$response['mvProductCategory']                      = array();
				$response['mvProductCategory']['ProductCategoryID'] = $id;
			}
		}

		return ( array_key_exists( 'mvProductCategory', $response ) ) ? $response['mvProductCategory']['ProductCategoryID'] : null;
	}

	/**
	 * Sync product meta data on initial sync operation only.
	 *
	 * @return void
	 */
	public function sync_post_meta_with_id() {

		update_post_meta( $this->wc_id, 'mv_id', $this->mv_id );
		update_post_meta( $this->wc_id, '_mv_qty', $this->mv_qty );

		if ( 0 === (int) get_post_meta( $this->wc_id, '_stock', true ) ) {

			update_post_meta( $this->wc_id, '_manage_stock', 'yes' );
			update_post_meta( $this->wc_id, '_stock', (string) $this->available_wc_stock );
			update_post_meta( $this->wc_id, '_stock_status', ( $this->available_wc_stock > 0 ? 'instock' : 'outofstock' ) );
		}

	}

	/**
	 * Synchronize stock from Megaventory to WooCommerce.
	 *
	 * @return void
	 */
	public function sync_stock() {

		if ( null === $this->mv_id ) {
			return; // this should not happen.
		}

		foreach ( self::wc_all() as $wc_product ) {

			if ( $this->sku === $wc_product->sku ) {

				$this->wc_id = $wc_product->wc_id;
				break;
			}
		}

		$this->pull_stock();
		update_post_meta( $this->wc_id, '_mv_qty', $this->mv_qty );
		update_post_meta( $this->wc_id, '_manage_stock', 'yes' );
		update_post_meta( $this->wc_id, '_stock', (string) $this->available_wc_stock );
		update_post_meta( $this->wc_id, '_stock_status', ( $this->available_wc_stock > 0 ? 'instock' : 'outofstock' ) );
	}

	/**
	 * Syncronize stock for a variation of a product.
	 *
	 * @param WC_Product_Variation $wc_product as WC variation.
	 * @param array                $mv_product_stock_details as MV stock data.
	 * @return void
	 */
	public static function sync_variation_stock( $wc_product, $mv_product_stock_details ) {

		$product        = new Product();
		$product->wc_id = $wc_product->get_id();
		$product->mv_id = $mv_product_stock_details['productID'];
		$product->sku   = $wc_product->get_sku();

		$product->pull_stock();

		update_post_meta( $product->wc_id, '_manage_stock', 'yes' );
		update_post_meta( $product->wc_id, '_stock', (string) $product->available_wc_stock );
		update_post_meta( $product->wc_id, '_stock_status', ( $product->available_wc_stock > 0 ? 'instock' : 'outofstock' ) );

	}

	/**
	 * Deletes Megaventory data.
	 *
	 * @return void
	 */
	public function wc_reset_mv_data() {

		delete_post_meta( $this->wc_id, 'mv_id' );
		delete_post_meta( $this->wc_id, '_mv_qty' );
	}
}

