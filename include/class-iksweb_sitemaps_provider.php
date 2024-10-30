<?php
class iksweb_sitemaps_provider extends WP_Sitemaps_Provider {

	// make visibility not protected
	public $name;
	
	/**
	 * Constructor. Sets name, object_type properties.
	 *
	 * $name         Provider name. Uses in URL (must be unique).
	 * $object_type  The object name that the provider works with.
	 *               Passes into the hooks (must be unique).
	 */
	public function __construct()
	{
		$this->name        = 'iksweb';
		$this->object_type = 'iksweb';
	}

	
	/**
	 * Gets a URL list for a sitemap.
	 *
	 * @param int    $page_num       Page of results.
	 * @param string $subtype Optional. Object subtype name. Default empty.
	 *
	 * @return array Array of URLs for a sitemap.
	 */
	public function get_url_list( $page_num, $subtype = '' )
	{

		$url_list = array();
	
		$arParams = get_option('SITEMAP_SETTINGS');
		
		if( isset($arParams) && isset($arParams['dop_pages']) ){

			foreach($arParams['dop_pages'] as $item){
				
				if ( filter_var($item, FILTER_VALIDATE_URL) === FALSE )
					continue;
				
				$sitemap_entry = [
					'loc' => esc_url( $item ),
				];
				
				$url_list[] = $sitemap_entry;
			}

		}
		
		return $url_list;
	}
	
	/**
	 * Gets the max number of pages available for the object type.
	 *
	 * @param string $subtype Optional. Object subtype. Default empty.
	 * @return int Total number of pages.
	 */
	public function get_max_num_pages( $subtype = '' ) {

		$total = 1 ;

		return (int) ceil( $total / wp_sitemaps_get_max_urls( $this->object_type ) );
	}
	
}	

?>