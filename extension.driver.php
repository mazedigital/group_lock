<?php

class extension_group_lock extends Extension {

	//todo inherit group permission from association
	
	public function getSubscribedDelegates() {
		return array(
			array(
				'page' => '/frontend/',
				'delegate' => 'FrontendPageResolved',
				'callback' => 'checkFrontendPagePermissions'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'FrontendParamsResolve',
				'callback' => 'addMemberDetailsToPageParams'
			),
			array(
				'page' => '/frontend/',
				'delegate' => 'FrontendProcessEvents',
				'callback' => 'appendLoginStatusToEventXML'
			),
			array(
				'page' => '/backend/',
				'delegate' => 'InitialiseAdminPageHead',
				'callback' => 'initializeAdmin',
			),
			array(
				'page' => '/backend/',
				'delegate' => 'AdminPagePostCallback',
				'callback' => 'adminPagePostCallback',
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AddElementstoAuthorForm',
				'callback' => 'addGroupPicker'
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AuthorPreDelete',
				'callback' => 'deleteAuthorGroup'
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AuthorPostCreate',
				'callback' => 'saveAuthorGroup'
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AuthorPostEdit',
				'callback' => 'saveAuthorGroup'
			),
			array(
				'page' => '/publish/',
				'delegate' => 'AdjustPublishFiltering',
				'callback' => 'adjustPublishFiltering'
			),
			array(
				'page' => '/system/authors/',
				'delegate' => 'AdjustAuthorFiltering',
				'callback' => 'adjustAuthorFiltering'
			),
			array(
				'page' => '/publish/',
				'delegate' => 'AssociationFiltering',
				'callback' => 'associationFiltering'
			),
			array(
				'page' => '/publish/',
				'delegate' => 'AssociatedEntryCount',
				'callback' => 'associatedEntryCount'
			),
			array(
				'page' => '/publish/edit/',
				'delegate' => 'EntryPreRender',
				'callback' => 'entryPreRender'
			),
			array(
				'page' => '/publish/edit/',
				'delegate' => 'EntryPreEdit',
				'callback' => 'entryPreEdit'
			),
			array(
				'page' => '/publish/edit/',
				'delegate' => 'EntryPreCreate',
				'callback' => 'entryPreCreate'
			),
			array(
				'page' => '/publish/',
				'delegate' => 'EntryPreDelete',
				'callback' => 'entryPreDelete'
			),
			array(
				'page' => '/backend/',
				'delegate' => 'NavigationPreRender',
				'callback' => 'navigationPreRender'
			),
			array(
				'page' => '/system/preferences/',
				'delegate' => 'AddCustomPreferenceFieldsets',
				'callback' => 'appendPreferences'
			),
		);
	}

	/**
	 * Check if the current user is the Author of the entry
	 * @param $entry - the entry to be checked
	 * @param $rules - the rules applying to this section
	 * @return boolean
	 */
	private function superseedsPermissions(){
		return Symphony::Author()->isDeveloper() || (Symphony::Author()->isManager() && Symphony::Configuration()->get('manager', 'group_lock') !== 'yes');
	}

	private function getMainSectionPrimaryFieldId(){
		$section_id = (string)Symphony::Configuration()->get('section_id', 'group_lock');
		return current(FieldManager::fetch(null,$section_id,'ASC','sortorder',null,'main'," AND `t1`.`sortorder`='0' "))->get('id');
	}

	private function getChildAssociations($section_id,&$sectionAssociations){
		$childAssociations = SectionManager::fetchChildAssociations($section_id);
		foreach ($childAssociations as $key => $value) {
			if ($value['child_section_id'] == $value['parent_section_id']){
				// we do not want sections linking to themselves
				continue;
			}

			if (empty($sectionAssociations[$value['child_section_id']])){
				$sectionAssociations[$value['child_section_id']] = array( array() );
			}

			$sectionAssociations[$value['child_section_id']][] = array($value['child_section_field_id'] , $value['child_section_field_id'] == Symphony::Configuration()->get("section_{$value['child_section_id']}_field_id", 'group_lock'), FieldManager::fetchHandleFromID($value['child_section_field_id']));
		}
	}

	public function appendPreferences($context){
		$group = new XMLElement('fieldset',null,array('class'=>'settings'));
		$group->appendChild(new XMLElement('legend', 'Group Lock'));
					
		$div = new XMLElement('div',null,array('class'=>'group'));
		$label = Widget::Label();
		$sections = SectionManager::fetch();
		$options = array();
		foreach ($sections as $key => $section) {
			$options[$key] = array($section->get('id'), $section->get('id') == (string)Symphony::Configuration()->get('section_id', 'group_lock'), $section->get('name'));
		}

		$input = Widget::Select("settings[group_lock][section_id]", $options);
		$label->setValue(__('Group Section') . $input->generate());
		$div->appendChild($label);
		$div->appendChild(new XMLElement('p','Select the section which will be used for permission purposes.',array('class'=>'help')));
		
		$group->appendChild($div);

		$section_id = Symphony::Configuration()->get('section_id', 'group_lock');

		$sectionAssociations = array();
		$this->getChildAssociations($section_id,$sectionAssociations);

		foreach ($sectionAssociations as $key => $value) {
			$this->getChildAssociations($key,$sectionAssociations);
		}

		if ($section_id && !empty($sectionAssociations)){

			$div = new XMLElement('div',null,array('class'=>'group'));

			foreach ($sections as $key => $section) {

				if (!isset($sectionAssociations[$section->get('id')]) || empty($sectionAssociations[$section->get('id')])){
					continue;
				}

				$sectionSelector = new XMLElement('div',null,array('class'=>'section-selector'));

				$label = Widget::Label();
				$input = Widget::Select("settings[group_lock][section_{$section->get('id')}_field_id]", $sectionAssociations[$section->get('id')]);
				$label->setValue( $section->get('name') . ' ' . __('Field') . $input->generate());

				$sectionSelector->appendChild($label);
				$div->appendChild($sectionSelector);
			}
			
			$group->appendChild($div);
		}
		
		$label = Widget::Label();
		$input = Widget::Input('settings[group_lock][manager]', 'yes', 'checkbox');

		if (Symphony::Configuration()->get('manager', 'group_lock') === 'yes') {
			$input->setAttribute('checked', 'checked');
		}

		$label->setValue($input->generate() . ' ' . __('Managers are only allowed to view users within their attributed groups'));
		$group->appendChild($label);

		// Append preferences
		$context['wrapper']->appendChild($group);
	}

	//modify the navigation menu before rendering.
	public function navigationPreRender($context) {
		return;
		foreach ($context['navigation'] as $key => $value) {
			if ($value['children'][0]['section']['handle'] == 'general-info'){
				//if general info link directly to group info - if it does not exist create a new general info entry

				$groupID = $this->getCurrentGroup();
				$fieldID = '60';
				$joins .= " LEFT JOIN `tbl_entries_data_{$fieldID}` AS `group_lock_{$fieldID}` ON (`e`.`id` = `group_lock_{$fieldID}`.entry_id)";
				$where .= " AND (`group_lock_{$fieldID}`.relation_id = ('{$groupID}') )";
				$entry = current(EntryManager::fetch(null,12,1,0,$where,$joins));

				if($entry){
					$context['navigation'][$key]['children'][0]['link'] .= 'edit/'. $entry->get('id') .'/';
				} else {
					$context['navigation'][$key]['children'][0]['link'] .= 'new/';
				}
			}
			if ($value['children'][0]['section']['handle'] == 'groups' && ! ($this->superseedsPermissions())){
				//if not a developer or manager hide the groups section from the navigation
				unset($context['navigation'][$key]);
			}
		}
	}

	// check if the user has the necessary permissions to view this group
	public function checkFrontendPagePermissions($context) {

		return;

		$isLoggedIn = false;
		$errors = array();

		// Checks $_REQUEST to see if a Member Action has been requested,
		// member-action['login'] and member-action['logout']/?member-action=logout
		// are the only two supported at this stage.
		if(is_array($_REQUEST['member-action'])){
			list($action) = array_keys($_REQUEST['member-action']);
		} else {
			$action = $_REQUEST['member-action'];
		}

		$env = $context['page']->Env();

		if (is_array($env['url']) && !array_key_exists('group', $env['url']))
			return;

		$groupHandle = $env['url']['group'];


		try{
			if (!$groupHandle) 
				// no group handle provided
				throw new FrontendPageNotFoundException;
		} catch (FrontendPageNotFoundException $e) {
		    // Work around. This ensures the 404 page is displayed and
			// is not picked up by the default catch() statement below
			FrontendPageNotFoundExceptionHandler::render($e);
		} 

		$section_id = (string)Symphony::Configuration()->get('section_id', 'group_lock');
		$fieldID = $this->getMainSectionPrimaryFieldId();

		$joins .= " LEFT JOIN `tbl_entries_data_{$fieldID}` AS `group_lock_{$fieldID}` ON (`e`.`id` = `group_lock_{$fieldID}`.entry_id)";
		$where .= " AND (`group_lock_{$fieldID}`.handle = ('{$groupHandle}') )";

		$groupEntry = current(EntryManager::fetch(null,$section_id,1,0,$where,$joins));

		try{
			if (!$groupEntry) 
				//group not found / incorrect
				throw new FrontendPageNotFoundException;
		} catch (FrontendPageNotFoundException $e) {
		    // Work around. This ensures the 404 page is displayed and
			// is not picked up by the default catch() statement below
			FrontendPageNotFoundExceptionHandler::render($e);
		} 


		// Check if user is already set to view the group
		$currentGroupID = $groupEntry->get('id');

		//automatically logged in if the cooke group matches the group in the param
		$this->canAccess = $currentGroupID == $this->getCurrentGroup();

		// Logout
		if(trim($action) == 'logout') {
			$this->setCurrentGroup(null);
		}
			
		// Login
		else if(trim($action) == 'login' && !is_null($_POST['fields'])) {

			if($this->login($_POST['fields'],$currentGroupID)) {
				$this->canAccess = true;
			}
			else {
				$this->_failed_login_attempt = true;
			}
		}

		if (Symphony::Author() instanceof Author){
			$id_groups = Symphony::Database()->fetchCol('id_group', 'SELECT `id_group` FROM `tbl_group_lock_authors` WHERE `id_author` = '. Symphony::Author()->get('id') .';');
		
			// If author has access to group let him access
			if ( $id_groups && in_array($currentGroupID, $id_groups))
				$this->canAccess = true;

			// If Manager / Developer is logged in, return, as Developers / Managers
			// should be able to access every page.
			if( $this->superseedsPermissions()) 
				$this->canAccess = true;
		}

		if ($this->canAccess){
			$this->setCurrentGroup($currentGroupID);
			return;
		}

		$protected = in_array('protected', $context['page_data']['type']);;
		// if page type == protected then 403

		if($protected) {

			// User has no access to this page, so look for a custom 403 page
			if($row = PageManager::fetchPageByType('403')) {
				$row['type'] = PageManager::fetchPageTypes($row['id']);
				$row['filelocation'] = PageManager::resolvePageFileLocation($row['path'], $row['handle']);

				$context['page_data'] = $row;
				return;
			}
			else {
				// No custom 403, just throw default 403
				Frontend::instance()->throwCustomError(
                    __('You do not have access to view this page.'),
                    __('Forbidden'),
                    Page::HTTP_STATUS_FORBIDDEN
                );
			}
		}
	}

	//if user has access output the group id to be used for filtering
	public function addMemberDetailsToPageParams(array $context = null) {
		if(!$this->canAccess) return;

		$context['params']['can-access-group'] = 'true';
		$context['params']['group-id'] = $this->getCurrentGroup();
	}

	//add login details in xml to make it easier to know if a user is logged in or not will be used to show the login interface
	public function appendLoginStatusToEventXML(array $context = null) {
		$result = new XMLElement('group-access-info');

		if($this->canAccess){
			$result->setAttribute('logged-in','yes');
		} else {
			$result->setAttribute('logged-in','no');
		}

		if ($this->_failed_login_attempt){
			$result->appendChild(new XMLElement('error','Login and/or Password Incorrect'));
		}

		$context['wrapper']->appendChild($result);
	}

	// create a new symphony cookie if one doesn't already exist
	public function initialiseCookie() {
		if(is_null($this->cookie)) {
			$this->cookie = new Cookie(
				'group-filter', TWO_WEEKS, __SYM_COOKIE_PATH__, null, true
			);
		}
	}

	// function to validate a login - plain text user/password for read-only access on entries
	private function login($fields,$groupID = null){

		if ($groupID){
			$group = current(EntryManager::fetch($groupID));
			if ($group->getData('63')['value'] == $fields['login'] && $group->getData('64')['value'] == $fields['password'] ){
				$this->setCurrentGroup($groupID);
				return true;
			}
		}

		return false;
	}

	// choose the current group which is being viewed. Uses cookies if none set fetches the first one available for the user
	private function getCurrentGroup(){
		$this->initialiseCookie();

		$group = $this->cookie->get('group');
		// var_dump($group);die;
		if ((!isset($group) || empty($group)) && is_object(Symphony::Author())){

			$group = Symphony::Database()->fetchVar('id_group',0,'SELECT id_group FROM `tbl_group_lock_authors` WHERE `id_author` = '.Symphony::Author()->get('id').';');
			
			if (empty($group) && $this->superseedsPermissions()){
				$groupEntry = EntryManager::fetch(null,(string)Symphony::Configuration()->get('section_id', 'group_lock'),1,0);
				if (!empty($groupEntry)){
					$groupEntry = current($groupEntry);
					$group = $groupEntry->get('id');}
				}

			$this->setCurrentGroup($group);
		}


		return $group;
	}

	//sets the current group
	private function setCurrentGroup($id){
		$this->initialiseCookie();
		if ($id == 'all' && !$this->superseedsPermissions()){
			// this user is not allowed to view all so deny this option
			return;
		}
		$this->cookie->set('group',$id);
	}


	/**
	 * Sets the group fields to be automatically pre-populated with the current group
	 */
	public function adminPagePostCallback($context) {
		if ($context['callback']['driver'] == 'publish' && in_array($context['callback']['context']['page'], array('new','edit')) ){
			//used to pre-populate group data
			
			$sectionID = SectionManager::fetchIDFromHandle($context['callback']['context']['section_handle']);

			$fieldID = (string)Symphony::Configuration()->get('section_'.$sectionID.'_field_id', 'group_lock');

			if ($fieldID){
				$fieldRelatedTo = current(FieldManager::fetch(FieldManager::fetch($fieldID)->get('related_field_id')));
				$parentSection = $fieldRelatedTo->get('parent_section');
				$sectionID = (string)Symphony::Configuration()->get('section_id', 'group_lock');

				if ($parentSection == $sectionID){
					$_REQUEST['prepopulate'][$fieldID] = $this->getCurrentGroup();
				}
			}

			/*
			//allow entries with no groups
			if ($groupLessSection && ($this->superseedsPermissions()) && $context['callback']['context']['page'] == 'edit'){
				//as categories can have no group show the group field when editing an existing item for devleopers / managers
				unset($_REQUEST['prepopulate'][$fieldID]);
			}*/
		}
	}


	/**
	 * adjust all the filters to use the current group
	 */
	public function adjustPublishFiltering($context) {
		//if author has filtering and section has a 'group' field then filter by group id

		$groupID = $this->getCurrentGroup();

		$sectionID = (string)Symphony::Configuration()->get('section_id', 'group_lock');

		if ($groupID && $groupID !=='all'){

			if ($context['section-id'] == $sectionID && !($this->superseedsPermissions() ) ){

				$editLink = Symphony::Engine()->getCurrentPageURL() . 'edit/' . $groupID . '/';

				//redirect to group edit page or throw error?
				header("Location: {$editLink}",TRUE,302);
				die;
			}

			//fetch field of name group within section if available
			$fieldID = (string)Symphony::Configuration()->get('section_'.$context['section-id'].'_field_id', 'group_lock');

			if ($fieldID){
				$fieldRelatedTo = current(FieldManager::fetch(FieldManager::fetch($fieldID)->get('related_field_id')));
				$parentSection = $fieldRelatedTo->get('parent_section');


				if ($parentSection == $sectionID){

					$context['joins'] .= " LEFT JOIN `tbl_entries_data_{$fieldID}` AS `group_lock_{$fieldID}` ON (`e`.`id` = `group_lock_{$fieldID}`.entry_id)";
					$context['where'] .= " AND (`group_lock_{$fieldID}`.relation_id IN ('{$groupID}') )";

				} else{

					$linkedFieldId = (string)Symphony::Configuration()->get('section_'.$parentSection.'_field_id', 'group_lock');

					$context['joins'] .= " LEFT JOIN `tbl_entries_data_{$fieldID}` AS `group_lock_{$fieldID}` ON (`e`.`id` = `group_lock_{$fieldID}`.entry_id)";
					$context['joins'] .= " LEFT JOIN `tbl_entries_data_{$linkedFieldId}` AS `group_lock_{$linkedFieldId}` ON (`group_lock_{$fieldID}`.relation_id = `group_lock_{$linkedFieldId}`.entry_id)";
					$context['where'] .= " AND (`group_lock_{$linkedFieldId}`.relation_id IN ('{$groupID}') )";

				}

			}
		}
	}

	/**
	 * adjust all the filters to use the current group
	 */
	public function adjustAuthorFiltering($context) {
		//if author has filtering and section has a 'group' field then filter by group id

		if (!isset($context['where'])){
			$context['where'] = '';
		}
		if (!isset($context['join'])){
			$context['join'] = '';
		}

		$groupID = $this->getCurrentGroup();

		if ($groupID && $groupID !=='all'){
	        $context['where'] = " `tbl_group_lock_authors`.id_group in ({$groupID})";
	        $context['joins'] = " LEFT JOIN `tbl_group_lock_authors` on `tbl_group_lock_authors`.id_author = a.id ";
		}
	}

	/**
	 * Filter selectbox choices - this ensures that users do not see entries form other groups when selecting items
	 */
	public function associationFiltering($context) {
		//if author has filtering and section has a 'group' field then filter by group id

		$groupID = $this->getCurrentGroup();

		if ($groupID && $groupID !=='all'){

			if ($context['section-id'] == (string)Symphony::Configuration()->get('section_id', 'group_lock')){

				$context['where'] .= " AND `e`.`id` = '{$groupID}' ";
				return;
			}

			//fetch field of name group within section if available
			$fieldID = (string)Symphony::Configuration()->get('section_'.$context['section-id'].'_field_id', 'group_lock');

			if ($fieldID){

				$context['joins'] .= " LEFT JOIN `tbl_entries_data_{$fieldID}` AS `group_lock_{$fieldID}` ON (`e`.`id` = `group_lock_{$fieldID}`.entry_id)";
				$context['where'] .= " AND (`group_lock_{$fieldID}`.relation_id IN ('{$groupID}') )";

			}
		}
	}

	/**
	 * adjust the entry counts to only include the current group
	 */
	public function associatedEntryCount($context) {
		//if author has filtering and section has a 'group' field then filter by group id

		$groupID = $this->getCurrentGroup();

		if ($groupID && $groupID !=='all'){

			if ($context['section-id'] == (string)Symphony::Configuration()->get('section_id', 'group_lock')){

				$context['joins'] .= " LEFT JOIN `sym_entries` as `e` ON `e`.`id` = `assoc`.`entry_id` ";
				$context['where'] .= " AND `e`.`id` = '{$groupID}' ";
				return;
			}

			//fetch field of name group within section if available
			$fieldID = (string)Symphony::Configuration()->get('section_'.$context['section-id'].'_field_id', 'group_lock');

			if ($fieldID){

				$context['joins'] .= " LEFT JOIN `sym_entries` as `e` ON `e`.`id` = `assoc`.`entry_id` LEFT JOIN `sym_entries_data_{$fieldID}` as `p` ON `e`.`id` = `p`.`entry_id`";
				$context['where'] .= " AND (`p`.relation_id IN ('{$groupID}') )";

			}
		}
	}

	/**
	 * Check that the current user has access prior to showing the entry edit screen
	 */
	public function entryPreRender($context) {

		$groupID = $this->getCurrentGroup();

		if ($groupID && !($this->superseedsPermissions())){

			if ($context['section']->get('id') == (string)Symphony::Configuration()->get('section_id', 'group_lock')){
				if ( $context['entry']->get('id') != $groupID ){
					//throw custom error if not correct entry
					Administration::instance()->throwCustomError(
						__('Unknown Entry'),
						__('The Group, %s, could not be found.', array($context['entry']->get('id'))),
						Page::HTTP_STATUS_NOT_FOUND
					);
				}
			}

			//fetch field of name group within section if available
			$fieldID = FieldManager::fetchFieldIDFromElementName((string)Symphony::Configuration()->get('field_handle', 'group_lock'),$context['section']->get('id'));


			if ($fieldID && $context['entry']->getData($fieldID)['relation_id'] != $groupID){
				Administration::instance()->throwCustomError(
					__('Unknown Entry'),
					__('The Entry, %s, could not be found.', array($context['entry']->get('id'))),
					Page::HTTP_STATUS_NOT_FOUND
				);
			}

		}
	}

	/**
	 * confirm user has rights before saving - in case someone tries to hijack a form
	 */
	public function entryPreEdit($context) {

		$groupID = $this->getCurrentGroup();

		if ($groupID && !($this->superseedsPermissions())){

			if ($context['section']->get('id') == (string)Symphony::Configuration()->get('section_id', 'group_lock')){
				if ( $context['entry']->get('id') != $groupID ){
					//throw custom error if not correct entry
					Administration::instance()->throwCustomError(
						__('Unknown Entry'),
						__('The Group, %s, could not be edited.', array($context['entry']->get('id'))),
						Page::HTTP_STATUS_NOT_FOUND
					);
				}
			}

			//fetch field of name group within section if available
			$fieldID = FieldManager::fetchFieldIDFromElementName((string)Symphony::Configuration()->get('field_handle', 'group_lock'),$context['section']->get('id'));


			if ($fieldID && $context['entry']->getData($fieldID)['relation_id'] != $groupID){
				Administration::instance()->throwCustomError(
					__('Unknown Entry'),
					__('The Entry, %s, could not be edited.', array($context['entry']->get('id'))),
					Page::HTTP_STATUS_NOT_FOUND
				);
			}

		}
	}

	/**
	 * ensure that a user has rights to create entries for the current group - in case of attemted hijacking
	 */
	public function entryPreCreate($context) {

		var_dump($context);die;

		$groupID = $this->getCurrentGroup();

		if ($groupID && !($this->superseedsPermissions())){

			if ($context['section']->get('id') == (string)Symphony::Configuration()->get('section_id', 'group_lock')){

				//should not be allowed to create new groups ??
				Administration::instance()->throwCustomError(
					__('Unknown Entry'),
					__('You do not have permission to create new groups', array($context['entry']->get('id'))),
					Page::HTTP_STATUS_NOT_FOUND
				);
			}

			//fetch field of name group within section if available
			$fieldID = FieldManager::fetchFieldIDFromElementName((string)Symphony::Configuration()->get('field_handle', 'group_lock'),$context['section']->get('id'));


			if ($fieldID && $context['entry']->getData($fieldID)['relation_id'] != $groupID){
				//TODO Replace with a more user friendly error
				Administration::instance()->throwCustomError(
					__('Unknown Entry'),
					__('There was a problem setting a group for your entry.', array($context['entry']->get('id'))),
					Page::HTTP_STATUS_NOT_FOUND
				);
			}

		}
	}

	
	/**
	 * initialise the admin and add the necessary css and scripts for the backend to work as expected - including showing the group dropdown and group links in top left.
	 */
	public function initializeAdmin($context) {
		// $LOAD_NUMBER = 935935211;

		if (!empty($_REQUEST['current-group'])){
			if ($_REQUEST['current-group'] !== 'all'){
				$id_group = Symphony::Database()->fetchVar('id_group',0, 'SELECT `id_group` FROM `tbl_group_lock_authors` WHERE `id_author` = '. Symphony::Author()->get('id') .' AND `id_group` = ' . Symphony::Database()->cleanValue($_REQUEST['current-group']) . ';');
			} else {
				//TODO confirm user has access to 'all'
				$id_group = 'all';
			}
			$this->setCurrentGroup($id_group);
		}

		// If developer and manager - give access to groups even if not assigned. To confirm that this is correct
		if (is_null($id_group) && !empty($_REQUEST['current-group']) && ($this->superseedsPermissions())){
			$this->setCurrentGroup(Symphony::Database()->cleanValue($_REQUEST['current-group']));
		}

		// See which group this user has:
		$id_groups = Symphony::Database()->fetchCol('id_group', 'SELECT `id_group` FROM `tbl_group_lock_authors` WHERE `id_author` = '. Symphony::Author()->get('id') .';');

		if (empty($id_groups) && $this->superseedsPermissions()){
			$id_groups = null;
		}

		$groups = EntryManager::fetch($id_groups,(string)Symphony::Configuration()->get('section_id', 'group_lock'));

		if (!$groups){
			// no groups
			return;
		}

		$currentGroupID = $this->getCurrentGroup();

		if ($currentGroupID == 'all'){
			$selectedGroup = array('value'=>'All','handle'=>'all');
		} else if (!empty($currentGroupID)){
			$selectedGroup = current(array_filter($groups,function($group) use ($currentGroupID){
				return $group->get('id') == $currentGroupID;
			}));
			$selectedGroup = array('value' => $selectedGroup->getData('{$fieldID}')['value'], 'handle' => $selectedGroup->getData('{$fieldID}')['handle']);
		}

		if( empty($selectedGroup)){
			$this->cookie->set('group',null);

			//try get the current group again
			$currentGroupID = $this->getCurrentGroup();

			if (!empty($currentGroupID)){
				$selectedGroup = current(array_filter($groups,function($group) use ($currentGroupID){
					return $group->get('id') == $currentGroupID;
				}));
				$selectedGroup = array('value' => $selectedGroup->getData('{$fieldID}')['value'], 'handle' => $selectedGroup->getData('{$fieldID}')['handle']);
			}
		}

		$fieldID = $this->getMainSectionPrimaryFieldId();

		//if only one group no need to show a dropdown
		if (sizeof($groups) <= 1){
			$script = "jQuery(document).ready(function(){ 
				jQuery('h1 a').text('{$selectedGroup['handle']}');
				jQuery('h1 a').attr('href',jQuery('h1 a').attr('href') + 'view/' + '{$selectedGroup['handle']}' + '/');
				if (window.location.pathname == Symphony.Context.get('symphony') + '/publish/general-info/'){
					var link = $('#nav a').filter(function(index) { return $(this).text() === 'General Info'; });
					window.location = link.attr('href');
				}
			 });";
			Administration::instance()->Page->addElementToHead(
				new XMLElement('script', $script, array(
					'type' => 'text/javascript'
				))
			);
			return;
		}

		if($this->superseedsPermissions()){
			$options[] = array('all', 'all' == $currentGroupID , 'All');
		}
		foreach($groups as $group) {
			$options[] = array($group->get('id'), $group->get('id') == $currentGroupID , $group->getData($fieldID)['value']);
		}

		//if this is an entry change link to a section
		$actionURL = '';
		$pageContext = Administration::instance()->Page->getContext();
		if(isset($pageContext['page']) && $pageContext['page']!='index'){
			$actionURL = SYMPHONY_URL . '/publish/' . $pageContext['section_handle'] .'/';
		}

		$form = Widget::Form($actionURL,'post','group-form','group-form');

		$form->appendChild(Widget::Select('current-group', $options, array('onchange'=>'this.form.submit()')));
		$form->appendChild(XSRF::formToken());

		$script = "jQuery(document).ready(function(){ 
			jQuery('#nav').append('{$form->generate()}');
			jQuery('h1 a').text('{$selectedGroup['handle']}');
			jQuery('h1 a').attr('href',jQuery('h1 a').attr('href') + 'view/' + '{$selectedGroup['handle']}' + '/');
			if (window.location.pathname == Symphony.Context.get('symphony') + '/publish/general-info/'){
				var link = $('#nav a').filter(function(index) { return $(this).text() === 'General Info'; });
				window.location = link.attr('href');
			}
		 });";
		$style = " .group-form{
						float: right !important;
						margin-right: 10px !important;
						margin-top: 5px !important;
					}";

		Administration::instance()->Page->addElementToHead(
			new XMLElement('script', $script, array(
				'type' => 'text/javascript'
			))
		);

		Administration::instance()->Page->addElementToHead(
			new XMLElement('style', $style, array(
				'type' => 'text/css'
			))
		);
	}


	/**
	 * Add the group field to the author-form:
	 * @param	$context
	 *  The context, providing the form and the author object
	 */
	public function addGroupPicker($context) {

		$id_groups = Symphony::Database()->fetchCol('id_group', 'SELECT `id_group` FROM `tbl_group_lock_authors` WHERE `id_author` = '. Symphony::Author()->get('id') .';');

		if($this->superseedsPermissions() || (Symphony::Author()->isManager() && sizeof($id_groups) !== 1)) {
			$container = new XMLElement('fieldset');
			$container->setAttribute('class', 'settings');
			$container->appendChild(new XMLElement('legend', __('Author Groups')));

			$div = new XMLElement('div');

			$label = Widget::Label(__('This author belongs to'));

			$options = array(
				array(0, false, __('No group assigned'))
			);

			if ($this->superseedsPermissions()){
				$id_groups = null;
			}

			$groups = EntryManager::fetch($id_groups,(string)Symphony::Configuration()->get('section_id', 'group_lock'));

			$author_id = $context['author']->get('id');
			if (!empty($author_id)){
				// See which group this user has:
				$id_groups = $author_id != false ? Symphony::Database()->fetchCol('id_group', 'SELECT `id_group` FROM `tbl_group_lock_authors` WHERE `id_author` = '.$author_id.';') : 0;
			} else {
				$id_groups = array();
			}

			$fieldID = $this->getMainSectionPrimaryFieldId();

			foreach($groups as $group) {
				$options[] = array($group->get('id'), in_array($group->get('id'), $id_groups), $group->getData($fieldID)['value']);
			}

			$label->appendChild(Widget::Select('fields[group][]', $options, array('multiple'=>'multiple')));

			$div->appendChild($label);
			$div->appendChild(new XMLElement('p', __('<strong>Please note:</strong> When selecting a group, the author can only view entries related to that particular group.')));
			$container->appendChild($div);
		} else {
			$pageContext = Symphony::Engine()->getPageCallback()['context'];
			if ($pageContext[0] == 'new'){
				$container = Widget::Input('fields[group][]', current($id_groups), 'hidden');
			}
		}

		if ($container){
			$i = 0;
	
			foreach($context['form']->getChildren() as $formChild) {
				if($formChild->getName() != 'fieldset') {
					// Inject here:
					$context['form']->insertChildAt($i, $container);
				}
	
				$i++;
			}
		}
	}

	/**
	 * Save the groups to this author. This is send after the save-button is clicked on the author-screen.
	 * @param	$context
	 *  The context
	 */
	public function saveAuthorGroup($context) {
		if($this->superseedsPermissions()) {

			$id_groups = $_POST['fields']['group'];
			$id_author = $context['author']->get('id');

			if($id_author == null) {
				// This author has just been created, get the ID of this newly created author:
				$id_author = Symphony::Database()->fetchVar('id', 0, 'SELECT `id` FROM `tbl_authors` WHERE `username` = \''.$context['author']->get('username').'\';');
			}

			// Delete previously set roles:
			$this->deleteAuthorGroup($id_author);

			if(!empty($id_groups) && !(sizeof($id_groups) == 1 && $id_groups[0] == 0 )) {

				if (!is_array($id_groups)) $id_groups = array($id_groups);

				$insertData = array();
				foreach ($id_groups as $key => $id_group) {
					$insertData[] = array('id_group'=>intval($id_group), 'id_author'=>$id_author);
				}

				// Insert new role:
				Symphony::Database()->insert($insertData, 'tbl_group_lock_authors');
			}
		}
	}


	/**
	 * Delete the group links to this author
	 * @param	$context
	 *  Can be a Symphony Context object, an id of an author, or an array with author-id's
	 */
	public function deleteAuthorGroup($context) {
		// When a new author is created $context is false:
		if($context == false) {
			return;
		}

		// When a bulk action delete is performed the ID's are stored in this manner:
		if(isset($context['author_ids'])) {
			$context = $context['author_ids'];
		}

		// When 'Delete' is clicked in the author-edit screen:
		if(isset($context['author_id'])) {
			$context = array($context['author_id']);
		}

		// When a single ID is provided:
		if(!is_array($context)) {
			$context = array($context);
		}

		// Delete the links:
		foreach($context as $id_author) {
			Symphony::Database()->query('DELETE FROM `tbl_group_lock_authors` WHERE `id_author` = '.$id_author.';');
		}
	}


	/**
	 * Installation
	 */
	public function install() {

		// Roles <-> Authors
		Symphony::Database()->query("
			CREATE TABLE IF NOT EXISTS `tbl_group_lock_authors` (
				`id` INT(11) unsigned NOT NULL auto_increment,
				`id_group` INT(255) unsigned NOT NULL,
				`id_author` INT(255) unsigned NOT NULL,
				PRIMARY KEY (`id`),
				KEY `id_group` (`id_group`),
				KEY `id_author` (`id_author`)
			);
		");
	}

	/**
	 * Uninstallation
	 */
	public function uninstall() {
		// Drop all the tables:
		Symphony::Database()->query("DROP TABLE `tbl_group_lock_authors`");
	}
  
}  
?>