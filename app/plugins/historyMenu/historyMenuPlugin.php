<?php
/* ----------------------------------------------------------------------
 * historyMenuPlugin.php : implements editing activity menu - a list of recently edited items
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2009-2018 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */
 
	class historyMenuPlugin extends BaseApplicationPlugin {
		# -------------------------------------------------------
		private $opo_config;
		# -------------------------------------------------------
		public function __construct($ps_plugin_path) {
			$this->description = _t('Adds a "history" menu listing all recently edited items');
			$this->opo_config = Configuration::load($ps_plugin_path . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'historyMenu.conf');

			parent::__construct();
		}
		# -------------------------------------------------------
		/**
		 * Override checkStatus() to return true - the historyMenu plugin always initializes ok
		 */
		public function checkStatus() {
			return array(
				'description' => $this->getDescription(),
				'errors' => array(),
				'warnings' => array(),
				'available' => true
			);
		}
		# -------------------------------------------------------
		/**
		 * Record editing activity
		 */
		public function hookEditItem($pa_params) {
			if (($pa_params['id'] > 0) && ($o_req = $this->getRequest())) {
				if (!is_array($va_activity_list = Session::getVar($pa_params['table_name'].'_history_id_list'))) {
					$va_activity_list = array();
				}
				
				if (!method_exists($pa_params['instance'], "getTypeID")) { return $pa_params; }
				
				// TODO: This should be a configurable preference of some kind
				$vn_max_num_items_in_activity_menu = 20;
				
				if((!isset($va_activity_list[$pa_params['id']])) && (sizeof($va_activity_list) >= $vn_max_num_items_in_activity_menu)) {
					$va_activity_list = array_slice($va_activity_list, (sizeof($va_activity_list) - $vn_max_num_items_in_activity_menu - 1), $vn_max_num_items_in_activity_menu - 1, true);
				}
				
				if (!isset($va_activity_list[$pa_params['id']])) {
					AppNavigation::clearMenuBarCache($o_req);
				}
				$va_activity_list[$pa_params['id']] = array(
					'time' => time(),
					'type_id' => $pa_params['instance']->getTypeID(),
					'idno' => $pa_params['instance']->get('idno'),
				);
				
				Session::setVar($pa_params['table_name'].'_history_id_list', $va_activity_list);
			}
			return $pa_params;
		}
		# -------------------------------------------------------
		/**
		 * Record save activity
		 */
		public function hookSummarizeItem($pa_params) {
			$this->hookEditItem($pa_params);
			
			return $pa_params;
		}
		# -------------------------------------------------------
		/**
		 * Record save activity
		 */
		public function hookSaveItem($pa_params) {
			$this->hookEditItem($pa_params);
			
			return $pa_params;
		}
		# -------------------------------------------------------
		/**
		 * Record delete activity
		 */
		public function hookDeleteItem($pa_params) {
			if ($o_req = $this->getRequest()) {
				if (!is_array($va_activity_list = Session::getVar($pa_params['table_name'].'_history_id_list'))) {
					$va_activity_list = array();
				}
				unset($va_activity_list[$pa_params['id']]);
				Session::setVar($pa_params['table_name'].'_history_id_list', $va_activity_list);
				
				AppNavigation::clearMenuBarCache($o_req);
			}
			return $pa_params;
		}
		# -------------------------------------------------------
		/**
		 * Insert activity menu
		 */
		public function hookRenderMenuBar($pa_menu_bar) {
			if ($o_req = $this->getRequest()) {
				$va_activity_lists = array();

				if($this->opo_config instanceof Configuration) {
					$va_menu_item_names = $this->opo_config->getAssoc('menuItemNames');
				}

				foreach(array(
					'ca_objects', 'ca_object_lots', 'ca_entities', 'ca_places', 'ca_occurrences', 
					'ca_collections', 'ca_storage_locations', 'ca_loans', 'ca_movements', 'ca_list_items', 'ca_sets', 'ca_tours', 'ca_tour_stops'
				) as $vs_table_name) {
					$va_activity_menu_list = array();
					if (!is_array($va_activity_list = Session::getVar($vs_table_name.'_history_id_list'))) {
						$va_activity_list = array();
					}
				
					if (sizeof($va_activity_list) == 0) { continue; }
					
					$t_instance = Datamodel::getInstanceByTableName($vs_table_name, true);
					$va_labels = $t_instance->getPreferredDisplayLabelsForIDs(array_keys($va_activity_list));
					
					if ($vs_table_name === 'ca_occurrences') {
						$vs_priv_name = 'can_edit_ca_occurrences';
						
						// Output occurrences grouped by type with types as top-level menu items
						$va_types = $t_instance->getTypeList();
						
						// sort occurrences by type
						$va_sorted_by_type_id = array();
						$va_keys = array_reverse(array_keys($va_activity_list));
						foreach($va_keys as $vn_id) {
							$va_info = $va_activity_list[$vn_id];
							$va_sorted_by_type_id[$va_info['type_id']][$vn_id] = $va_info;
						}
						foreach($va_types as $vn_type_id => $va_type_info) {
							$va_activity_menu_list = array();
							if (isset($va_sorted_by_type_id[$vn_type_id]) && is_array($va_sorted_by_type_id[$vn_type_id])) {
								foreach($va_sorted_by_type_id[$vn_type_id] as $vn_id => $va_info) {
									$va_editor_url_info = caEditorUrl($o_req, $vs_table_name, $vn_id, true);
									
									$va_activity_menu_list[$vs_table_name.'_'.$vn_type_id.'_'.$vn_id] = array(
										'default' => $va_editor_url_info,
										'displayName' => $va_labels[$vn_id].((trim($va_info['idno'])) ? ' ['.$va_info['idno'].']' : ''),
										'is_enabled' => 1,
										'requires' => array(
											'action:'.$vs_priv_name => 'OR'
										),
										'parameters' => array(
											$va_editor_url_info['_pk'] => $vn_id
										)
									);
								}
							}
							
							if (sizeof($va_activity_menu_list) > 0) {
								$va_activity_lists[$vs_table_name.'_'.$vn_type_id] = array(
									'displayName' => caUcFirstUTF8Safe($va_type_info['name_plural']),
									'submenu' => array(
										"type" => 'static',
										'navigation' => $va_activity_menu_list
									)
								);
							}
						}
					} else {
						// Non-occurrences get grouped by their table
						switch($vs_table_name) {
							case 'ca_list_items':
								$vs_priv_name = 'can_edit_ca_lists';
								break;
							case 'ca_object_representations':
								$vs_priv_name = 'can_edit_ca_objects';
								break;
							case 'ca_tour_stops':
								$vs_priv_name = 'can_edit_ca_tours';
								break;
							case 'ca_sets':
								$vs_priv_name = 'can_edit_sets';
								break;
							default:
								$vs_priv_name = 'can_edit_'.$vs_table_name;
								break;
						}
						
						$va_keys = array_reverse(array_keys($va_activity_list));
						foreach($va_keys as $vn_id) {
							$va_info = $va_activity_list[$vn_id];
							$va_editor_url_info = caEditorUrl($o_req, $vs_table_name, $vn_id, true);
							$va_activity_menu_list[$vs_table_name.'_'.$vn_id] = array(
								'default' => $va_editor_url_info,
								'displayName' => $va_labels[$vn_id].((trim($va_info['idno'])) ? ' ['.$va_info['idno'].']' : ''),
								'is_enabled' => 1,
								'requires' => array(
									'action:'.$vs_priv_name => 'OR'
								),
								'parameters' => array(
									$va_editor_url_info['_pk'] => $vn_id
								)
							);
						}

						if(is_array($va_menu_item_names) && isset($va_menu_item_names[$vs_table_name])){
							$vs_display_name = $va_menu_item_names[$vs_table_name];
						} else {
							$vs_display_name = caUcFirstUTF8Safe(_t($t_instance->getProperty('NAME_PLURAL')));
						}
					
						$va_activity_lists[$vs_table_name] = array(
							'displayName' => $vs_display_name,
							'submenu' => array(
								"type" => 'static',
								'navigation' => $va_activity_menu_list
							)
						);
					}
					
				}
				if(sizeof($va_activity_lists)) {	// only show history menu if there's some history...
					$va_activity_menu = array(
						'displayName' => _t('History'),
						'navigation' => $va_activity_lists
					);
					$pa_menu_bar['activity_menu'] = $va_activity_menu;
				}
			} 
			return $pa_menu_bar;
		}
		# -------------------------------------------------------
	}
