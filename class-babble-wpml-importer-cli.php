<?php
/**
 * WPML to Babble import
 * Note: Deactivate WPML plugin
 **/

if ( class_exists( "WP_CLI_Command" ) ):

	class Babble_WPML_Importer_CLI {
		protected $paged = 0;
		protected $posts_per_page = 400;
		protected $excluded_post_types = array( 'revisions', 'attachment' );
		protected $post_types = array();

		function __construct() {
			$base_post_types = array_keys( bbl_get_base_post_types() );
			foreach ( $base_post_types as $post_type ) {
				if ( false === in_array( $post_type, $this->excluded_post_types ) ) {
					$this->post_type[ ] = $post_type;
				}
			}
		}

		/**
		 * Start WPML to Babble import.
		 *
		 * ## EXAMPLES
		 *
		 *     wp babble import
		 *
		 * @synopsis
		 */
		function import( $args, $assoc_args ) {
			//Can't use get_posts as babble plugin will filter the post
			global $wpdb, $bbl_languages, $bbl_post_public, $bbl_taxonomies;
			global $bbl_jobs;

			if ( !isset( $bbl_languages ) ) {
				WP_CLI::error( 'Please install/activate babble plugin' );
			}
			$wmpl_languages = $wpdb->get_results( "select code, default_locale from {$wpdb->prefix}icl_languages where active = 1", OBJECT_K );

			$babble_wpml_active_lang_diff = array_diff( array_keys( $wmpl_languages ), array_keys( bbl_get_active_langs() ) );

			foreach ( $babble_wpml_active_lang_diff as $lang ) {
				WP_CLI::warning( $bbl_languages::format_code_lang( $wmpl_languages[ $lang ]->default_locale ) . ' : Not Found' );
			}

			if ( !empty( $babble_wpml_active_lang_diff ) ) {
				WP_CLI::error( 'Please install and configure above languages in to babble to start importer' );
			}
			WP_CLI::warning( "Star Importer..." );

			// Migrate Menu

			$nav_menus        = wp_get_nav_menus( array( 'orderby' => 'name', 'bbl_translate' => false ) );
			$translated_menus = array();

			foreach ( $nav_menus as $menu ) {
				$wmpl_translated_menu_map = $this->_get_wpml_tax_translations( $menu );

				if ( false === empty( $wmpl_translated_menu_map ) ) {

					$default_language_menu_id = intval( $menu->term_id );

					foreach ( $wmpl_translated_menu_map as $language_code => $wpml_menu ) {
						if ( bbl_get_default_lang_code() === $wmpl_languages[ $language_code ]->default_locale ) {
							$default_language_menu_id = $wpml_menu->element_id;
						}

					}
					if ( in_array( $default_language_menu_id, $translated_menus ) ) {
						continue;
					}
					$translated_menus[ ] = $default_language_menu_id;

					$default_language_menu = get_term( $default_language_menu_id, 'nav_menu' );

					foreach ( $wmpl_translated_menu_map as $language_code => $wpml_menu ) {
						$lang    = $wmpl_languages[ $language_code ]->default_locale;
						$menu_id = $wpml_menu->element_id;

						$menu_items = wp_get_nav_menu_items( $menu_id, array(
							'post_status'   => 'any',
							'bbl_translate' => false
						) );

						if ( $menu_items === false ) {
							continue;
						}
						foreach ( $menu_items as $menu_post ) {

							$menu_classes = get_post_meta( $menu_post->ID, '_menu_item_classes', true );
							if ( is_array( $menu_classes ) ) {
								$menu_classes[ ] = $lang;
							} else {
								$menu_classes = array( $lang );
							}
							update_post_meta( $menu_post->ID, '_menu_item_classes', $menu_classes );

							if ( '' !== get_post_meta( $menu_post->ID, '_menu_lang_code', true ) ) {
								continue;
							}

							update_post_meta( $menu_post->ID, '_menu_lang_code', $lang );

							if ( $menu_id === $default_language_menu_id ) {
								continue;
							}
						}
						if ( $menu_id !== $default_language_menu_id ) {
							wp_delete_term( $menu_id, 'nav_menu', array( 'default' => $default_language_menu_id ) );
						}

					}
				}
			}
			
			//Migrate Post
			$posts = $this->_get_post();

			$file_progress = new \cli\progress\Bar( 'Progress', $posts->found_posts );

			while ( count( $posts->posts ) > 0 ) {
				foreach ( $posts->posts as $post ) {
					//get post current language
					$wmpl_trasalated_posts_map = $this->_get_wpml_post_translations( $post );
					if ( false === empty( $wmpl_trasalated_posts_map ) ) {
						$default_language_post_id        = $post->ID;
						$default_language_post_code      = false;
						$default_last_language_post_code = '';

						foreach ( $wmpl_trasalated_posts_map as $language_code => $wpml_single_post_map ) {
							$lang_post_id = $wpml_single_post_map->element_id;
							if ( bbl_get_default_lang_code() === $wmpl_languages[ $language_code ]->default_locale ) {
								$default_language_post_id   = $lang_post_id;
								$default_language_post_code = $wmpl_languages[ $language_code ]->default_locale;
							} else {
								$default_last_language_post_code = $wmpl_languages[ $language_code ]->default_locale;
							}
						}

						if ( false === $default_language_post_code ) {
							$default_language_post_code = $default_last_language_post_code;
						}

						$default_language_post = get_post( $default_language_post_id );
						$transid               = $bbl_post_public->get_transid( $post->ID );
						foreach ( $wmpl_trasalated_posts_map as $language_code => $wpml_single_post_map ) {
							$bbl_jobs->no_recursion = false;
							$lang_post_id           = $wpml_single_post_map->element_id;
							// Assign transid to translation:
							if ( 'done' === get_post_meta( $lang_post_id, '_bbl_wpml_transalated', true ) ) {
								continue;
							}
							update_post_meta( $lang_post_id, 'bbl_post_original_lang', $default_language_post_code );

							$bbl_post_public->set_transid( $lang_post_id, $transid );

							if ( bbl_get_default_lang_code() === $wmpl_languages[ $language_code ]->default_locale ) {
								$default_language_post_id = $lang_post_id;
							} else {
								$lang_code = $wmpl_languages[ $language_code ]->default_locale;

								$lang_post = get_post( $lang_post_id );

								global $bbl_jobs;
								$existing_jobs = $bbl_jobs->get_incomplete_post_jobs( $post );

								if ( isset( $existing_jobs[ $lang_code ] ) ) {
									$job = get_post( $existing_jobs[ $lang_code ] );
								} else {
									$jobs = $bbl_jobs->create_post_jobs( $default_language_post_id, (array) $lang_code );
									$job  = get_post( $jobs[ 0 ] );
								}

								$job->post_title   = $lang_post->post_title;
								$job->post_name    = $lang_post->post_name;
								$job->post_content = $lang_post->post_content;
								$job->post_status  = 'complete';
								$post_meta         = get_post_meta( $lang_post->ID );

								update_post_meta( $job->ID, 'bbl_post_original_lang', $default_language_post_code );

								foreach ( $post_meta as $meta_key => $val ) {
									foreach ( $val as $meta_value ) {
										update_post_meta( $job->ID, $meta_key, $meta_value );
									}
								}
								wp_update_post( $job, true );

								wp_set_object_terms( $job->ID, stripslashes( $lang_code ), 'bbl_job_language', false );
								$language = get_the_terms( $job, 'bbl_job_language' );

								if ( empty( $language ) ) {
									return false;
								} else {
									$lang_code = reset( $language )->name;
								}


								add_post_meta( $job->ID, 'bbl_job_post', "{$post->post_type}|{$post->ID}", true );

								foreach ( $bbl_jobs->get_post_terms_to_translate( $post->ID, $lang_code ) as $taxo => $terms ) {
									foreach ( $terms as $term_id => $term ) {
										add_post_meta( $job->ID, 'bbl_job_term', "{$taxo}|{$term_id}", false );
									}
								}

								$lang_post->post_type = bbl_get_post_type_in_lang( $post->post_type, $wmpl_languages[ $language_code ]->default_locale );


								if ( $lang_post->post_date == '0000-00-00 00:00:00' ) {
									$lang_post->post_date     = $default_language_post->post_date;
									$lang_post->post_date_gmt = $default_language_post->post_date_gmt;
									if ( $lang_post->post_date == '0000-00-00 00:00:00' ) {
										$lang_post->post_date = $lang_post->post_date_gmt;
									}
								}

								wp_update_post( $lang_post, true );


								update_post_meta( $job->ID, "bbl_post_{$default_language_post_id}", $lang_post );


								//bbl_get_base_post_type($post->post_type)
								$base_post_type = bbl_get_base_post_type( $post->post_type );

								if ( 'page' == $base_post_type ) {
									$custom_page_template = get_post_meta( $default_language_post_id, '_wp_page_template', true );
									update_post_meta( $lang_post_id, '_wp_page_template', $custom_page_template );
								}

								$taxonomies = get_object_taxonomies( $base_post_type );

								foreach ( $taxonomies as $tax ) {
									if ( in_array( $tax, $bbl_taxonomies->ignored_taxonomies() ) ) {
										continue;
									}

									$post_terms = wp_get_post_terms( $lang_post_id, $tax );

									foreach ( $post_terms as $p_term ) {
										$translated_terms         = $this->_get_wpml_tax_translations( $p_term );
										$default_language_term_id = 0;

										if ( !is_array( $translated_terms ) ) {
											global $bbl_taxonomies;
											$t_trans_id          = $bbl_taxonomies->get_transid( $p_term->term_id );
											$new_term            = bbl_get_term_in_lang( $p_term->term_id, $p_term->taxonomy, $wmpl_languages[ $language_code ]->default_locale );
											$translated_taxonomy = bbl_get_taxonomy_in_lang( $tax, $wmpl_languages[ $language_code ]->default_locale );

											if ( $new_term->taxonomy !== $translated_taxonomy ) {
												$new_term = wp_insert_term( $p_term->name, $translated_taxonomy, array(
													'description' => $p_term->description,
													'slug'        => $p_term->slug
												) );
												$new_term = get_term( $new_term[ 'term_id' ], $translated_taxonomy );
												$bbl_taxonomies->set_transid( $new_term->term_id, $t_trans_id );
											}

											if ( is_wp_error( $new_term ) ) {
												exit;
												continue;
											}
											wp_set_object_terms( $lang_post_id, $new_term->slug, $translated_taxonomy, true );

											wp_delete_object_term_relationships( $lang_post_id, $tax );
											continue;
										}
										foreach ( $translated_terms as $t_language_code => $p_term ) {
											if ( bbl_get_default_lang_code() === $wmpl_languages[ $t_language_code ]->default_locale ) {
												$default_language_term_id = $p_term->element_id;
											} else {
												$default_last_language_term_id = $p_term->element_id;
											}
										}

										if ( 0 === $default_language_term_id ) {
											$default_language_term_id = $default_last_language_term_id;
										}
										if ( null == $default_language_term_id ) {
											continue;
										}
										global $bbl_taxonomies;
										$t_trans_id  = $bbl_taxonomies->get_transid( intval( $default_language_term_id ) );
										$found_terms = false;

										foreach ( $translated_terms as $t_language_code => $t_term ) {
											if ( $wmpl_languages[ $language_code ]->default_locale === $wmpl_languages[ $t_language_code ]->default_locale ) {
												$found_terms       = true;
												$t_current_term_id = intval( $t_term->element_id );
												$term              = get_term( intval( $t_term->element_id ), $tax );
												if ( is_wp_error( $term ) ) {
													continue;
												}
												if ( null == $term ) {
													continue;
												}

												$bbl_taxonomies->set_transid( $t_current_term_id, $t_trans_id );

												$translated_taxonomy = bbl_get_taxonomy_in_lang( $tax, $wmpl_languages[ $language_code ]->default_locale );

												$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => $translated_taxonomy ), array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
											}

										}
										if ( $found_terms == false ) {
											foreach ( $translated_terms as $t_language_code => $t_term ) {
												if ( bbl_get_default_lang_code() === $wmpl_languages[ $t_language_code ]->default_locale ) {
													$t_current_term_id = intval( $t_term->element_id );
													$term              = get_term( intval( $t_term->element_id ), $tax );
													if ( is_wp_error( $term ) ) {
														continue;
													}
													if ( null == $term ) {
														continue;
													}
													$translated_taxonomy = bbl_get_taxonomy_in_lang( $tax, $wmpl_languages[ $language_code ]->default_locale );

													$new_term = wp_insert_term( $term->name, $translated_taxonomy, array(
														'description' => $term->description,
														'slug'        => $term->slug
													) );

													if ( is_wp_error( $new_term ) ) {
														continue;
													}
													if ( null == $new_term ) {
														continue;
													}

													$bbl_taxonomies->set_transid( $new_term[ 'term_id' ], $t_trans_id );
												}

											}
										}
									}

									//Pending Tax migration

								}
							}
							update_post_meta( $lang_post_id, '_bbl_wpml_transalated', 'done' );
						}
					}
					$file_progress->tick();
				}
				//Next Posts
				$posts = $this->_get_post();
			}
			WP_CLI::success( 'Imported' );
		}

		protected function _get_wpml_post_translations( $post ) {
			global $wpdb;
			$trid = $wpdb->get_var( $wpdb->prepare( "select trid from {$wpdb->prefix}icl_translations where element_id = %d and  element_type= %s", $post->ID, 'post_' . $post->post_type ) );
			if ( null === $trid ) {
				return false;
			}

			return $wpdb->get_results( $wpdb->prepare( "select language_code,element_id from {$wpdb->prefix}icl_translations where trid = %d and  element_type= %s", $trid, 'post_' . $post->post_type ), OBJECT_K );
		}

		protected function _get_wpml_tax_translations( $tax ) {
			global $wpdb;
			$trid = $wpdb->get_var( $wpdb->prepare( "select trid from {$wpdb->prefix}icl_translations where element_id = %d and  element_type= %s", $tax->term_id, 'tax_' . $tax->taxonomy ) );
			if ( null === $trid ) {
				return false;
			}

			return $wpdb->get_results( $wpdb->prepare( "select language_code,element_id from {$wpdb->prefix}icl_translations where trid = %d and  element_type= %s", $trid, 'tax_' . $tax->taxonomy ), OBJECT_K );
		}


		protected function _get_post() {
			$args = array(
				// We want a clean listing, without any particular language
				'bbl_translate'  => false,
				'post_type'      => $this->post_type,
				'post_status'    => 'any',
				'paged'          => $this->paged ++,
				'posts_per_page' => $this->posts_per_page,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'meta_key' => '_bbl_wpml_transalated',
						'compare'  => 'NOT EXISTS',
						'value'    => ''

					)
				)
			);

			return new WP_Query( $args );

		}

		protected function _reset_get_post() {
			$this->paged = 0;
		}

	}

	WP_CLI::add_command( 'babble', 'Babble_WPML_Importer_CLI' );
endif;