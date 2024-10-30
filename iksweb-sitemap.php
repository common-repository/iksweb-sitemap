<?php
/*
 * Plugin Name: XML Sitemap генератор
 * Plugin URI: https://plugin.iksweb.ru/wordpress/sitemap/
 * Description: Плагин поможет быстро сгенерировать sitemap.xml сайта на WordPress. Вы можете отключать страницы из генерации и применять настройки по индексации для поисковых систем. Рекомендуемая верся PHP 7.1-8.0 / Версия WP 5.5 и выше
 * Author: IKSWEB
 * Author URI: https://plugin.iksweb.ru/wordpress/sitemap/
 * Copyright: IKSWEB
 * Version: 2.7
 * Tags: seo,sitemap,xml,sitemaps,cron,autositemap,xmsitemap,iksweb
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Invalid request.' );
}

if ( ! class_exists( 'iksweb_sitemaps' ) ) {
	
	class iksweb_sitemaps{
		
		/** @var string The plugin version number */
		var $version = '2.7';
		
		/** @var array The plugin settings array */
		var $arParams = array();
		
		function __construct(){}
		
		/*
		* Запуск компонента
		*/
		function init()
		{
			
			global $IKSWEB;
			
			// Объеденяем плагины IKSWEB
			$IKSWEB[] = array(
				'PLUGIN'=>'iksweb-sitemap',
				);
				
			$SETTINGS_NAME = 'SITEMAP_SETTINGS';
			$arSettings = get_option($SETTINGS_NAME);

			// Получаем параметры
			$arParams = $this->arParams = array(
				// basic plugin
				'PLUGIN'=>array(
					// base
					'VERSIA'		=> $this->version,
					'NAME'			=> 'Sitemap.xml',
					'TITLE'			=> 'Настройка Sitemap | IKSWEB',

					//settings
					'SLUG'			=> 'iks-sitemap',
					'SETTINGS_NAME' => $SETTINGS_NAME,
					
					// urls
					'HOST'			=> $_SERVER['HTTP_HOST'],
					'URL'				=> plugin_dir_url( __FILE__ ),
					'PATH'			=> plugin_dir_path( __DIR__ ),
					'FILE'			=> __FILE__,
					),
					
				'params'=>			$arSettings,
			);	

			// Регистрируем меню и настройки
			add_action( 'admin_menu' , array( $this , 'register_menu_plugins' ) ); 
	    add_action( 'admin_init' , array( $this , 'register_params_plugins' ) );
	        
	    //Подключаем файлы к админке
			add_action( 'admin_enqueue_scripts' , array( $this , 'set_plugin_scripts' ) );
			
			// выполняем действия при активации
			register_activation_hook( __FILE__, array( $this , 'set_default_params' ) );
			
			// Если плагин активирован
			if( isset($this->arParams['params']['active']) && $this->arParams['params']['active']=='Y'){
				
				// Меняем параметры карты сайта
				add_filter( 'wp_sitemaps_posts_entry',  array( $this , 'set_params_url_sitemaps_post' ), 10, 2 );
				add_filter( 'wp_sitemaps_taxonomies_entry',  array( $this , 'set_params_url_sitemaps_taxonomies' ), 10, 4);
				add_filter( 'wp_sitemaps_users_entry',  array( $this , 'set_params_url_sitemaps_users' ), 10, 2 );
				add_filter( 'wp_sitemaps_index_entry', array( $this , 'set_params_url_sitemaps_home' ), 10, 4 );

				// Меняем URL карты сайта
				add_action( 'init', array( $this , 'set_redirect_sitemap_url' ));
				add_filter( 'home_url', array( $this , 'set_sitemap_url' ) , 11, 2 );
				
				// Меняем число ссылок в карте 
				add_filter( 'wp_sitemaps_max_urls', array( $this , 'set_limit_url' ) , 10, 2 );
				
				// Фильтрация типов записей
				add_filter( 'wp_sitemaps_taxonomies', array( $this , 'filter_post_type_sitemaps' ) , 10, 2 ); // taxonomies
				add_filter( 'wp_sitemaps_post_types', array( $this , 'filter_post_type_sitemaps' ) , 10, 2 ); // post
				
				// Отключение пользователей
				if( isset($this->arParams['params']['post_type']['users']['active']) && $this->arParams['params']['post_type']['users']['active']!='Y')
					add_filter( 'wp_sitemaps_add_provider', array( $this , 'filter_post_type_users_sitemaps'), 20, 2 ); 
				
				// Добавление доп ссылок
				if( isset($this->arParams['params']['dop_pages']) && !empty($this->arParams['params']['dop_pages']) )
					add_filter( 'init', array( $this , 'register_sitemap_provider' ) );
				
				// Отключение определённые страницы и посты
				if( ( isset($this->arParams['params']['filter_pages']) && is_array($this->arParams['params']['filter_pages']) && count($this->arParams['params']['filter_pages'])>0 ) || ( isset($this->arParams['params']['filter_terms']) && is_array($this->arParams['params']['filter_terms']) && count($this->arParams['params']['filter_terms'])>0 ) )
					add_filter( 'wp_sitemaps_posts_query_args', array( $this , 'filters_pages_and_post_sitemap' ) , 10, 2 );

				// Отключаем определённые taxonomies
				if( isset($this->arParams['params']['filter_terms']) && is_array($this->arParams['params']['filter_terms']) && count($this->arParams['params']['filter_terms'])>0 )	
					add_filter( 'wp_sitemaps_taxonomies_query_args', array( $this , 'filters_taxonomies_sitemap' ) , 10, 2 );
				
				// Отключаем определённых пользователей
				if( isset($this->arParams['params']['post_type']['author']['filter']) && is_array($this->arParams['params']['post_type']['author']['filter'])  && count($this->arParams['params']['post_type']['author']['filter'])>0 )
					add_filter( 'wp_sitemaps_users_query_args', array( $this , 'filters_users_sitemap' ) );
			
			}

		}

		/*
		* Удаляем посты и страницыы из карты сайта
		*/
		function filters_pages_and_post_sitemap($args, $post_type)
	  {
			
			// учтем что этот параметр может быть уже установлен
			if( !isset( $args['post__not_in'] ) )
				$args['post__not_in'] = array();
	

			// Исключаем посты и страницы
			if( isset($this->arParams['params']['filter_pages']) )
				foreach( $this->arParams['params']['filter_pages'] as $post_id )
					$args['post__not_in'][] = $post_id;
			
			// Исключаем посты отключённых категорий
			if( isset($this->arParams['params']['filter_terms']) )
				foreach( $this->arParams['params']['filter_terms'] as $term_id )
					$args['cat'][] = '-'.$term_id;
					
			return $args;
		}
		
		/*
		* Удаляем taxonomies из карты сайта
		*/
		function filters_taxonomies_sitemap($args, $taxonomy)
	  {
			
			// учтем что этот параметр может быть уже установлен
			if( !isset( $args['exclude'] ) )
				$args['exclude'] = array();

			// Исключаем термины
			$args['exclude'] = array_merge( $args['exclude'], $this->arParams['params']['filter_terms'] );

			return $args;
		}
		
		/*
		* Удаляем пользователей из карты сайта
		*/
		function filters_users_sitemap($args)
	  {
			
			// учтем что этот параметр может быть уже установлен
			if( !isset( $args['exclude'] ) )
				$args['exclude'] = array();

			// Исключаем юзеров
			$args['exclude'] = array_merge( $args['exclude'], $this->arParams['params']['post_type']['author']['filter'] );

			return $args;
		}
	
		/*
		* Регистрируем провайдер для произвольных ссылок
		*/
		function register_sitemap_provider()
	  {
			require_once __DIR__ .'/include/class-iksweb_sitemaps_provider.php';
			$provider = new iksweb_sitemaps_provider();
			wp_register_sitemap_provider( $provider->name, $provider );
		}
						
		/*
		* Установим дефолтные настройки
		*/
		function set_default_params()
	  {
			$SETTINGS_NAME = $this->arParams['PLUGIN']['SETTINGS_NAME'];
	    	
			delete_option( $SETTINGS_NAME );
			$arSettings = array( 
				'active'				=>'Y',
				'sitemap_name'  => 'wp-sitemap',
				'post_type'     => array(
					'home_pages'	=> array( 'label' => 'Главная страница', 'active'=> 'Y'),
					'page'				=> array( 'label' => 'Страницы', 'active'=> 'Y'),
					'post'				=> array( 'label' => 'Записи' , 'active'=> 'Y'),
					'category'		=> array( 'label' => 'Рубрики' , 'active'=> 'Y'),
					'post_tag'		=> array( 'label' => 'Метки'),
					'users'				=> array( 'label' => 'Авторы' ),
					),
				'limit'					=> '2000',
				'dop_pages'			=> '',
				'filter_pages'	=> '',
				//'SITEMAP'		=> array(),
			);
			add_option( $SETTINGS_NAME , $arSettings );
	  }
		
		/*
		* Устанавливаем параметры приоритетов и обновлений для URL [Главная страница карты]
		*/
		function set_params_url_sitemaps_home( $entry, $object_type, $object_subtype, $page )
		{
			
			$entry[ 'lastmod' ] = date('c');

			return $entry;
		}
		
		/*
		* Устанавливаем параметры приоритетов и обновлений для URL [Посты/Страницы]
		*/
		function set_params_url_sitemaps_post( $entry, $post )
		{
			
			if( get_option( 'page_on_front' )==$post->ID)
				$post->post_type = 'home_pages';
			
			$entry[ 'lastmod' ] = get_the_modified_date( 'c', $post );
			
			$priority = $this->arParams['params']['post_type'][$post->post_type]['priority'];
			$entry[ 'priority' ] = !empty($priority)? $this->get_priority($priority) : false;
			
			$frequency = $this->arParams['params']['post_type'][$post->post_type]['frequency'];
			$entry[ 'changefreq' ] = !empty($frequency)? $this->get_frequency($frequency) : false;

			if($entry[ 'priority' ]==false)
				unset($entry['priority']);
			
			if($entry[ 'changefreq' ]==false)
				unset($entry['changefreq']);
			
			return $entry;
		}
		
		/*
		* Устанавливаем параметры приоритетов и обновлений для URL [Таксаномии]
		*/
		function set_params_url_sitemaps_taxonomies(  $entry, $term_id , $taxonomy)
		{
			
			$priority = $this->arParams['params']['post_type'][$taxonomy]['priority'];
			$entry[ 'priority' ] = !empty($priority)? $this->get_priority($priority) : false;
			
			$frequency = $this->arParams['params']['post_type'][$taxonomy]['frequency'];
			$entry[ 'changefreq' ] = !empty($frequency)? $this->get_frequency($frequency) : false;

			if($entry[ 'priority' ]==false)
				unset($entry['priority']);
			
			if($entry[ 'changefreq' ]==false)
				unset($entry['changefreq']);
			
			return $entry;
		}
		
		/*
		* Устанавливаем параметры приоритетов и обновлений для URL [Пользователи]
		*/
		function set_params_url_sitemaps_users(  $entry, $user)
		{
			
			$priority = $this->arParams['params']['post_type']['users']['priority'];
			$entry[ 'priority' ] = !empty($priority)? $this->get_priority($priority) : false;
			
			$frequency = $this->arParams['params']['post_type']['users']['frequency'];
			$entry[ 'changefreq' ] = !empty($frequency)? $this->get_frequency($frequency) : false;
			
			if($entry[ 'priority' ]==false)
				unset($entry['priority']);
			
			if($entry[ 'changefreq' ]==false)
				unset($entry['changefreq']);
			
			return $entry;
		}
		
		/*
		* Меняем URL карты чайта
		*/	
		function set_redirect_sitemap_url()
		{
			add_rewrite_rule( '^'.$this->arParams['params']['sitemap_name'].'\.xml$', 'index.php?sitemap=index', 'top' );
		}
	    
		/*
		* Настраиваем редирект на новый URL карты сайта
		*/	
	  function set_sitemap_url( $url, $path )
	  {
	
			if ( '/wp-sitemap.xml' === $path ) {
				return str_replace( '/wp-sitemap.xml', '/'.$this->arParams['params']['sitemap_name'].'.xml', $url );
			}
		
			return $url;
		}
		
		/*
		* Установим лимит числа ссылок в карте сайта
		*/
		function set_limit_url($num, $object_type)
		{
			if( intval($this->arParams['params']['limit']) > 0 )
				return intval($this->arParams['params']['limit']);
			
			return $num;
		}
		
		/*
		* Отключаем типы записей из карты сайта
		*/
		function filter_post_type_sitemaps( $post_types )
		{
			$arRemove = [];
			
			if( isset($this->arParams['params']['post_type']) )
				foreach( $this->arParams['params']['post_type'] as $key=>$item)
					if( $item['active']!='Y' ) 
						unset( $post_types[$key] );

			return $post_types;
		}
		
		/*
		* Отключаем провайдер пользователей
		*/
		function filter_post_type_users_sitemaps( $provider, $name )
		{
			
			if ( in_array( $name, ['users'] ) )
				return false;
				
			return $provider;
		}

		/*
		* Регистрируем меню
		*/
	  function register_menu_plugins()
	  {
			global $APPLICATION, $IKSUPDATE;
	    	
			// Получаем параметры
			$arParams = $this->arParams;
			
			// Если на сайте установлен главный модуль IKSWEB, то делаем подменю
			if(isset($APPLICATION)){
				
				add_submenu_page($APPLICATION->settings['PLUGIN']['SLUG'], $arParams['PLUGIN']['TITLE'], $arParams['PLUGIN']['NAME'], 'manage_options', $arParams['PLUGIN']['SLUG'], array( $this,'the_page_plugin') ); 
			
			}else{
				
				add_menu_page( $arParams['PLUGIN']['TITLE'], $arParams['PLUGIN']['NAME'], 'manage_options', $arParams['PLUGIN']['SLUG'] , array( $this, 'the_page_plugin' ), '' , 60 );
				
				if(!$IKSUPDATE){
					add_submenu_page( $arParams['PLUGIN']['SLUG'] , 'PRO версия', 'PRO версия', 'manage_options', 'iks-pro-sitemap', array( $this , 'the_page_pro_plugin' ));
				}
			}
	    
		}
		
		/*
		* Регистрируем настройки
		*/
		function register_params_plugins()
		{
			// Получаем параметры
			$arParams = $this->arParams;

			/* настраиваемые пользователем значения */
	        add_option( $arParams['PLUGIN']['SETTINGS_NAME'] , '');

			/* настраиваемая пользователем проверка значений общедоступных статических функций */
			register_setting($arParams['PLUGIN']['SETTINGS_NAME'], $arParams['PLUGIN']['SETTINGS_NAME'] , array( $this , 'check_params_plugins' ));
		}
		
		/*
		* Проверка правильности вводимых полей
		*/
		function check_params_plugins($arParams)
		{
			
			$arParams = wp_unslash($arParams);
			
			if( isset($arParams) && is_array($arParams) ){
				
				$arParamsCheck = [];

				$arParamsCheck['active'] = ($arParams['active']=='Y')? 'Y' : 'N';
				$arParamsCheck['limit'] = ( empty($arParams['limit']) )? 1000 : intval( $arParams['limit'] );
				
				$arParamsCheck['sitemap_name'] = ( empty($arParams['sitemap_name']) )? 'wp-sitemap' : sanitize_text_field( $arParams['sitemap_name'] );
				$arParamsCheck['dop_pages'] = ( empty($arParams['dop_pages']) )? false : htmlspecialchars( sanitize_textarea_field( $arParams['dop_pages'] ) );
				
				if( $arParamsCheck['dop_pages'] != false){
					$dop_pages = array_filter(array_map('trim', explode("\n", $arParamsCheck['dop_pages'] )), 'strlen');
					$dop_pages = array_unique( $dop_pages );
					
					$arParamsCheck['dop_pages'] = [];
					
					foreach($dop_pages as $item){
						if ( filter_var($item, FILTER_VALIDATE_URL) === FALSE )
							continue;
						
						$arParamsCheck['dop_pages'][] = esc_url($item);
					}
				}
				
				$arParamsCheck['filter_pages'] = $arParamsCheck['filter_terms'] = [];
				
				foreach($arParams['post_type'] as $key=>$params){
					
					$arFilter = [];
					
					if( isset($params['filter']) )
						$filter = array_filter(array_map('trim', explode(",", $params['filter'] )), 'strlen');
					
					if( isset($filter) && is_array($filter) ){
						foreach($filter as $ID){
							$ID = intval($ID);
							if($ID == 0)
								continue;
							
							$arFilter[] = $ID;
							
							if( get_taxonomy($key) ){
								$filter_terms[] = $ID;
							}else{
								$filter_pages[] = $ID;
							}
							
						}
						$arFilter = array_unique( $arFilter );
					}
					
					$arParamsCheck['post_type'][$key] = array(
						'active' 		=> ($params['active']=='Y')? 'Y' : false,
						'slug' 			=> sanitize_text_field( $key ),
						'label' 		=> sanitize_text_field( $params['label'] ),
						'priority' 	=> intval( $params['priority'] ),
						'frequency' => intval( $params['frequency'] ),
						'filter'		=> $arFilter,
					);
					
					$filter = $arFilter = false;
				}	
				
				$arParamsCheck['filter_terms'] = $filter_terms;
				$arParamsCheck['filter_pages'] = $filter_pages;

				return $arParamsCheck;
			}
			
			return $arParams;

		}
		
		/*
		* Отображение страницы параметров
		*/
		function the_page_plugin()
		{
			global $APPLICATION;
			
			// Получаем параметры
			$arParams = $this->arParams;
			
			if(isset($APPLICATION)){
				$APPLICATION->ShowPageHeader();
			}else{
				$this->the_plugin_header();	
			}
			?>	
				<div class="tabs"> 
					<ul class="adm-detail-tabs-block">
						<li class="adm-detail-tab adm-detail-tab-active adm-detail-tab-last active" data-id="1">Общие настройки</li>
						<li class="adm-detail-tab adm-detail-tab-active adm-detail-tab-last" data-id="2">Настройка разделов</li>
						<li class="adm-detail-tab adm-detail-tab-active adm-detail-tab-last" data-id="3">Отключение страниц</li>
						<li class="adm-detail-tab adm-detail-tab-active adm-detail-tab-last" data-id="4">Помощь</li>
					</ul>
					<form method="post" enctype="multipart/form-data" action="options.php">
						
						<div class="adm-detail-content-wrap active">
							<?php
							$s_name = $arParams['PLUGIN']['SETTINGS_NAME'];
							settings_fields($s_name); ?>
							<div class="adm-detail-content">
								<div class="adm-detail-title">Основные параметры</div>
								<div class="adm-detail-content-item-block">
									<table class="adm-detail-content-table edit-table">
										<tbody>
											<tr>
												<td class="adm-detail-content-cell-l">
													<div class="massage-page" data-toggle="factory-tooltip" data-placement="right" title="" data-original-title="Позволяет включать и отключать работу отдельных компонентов.">
														<span class="type-3"></span>
													</div>
													Активность   
												</td>
												<td class="adm-detail-content-cell-r"><input name="<?php echo $s_name?>[active]" type="checkbox" <?php if( isset($arParams['params']['active']) && $arParams['params']['active']=='Y'){?>checked<?php } ?>  value="Y" class="adm-designed-checkbox"></td>
											</tr>
											<tr>
												<td class="adm-detail-content-cell-l">
													<div class="massage-page" data-toggle="factory-tooltip" data-placement="right" title="" data-original-title="Укажите название файла карты сайта sitemap.xml. Название будет использоваться при генерации файла.">
														<span class="type-3"></span>
													</div>
													XML-адрес файла Sitemap
												</td>
												<td class="adm-detail-content-cell-r">
													<div class="flex row center">
														<input name="<?php echo $s_name?>[sitemap_name]" type="text" size="45" value="<?php if( isset($arParams['params']['sitemap_name'])){ ?><?php echo  esc_html($arParams['params']['sitemap_name']);?><?php } ?>">
														.xml <?php if($arParams['params']['sitemap_name']){?>&nbsp;&nbsp;&nbsp;<a href="<?php echo get_bloginfo( 'url' ).'/'. esc_html($arParams['params']['sitemap_name']).'.xml'?>" target="_blank">Посмотреть</a><?php }?>
													</div>
													<p class="descripton"><b>Внимание!</b> После изменения URL карты сайта необходимо зайти пересохранить <b>Настройки -> Постоянные ссылки</b>.</p>
													<?php 
													if( isset($arParams['params']['sitemap_name']) ){ 
													$checkFile = $_SERVER['DOCUMENT_ROOT'].'/'.$arParams['params']['sitemap_name'].'.xml';
													if( file_exists($checkFile) ){ ?>
													<p>По указанной дериктории уже есть файл карты сайта. <code><?php echo $checkFile;?></code> Удалите или переименуйте его для коректно функционирования плагина.</p>
													<?php 
														}
													} ?>
												</td>
											</tr>
											<tr>
												<td class="adm-detail-content-cell-l">
													<div class="massage-page" data-toggle="factory-tooltip" data-placement="right" title="" data-original-title="Укажите лимит ссылок в одном файле xml.">
														<span class="type-3"></span>
													</div>
													Лимит ссылок в одном файле
												</td>
												<td class="adm-detail-content-cell-r">
													<div class="flex row center">
														<input name="<?php echo $s_name?>[limit]" type="number" size="45" min="100" max="50000" value="<?php if( isset($arParams['params']['limit'])){ ?><?php echo esc_html($arParams['params']['limit']);?><?php } ?>">
													</div>
												</td>
											</tr>
											<tr>
												<td class="adm-detail-content-cell-l">
													<div class="massage-page" data-toggle="factory-tooltip" data-placement="right" title="" data-original-title="Вы можете добавить произвольные ссылки в вашу карту сайта. Для этого вставьте список ссылок (каждая ссылка с новой строки)">
														<span class="type-3"></span>
													</div>
													Дополнительные ссылки
												</td>
												<td class="adm-detail-content-cell-r">
														<textarea name="<?php echo $s_name?>[dop_pages]" style="width:100%;height:160px"><?php if( isset($arParams['params']['dop_pages']) && is_array($arParams['params']['dop_pages']) ){ ?><?php echo implode("\n", $arParams['params']['dop_pages']);?> <?php } ?></textarea>
														<span class="description"><b style="color: #ef0000;">ВНИМАНИЕ!</b> Добавляйте каждый новый URL с новой строки.</span>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
								<div class="adm-detail-content-btns">
								    <input type="submit" name="submit" id="submit" class="iksweb-btn" value="Сохранить">
								    <a href="?page=iks-sitemap&defaul-params=Y" class="iksweb-btn iksres-btn">Сбросить настройки</a>
							    </div> 
							</div>
						</div>
						<div class="adm-detail-content-wrap">
								<div class="adm-detail-content">
								<div class="adm-detail-title">Настроки генерации</div>
						
								<div class="adm-detail-content-item-block">
									
									<table class="internal types">
										<tbody>
											<tr>
												<td class="heading center">
													<div class="flex row jcenter center">
														<div class="massage-page" data-toggle="factory-tooltip" data-placement="right" title="" data-original-title="Вы можете исключить определённый тип документов из генерации.">
															<span class="type-1"></span>
														</div>
														Активность
													</div>
												</td>
												<td class="heading">
													<div class="flex row">
														Тип записи / Таксаномия
													</div>
												</td>
												<td class="heading center">
													<div class="flex row jcenter">
														<div class="massage-page" data-toggle="factory-tooltip" data-placement="right" title="" data-original-title="Укажите приоритет переобхода для поисковиков.">
															<span class="type-1"></span>
														</div>
														Относительный приоритет
													</div>
												</td>
												<td class="heading center">
													<div class="flex row jcenter">
														<div class="massage-page" data-toggle="factory-tooltip" data-placement="right" title="" data-original-title="Укажите частоту изменений данных в разделах.">
															<span class="type-1"></span>
														</div>
														Частота обновлений
													</div>
												</td>
											</tr>
											<?php
											$this->get_post_types();
											?>		
										<tr class="heading">
											<td class="center"><input type="checkbox" class="AllRows" data-table=".types"></td>
											<td class="center"></td>
											<td class="center"></td>
											<td class="center"></td>
										</tr>
										</tbody>
									</table>
								</div>
								<div class="adm-detail-content-btns">
								    <input type="submit" name="submit" id="submit" class="iksweb-btn" value="Сохранить">
								    <a href="?page=iks-sitemap&defaul-params=Y" class="iksweb-btn iksres-btn">Сбросить настройки</a>
							    </div> 
							</div>
						</div>
						
						<div class="adm-detail-content-wrap">
							<div class="adm-detail-content">
								<div class="adm-detail-title">Управление отображением отдельных страниц/записей</div>
						
								<div class="adm-detail-content-item-block">
									
									<p>Если вам нужно указать страницы/посты которые вы бы хотели исключить из карты сайта, то укажите их ID в полях нужного раздела.</p>
									<table class="adm-detail-content-table edit-table">
										<tbody>
											<?php
											if( isset($arParams['params']['post_type']) ){
												foreach($arParams['params']['post_type'] as $key=>$type){
												if( !isset($type['active']) || $type['active']!='Y' || $key=='home_pages' )
														continue;
												?>
												<tr class="heading">
													<td colspan="2"><?php echo $type['label'];?></td>
												</tr>	
												<tr>
													<td class="adm-detail-content-cell-l">
														<div class="massage-page" data-toggle="factory-tooltip" data-placement="right" title="" data-original-title='Укажите ID элементов из "<?php echo $type['label'];?>" через запятую.'>
															<span class="type-3"></span>
														</div>
														Исключить элементы из "<?php echo $type['label'];?>"
													</td>
													<td class="adm-detail-content-cell-r">
														<div class="flex row center">
															<textarea name="<?php echo $s_name?>[post_type][<?php echo $key;?>][filter]" style="width:100%;height:100px" placeholder="ID <?php echo $type['label'];?> через запятую"><?php if( isset($arParams['params']['post_type'][$key]['filter']) && is_array($arParams['params']['post_type'][$key]['filter']) ){ ?><?php echo implode(",", $arParams['params']['post_type'][$key]['filter']);?><?php } ?></textarea>
														</div>
													</td>
												</tr>
												<?php
													}
											}else{
											?>
											<tr>
												<td>
													<p>Активируйте хотя бы 1 тип записей на предыдущей вкладке.</p>
												</td>
											</tr>
											<?php
											}
											?>
										</tbody>
									</table>
									
								</div>
								<div class="adm-detail-content-btns">
								    <input type="submit" name="submit" id="submit" class="iksweb-btn" value="Сохранить">
								    <a href="?page=iks-sitemap&defaul-params=Y" class="iksweb-btn iksres-btn">Сбросить настройки</a>
							    </div> 
							</div>
						</div>
					</form>

					<div class="adm-detail-content-wrap">
						<div class="adm-detail-content">
							<div class="adm-detail-title">Инструкция</div>
							<div class="adm-detail-content-item-block">
								
								<p>Начиная с версии плагина 2.5 мы полностью перешли на работу основанную на функционале WordPress, а именно на новый класс WP_Sitemaps (<a href="https://wp-kama.ru/function/WP_Sitemaps">читать о WP_Sitemaps</a>). Вы можете указывать все хуки данного класса в вашей function.php и они будут учитываться при генерации (если нашего функционала будет недостаточно)</p>

								<h2>Шаг 1</h2>
								<p>После установки и активации плагина, необходимо во вкладке  включить плагин (Активность) и указать название вашей карты сайта. По умолчанию данные настройки уже заданы но вы можете их изменить в любое время.</p>
								
								<h2>Шаг 2</h2>
								<p>На странице "<a href="?page=iks-sitemap#2" target="_blank">Управление разделами</a>" необходимо установить флажки напротив нужных разделов для генераций карт сайта, а также можете установить параметры (если знаете зачем это).</p>
								<p>По умолчанию активированы Страницы и Записи.</p>
								
								<h2>Шаг 3</h2>
								<p>На странице "<a href="?page=iks-sitemap#3" target="_blank">Управление страницами</a>" необходимо указать страницы, которые нужно исключить из карты .xml.</p>
								<p>По умолчанию все страницы активированы.</p>
								<p><b>После изменения параметров необходимо сохранить настройки (голубая кнопка в низу страницы)!</b></p>
								
								<h2>Готово!</h2>
								<p>Поздравляем! ваша карта создана и готова к добавлению в поисковые системы.</p>
								<p><a href="https://webmaster.yandex.ru/sites/" target="_blank">Добавить в Яндекс.Вебмасте</a> | 
								<a href="https://search.google.com/search-console" target="_blank">Добавить в Google Search Console</a></p>
								<br><br>
								<hr>
								<br>
								<h2>Термины</h2>
								<p><b>Карта сайта</b> — это древовидный (упорядоченный) список всех разделов и/или страниц сайта, который состоит из ссылок на эти страницы. Карты сайта бывают двух видов: для посетителей сайта (HTML версия) и для поисковых роботов (XML версия).</p>
								<p><b>Lastmod</b> — дата изменения документа. (Проставляется автоматически)</p>
								<p><b>Priority</b> — (приоритет) Он показывает, какие страницы нужно проиндексировать как можно скорее, а какие можно и потом, то есть данный тег расставляет приоритет важности (очередь на сканирование). Значение задаётся от 0.0 до 1.0, значение для всех URL по умолчанию равно 0.5. Priority – это относительная величина, поэтому нет смысла писать для всех страниц (с целью накрутки) приоритет – 1.0, это действие бессмысленно. Priority – не влияет на позиции страниц в поисковой выдаче! Его значение влияет только на очередь индексирования между страницами вашего сайта.</p>
								<p><b>Changefreq</b> — (частота обновлений) Позволяет указать приблизительную частоту изменений страницы. Его допустимые значения: always hourly daily weekly monthly yearly never.</p>
							</div>
						</div>
					</div>	
					
				</div>	
			</div>	
			
			<?php
			$this->the_plugin_footer();	
		}
		
		/*
		 * Отображение страницу покупки PRO версии для FREE
		*/
		public function the_page_pro_plugin()
		{
			$this->the_plugin_header();	
			?>
			<div class="tabs">
				<ul class="adm-detail-tabs-block">
					<li class="adm-detail-tab adm-detail-tab-active adm-detail-tab-last active">IKSWEB</li>
				</ul>
				<div class="adm-detail-content-wrap active">
						<div class="adm-detail-content">
							<div class="adm-detail-title">Обновить плагин до PRO</div>
							<div class="adm-detail-content-item-block">
								<p>Если вам понравилась работа нашего плагина, вы можете приобрести PRO версию и получать уникальные обновления.</p>
								<h2>Что же вы получите в PRO версии?</h2>
								<ul>
									<li><span class="dashicons dashicons-saved"></span> Первоклассную поддержку</li>
									<li><span class="dashicons dashicons-saved"></span> Расширенный набор функций</li>
									<li><span class="dashicons dashicons-saved"></span> Бесплатные обновления</li>
								</ul>
								<br>
								<a target="_blank"  href="//iksweb.ru/plugins/sitemap/?utm_content=pligin&utm_medium=wp&utm_source=<?php echo $_SERVER['SERVER_NAME'];?>&utm_campaign=plugin" class="iksweb-btn">Подробнее о PRO версии</a>

								<br><br><br>
								
								<h2>Помочь развитию проекта</h2>
								<p>Наш проект нуждается в вашей помощи. На разработку и поддержание плагинов уходит много средств и сил. Мы будем рады любой помощи.</p>
								
								<iframe src="https://yoomoney.ru/quickpay/shop-widget?writer=seller&targets=%D0%A1%D0%B1%D0%BE%D1%80%20%D1%81%D1%80%D0%B5%D0%B4%D1%81%D1%82%D0%B2%20%D0%BD%D0%B0%20%D0%BE%D0%B1%D0%BD%D0%BE%D0%B2%D0%BB%D0%B5%D0%BD%D0%B8%D0%B5%20%D0%BF%D0%BB%D0%B0%D0%B3%D0%B8%D0%BD%D0%BE%D0%B2&targets-hint=&default-sum=100&button-text=14&payment-type-choice=on&mobile-payment-type-choice=on&comment=on&hint=&successURL=https%3A%2F%2Fplugin.iksweb.ru%2Fwordpress%2F&quickpay=shop&account=4100116825216739" width="100%" height="303" frameborder="0" allowtransparency="true" scrolling="no"></iframe>
							</div>
						</div>
					</div>
			</div>
			<?php
			$this->the_plugin_footer();	
		}
		
		/*
		 * Шапка для всех страниц
		*/
		function the_plugin_header($title=false)
		{
			global $APPLICATION;
		?>	
		<div class="wrap iks-wrap">
			<h1 class="wp-heading-inline"><?php echo !empty($title)? $title : 'Настройки модуля' ;?></h1>
			<?php
			if(!empty($_REQUEST['settings-updated'])){
				$this->the_notices('Настройки компонента сохранены.');	
			}
			
			if(!isset($APPLICATION)){
				$this->the_notices('Рекомендуем установить главный модуль плагина от разработчика IKSWEB. Главный модуль позволит подключить reCaptha, собирать данные из форм и производит транслитерацию URL, а также улучшит внешний вид панели. Вы можете попробовать бесплатную версию по ссылке - <b><a href="//plugin.iksweb.ru/wordpress/wordpress-start/" target="_blank">Попробовать</a></b> и если вам понравится, то сможете приобрести платную версию с увеличенным функционалом и постоянными обновлениями.','notice-info');	
			}
		}
		
		/*
		 *  Выводи оповещение об обновление настроек
		 *  notice-success - для успешных операций. Зеленая полоска слева.
		 *	notice-error - для ошибок. Красная полоска слева.
		 *	notice-warning - для предупреждений. Оранжевая полоска слева.
		 *	notice-info - для информации. Синяя полоска слева.
		 */
		function the_notices($massage=false, $type='notice-success')
		{
			if($massage!==false){
			?>
			<div class="notice <?php echo $type?> is-dismissible">
				<p><?php echo $massage?></p>
			</div>
			<?php
			}
		}
		
		/*
		* Подвал для всех страниц
		*/
		public function the_plugin_footer()
		{
			$arParams = $this->arParams;

			// Сбрасываем настройки 
			if(isset($_REQUEST['defaul-params']) && $_REQUEST['defaul-params']=='Y'){
				$this->set_default_params();
				$this->the_notices('Настройки компонента сброшены.');	
				// Делаем редирект на эту же страницу, что бы обновить показатели
				?>
				<script> (function($){ setTimeout(function(){ window.location.href = '?page=iks-sitemap#2'; }, 100); })(jQuery); </script>
				<?php
			} ?>
			<div class="footer-page">
				<div class="iksweb-box">
					<ul>
						<li><span class="type-1"></span> - Нейтральная настройка, которая не может нанести вред вашему сайту.</li>
						<li><span class="type-2"></span> - При включении этой настройки, вы должны быть осторожны. Некоторые плагины и темы могут зависеть от этой функции.</li>
		        <li><span class="type-3"></span> - Абсолютно безопасная настройка, рекомендуем использовать.</li>
						<li>----------</li>
						<li>Наведите указатель мыши на значок, чтобы получить справку по выбранной функции.</li>
		      </ul>
				</div>
				<div class="iksweb-box">
					<p><b>Вы хотите, чтобы плагин улучшался и обновлялся?</b></p>
					<p>Помогите нам, оставьте отзыв на wordpress.org. Благодаря отзывам, мы будем знать, что плагин действительно полезен для вас и необходим.</p>
					<p style="margin: 9px 0;">А также напишите свои идеи о том, как расширить или улучшить плагин.</p>
					<div class="vote-me">
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
					</div>
					<a href="//wordpress.org/plugins/iksweb-sitemap/?utm_content=pligin&utm_medium=wp&utm_source=<?php echo $_SERVER['SERVER_NAME'];?>&utm_campaign=plugin" target="_blank"><strong>Оставить отзыв или поделиться идеей</strong></a>
					
					<p style="margin: 5px 0 0 0; font-weight: bold; color: #d63638;">Хотите поддержать плагин? - <a href="//iksweb.ru/payment/" target="_blank">Пожертвовать</a></p>
				</div>
				<div class="iksweb-box">
					<p><b>Возникли проблемы?</b></p>
					<p>Мы предоставляем платную и бесплатную поддержку для наших <a href="//iksweb.ru/plugins/?utm_content=pligin&utm_medium=wp&utm_source=<?php echo $_SERVER['SERVER_NAME'];?>&utm_campaign=plugin" target="_blank">плагинов</a>. Если вас столкнули с проблемой, просто создайте новый тикет. Мы обязательно вам поможем!</p>
					<p><span class="dashicons dashicons-sos" style="margin: -4px 5px 0 0;"></span> <a href="//iksweb.ru/plugins/support/?utm_content=pligin&utm_medium=wp&utm_source=<?php echo $_SERVER['SERVER_NAME'];?>&utm_campaign=plugin" target="_blank">Получите поддержку</a></p>
					<div style="margin: 15px 0 10px;background: #fff4f1;padding: 10px;color: #a58074;">
						<span class="dashicons dashicons-warning" style="margin: -4px 5px 0 0;"></span> Если вы обнаружите ошибку php или уязвимость в плагине, вы можете <a href="//iksweb.ru/plugins/support/?utm_content=pligin&utm_medium=wp&utm_source=<?php echo $_SERVER['SERVER_NAME'];?>&utm_campaign=plugin" target="_blank">создать тикет</a> в поддержке, на который мы ответим мгновенно.
					</div>
				</div>
			</div> 
		<?php
		}
		
		/*
		* Подключаем JS и CSS к панели
		*/
		function set_plugin_scripts()
		{
			global $APPLICATION;
			
			$arParams = $this->arParams['PLUGIN'];

			// Если не установлен главный модуль
			if(!isset($APPLICATION)){
				wp_enqueue_script( 'tooltip', $arParams['URL'].'assets/js/bootstrap.tooltip.min.js', array(), $arParams['VERSIA'] , true );
				wp_enqueue_style( 'iksweb', $arParams['URL'].'assets/css/iksweb.css', array(), $arParams['VERSIA'] );
			}
			
			wp_enqueue_script( 'iks-sitemap', $arParams['URL'].'assets/js/script.js', array(), $arParams['VERSIA'] , true );
			wp_enqueue_style( 'iks-sitemap', $arParams['URL'].'assets/css/style.css', array(), $arParams['VERSIA'] );
		}
		
		/*
		* Формируем данные для таблицы
		*/
		function get_post_types(){

			$arParams = $this->arParams;

			$get_post_types = get_post_types( [ 'public' => true, 'publicly_queryable'=>1 ] , ['name','label'] );
			
			unset($get_post_types['attachment']);
			
			if( isset($get_post_types) ){
				foreach($get_post_types as $type){
					$this->arParams['params']['post_type'][$type->name]['label'] = $type->label;
				}
			}
			
			/*
			Производим вывод разделов для настроек
			*/
			$this->render_post_types();
		}
		
		/*
		Выводим роздел в список
		*/
		function render_post_types()
		{
			$arParams = $this->arParams;
			
			if( isset($arParams['params']['post_type']) ){ 
				foreach( $arParams['params']['post_type'] as $key=>$item ){
					?>
						<tr id="<?php echo $key;?>">
							<td class="center">
								<input type="hidden" name="<?php echo $arParams['PLUGIN']['SETTINGS_NAME'];?>[post_type][<?php echo $key;?>][label]" value="<?php echo $item['label'];?>">
								<input <?php echo ($key=='home_pages')? 'onclick="return false"' : '';?> type="checkbox" name="<?php echo $arParams['PLUGIN']['SETTINGS_NAME'];?>[post_type][<?php echo $key;?>][active]" <?php echo !empty($arParams['params']['post_type'][$key]['active'])? 'checked' : '';?> data-id="<?php echo $key;?>" value="Y">
							</td>
							<td><?php echo $item['label'];?></td>
							<td class="center" style="padding:2px 4px 3px!important">
								<select name="<?php echo $arParams['PLUGIN']['SETTINGS_NAME'];?>[post_type][<?php echo $key;?>][priority]" id="<?php echo $key;?>_Priority" class="priority" data-type="<?php echo $key;?>">
									<option value="0">Нет</option>
									<option value="2">0.0</option>
									<option value="3">0.1</option>
									<option value="4">0.2</option>
									<option value="5">0.3</option>
									<option value="6">0.4</option>
									<option value="7">0.5</option>
									<option value="8">0.6</option>
									<option value="9">0.7</option>
									<option value="10">0.8</option>
									<option value="11">0.9</option>
									<option value="12">1.0</option>
								</select>
							</td>	
							<td class="center" style="padding:2px 4px 3px!important">
								<select name="<?php echo $arParams['PLUGIN']['SETTINGS_NAME'];?>[post_type][<?php echo $key;?>][frequency]" id="<?php echo $key;?>_Frequency" class="frequency" data-type="<?php echo $key;?>">
									<option value="0">Нет</option>
									<option value="8">всегда</option>
									<option value="7">ежечасно</option>
									<option value="6">ежедневно</option>
									<option value="5">еженедельно</option>
									<option value="4">ежемесячно</option>
									<option value="3">ежегодно</option>
									<option value="2">никогда</option>
								</select>
								<script>
								// Проставляем выбранные параметры на JS
								(function($){
									$('#<?php echo $key;?>_Priority option[value=<?php echo $arParams['params']['post_type'][$key]['priority'];?>]').prop('selected', true);
									$('#<?php echo $key;?>_Frequency option[value=<?php echo $arParams['params']['post_type'][$key]['frequency'];?>]').prop('selected', true);
								})(jQuery);
								</script>
							</td>
						</tr>
					<?php	
					}
				}
		}

		/*
		* Формируем верный параметр обновления URL
		*/
		static function get_frequency($value=false)
		{
			switch ($value) {
				case 0:
					return "";
					break;
				case 1:
					return "default";
					break;
				case 2:
					return "never";
					break;
				case 3:
					return "yearly";
					break;
				case 4:
					return "monthly";
					break;
				case 5:
					return "weekly";
					break;
				case 6:
					return "daily";
					break;
				case 7:
					return "hourly";
					break;
				case 8:
					return "always";
					break;
				default:
					return "xxx";
			}
		}
				
		/*
		* Формируем верный параметр приоритета URL
		*/
		static function get_priority($value=false)
		{
			switch ($value) {
				case 0:
					return "";
					break;
				case 1:
					return "default";
					break;
				case 2:
					return "0.0";
					break;
				case 3:
					return "0.1";
					break;
				case 4:
					return "0.2";
					break;
				case 5:
					return "0.3";
					break;
				case 6:
					return "0.4";
					break;
				case 7:
					return "0.5";
					break;
				case 8:
					return "0.6";
					break;
				case 9:
					return "0.7";
					break;
				case 10:
					return "0.8";
					break;
				case 11:
					return "0.9";
					break;
				case 12:
					return "1.0";
					break;
				default:
					return "xxx";
			}
		}
		
	}
	
	// globals
	global $IKSWEB, $iksweb_sitemaps;
	
	// initialize
	if( !isset($iksweb_sitemaps) ) {
		$iksweb_sitemaps = new iksweb_sitemaps();
		$iksweb_sitemaps->init();
	}

}