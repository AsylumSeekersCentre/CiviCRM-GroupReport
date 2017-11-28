<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2017
 */
class CRM_Groupreport_Form_Report_ContactSummaryGroup extends CRM_Report_Form {

  public $_summary = NULL;

  protected $_emailField = FALSE;

  protected $_phoneField = FALSE;

  protected $_groupField = FALSE;

  protected $_smartGroupField = FALSE;

  protected $_customGroupExtends = array(
    'Contact',
    'Individual',
    'Household',
    'Organization',
  );

  public $_drilldownReport = array('contact/detail' => 'Link to Detail Report');

  /**
   * This report has not been optimised for group filtering.
   *
   * The functionality for group filtering has been improved but not
   * all reports have been adjusted to take care of it. This report has not
   * and will run an inefficient query until fixed.
   *
   * CRM-19170
   *
   * @var bool
   */
  protected $groupFilterNotOptimised = TRUE;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->_autoIncludeIndexedFieldsAsOrderBys = 1;
    $this->_columns = array(
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array_merge(
          $this->getBasicContactFields(),
          array(
            'modified_date' => array(
              'title' => ts('Modified Date'),
              'default' => FALSE,
            ),
          )
        ),
        'filters' => $this->getBasicContactFilters(),
        'grouping' => 'contact-fields',
        'order_bys' => array(
          'sort_name' => array(
            'title' => ts('Last Name, First Name'),
            'default' => '1',
            'default_weight' => '0',
            'default_order' => 'ASC',
          ),
          'first_name' => array(
            'name' => 'first_name',
            'title' => ts('First Name'),
          ),
          'gender_id' => array(
            'name' => 'gender_id',
            'title' => ts('Gender'),
          ),
          'birth_date' => array(
            'name' => 'birth_date',
            'title' => ts('Birth Date'),
          ),
          'contact_type' => array(
            'title' => ts('Contact Type'),
          ),
          'contact_sub_type' => array(
            'title' => ts('Contact Subtype'),
          ),
        ),
      ),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => array(
          'email' => array(
            'title' => ts('Email'),
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
        'order_bys' => array(
          'email' => array(
            'title' => ts('Email'),
          ),
        ),
      ),
      'civicrm_group_contact_cache' => array(
        'bao' => 'CRM_Contact_BAO_GroupContactCache',
        'fields' => array(
          'group_id' => array(
            'title' => ts('SmartGroup'),
    // FIXME this should use the group_contact_cache name found in _aliases
    // However, it doesn't work at this point because it hasn't been set yet.
            'dbAlias' => 'GROUP_CONCAT(civicrm_group_contact_cache.group_id SEPARATOR ",")',
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
        'order_bys' => array(
          'group_id' => array(
            'title' => ts('SmartGroup'),
          ),
        ),
      ),
      'civicrm_group_contact' => array(
        'dao' => 'CRM_Contact_DAO_Group',
        'fields' => array(
          'group_id' => array(
            'title' => ts('Group'),
            'dbAlias' => 'GROUP_CONCAT(group_contact_civireport.group_id SEPARATOR ",")',
            'no_repeat' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
        'order_bys' => array(
          'group_id' => array(
            'title' => ts('Group'),
          ),
        ),
      ),
      'civicrm_phone' => array(
        'dao' => 'CRM_Core_DAO_Phone',
        'fields' => array(
          'phone' => NULL,
          'phone_ext' => array(
            'title' => ts('Phone Extension'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
    ) + $this->getAddressColumns(array('group_by' => FALSE));

    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  public function preProcess() {
    parent::preProcess();
  }

  public function select() {
    $select = array();
    $this->_columnHeaders = array();
    foreach ($this->_columns as $tableName => $table) {
      if (array_key_exists('fields', $table)) {
        foreach ($table['fields'] as $fieldName => $field) {
          if (!empty($field['required']) ||
            !empty($this->_params['fields'][$fieldName])
          ) {
            if ($tableName == 'civicrm_email') {
              $this->_emailField = TRUE;
            }
            elseif ($tableName == 'civicrm_phone') {
              $this->_phoneField = TRUE;
            }
            elseif ($tableName == 'civicrm_group_contact') {
              $this->_groupField = TRUE;
            }
            elseif ($tableName == 'civicrm_group_contact_cache') {
              $this->_smartGroupField = TRUE;
            }
            elseif ($tableName == 'civicrm_country') {
              $this->_countryField = TRUE;
            }

            $alias = "{$tableName}_{$fieldName}";
            $select[] = "{$field['dbAlias']} as {$alias}";
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['type'] = CRM_Utils_Array::value('type', $field);
            $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
            $this->_selectAliases[] = $alias;
          }
        }
      }
    }

    $this->_select = "SELECT " . implode(', ', $select) . " ";
  }

  /**
   * @param $fields
   * @param $files
   * @param $self
   *
   * @return array
   */
  public static function formRule($fields, $files, $self) {
    $errors = $grouping = array();
    return $errors;
  }

  public function from() {
    $this->_from = "
        FROM civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
            LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
                   ON ({$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_address']}.contact_id AND
                      {$this->_aliases['civicrm_address']}.is_primary = 1 ) ";

    if ($this->isTableSelected('civicrm_email')) {
      $this->_from .= "
            LEFT JOIN  civicrm_email {$this->_aliases['civicrm_email']}
                   ON ({$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_email']}.contact_id AND
                      {$this->_aliases['civicrm_email']}.is_primary = 1) ";
    }

    if ($this->_phoneField) {
      $this->_from .= "
            LEFT JOIN civicrm_phone {$this->_aliases['civicrm_phone']}
                   ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_phone']}.contact_id AND
                      {$this->_aliases['civicrm_phone']}.is_primary = 1 ";
    }

    if ($this->_groupField) {
      $this->_from .= "
            LEFT JOIN civicrm_group_contact {$this->_aliases['civicrm_group_contact']}
                   ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_group_contact']}.contact_id";
    }
    /*
    */

    if ($this->_smartGroupField) {
      // FIXME this should use the group_contact_cache name found in _aliases
      // However, it doesn't work.
      $this->_from .= "
            LEFT JOIN civicrm_group_contact_cache
                   ON {$this->_aliases['civicrm_contact']}.id = civicrm_group_contact_cache.contact_id";
    /*
      $this->_from .= "
            LEFT JOIN civicrm_group_contact_cache {$this->_aliases['civicrm_group_contact']}
                   ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_group_contact_cache']}.contact_id";
    */
                   /*
      $this->_from .= "
            LEFT JOIN civicrm_group_contact_cache civicrm_group_contact_cache
                   ON {$this->_aliases['civicrm_contact']}.id = civicrm_group_contact_cache.contact_id";
                   */
    }

    if ($this->isTableSelected('civicrm_country')) {
      $this->_from .= "
            LEFT JOIN civicrm_country {$this->_aliases['civicrm_country']}
                   ON {$this->_aliases['civicrm_address']}.country_id = {$this->_aliases['civicrm_country']}.id AND
                      {$this->_aliases['civicrm_address']}.is_primary = 1 ";
    }
  }

  public function postProcess() {

    $this->beginPostProcess();

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact']);

    $sql = $this->buildQuery(TRUE);

    $rows = $graphRows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }


  public function groupBy() {
    $this->_groupBy = " ";
    $groupBy = array();
      $groupBy[] = " {$this->_aliases['civicrm_contact']}.id";
    $this->_groupBy = CRM_Contact_BAO_Query::getGroupByFromSelectColumns($this->_selectClauses, $groupBy);
  }

  public function getGroupTitle($groupId) {
//    CRM_Core_Session::setStatus('Getting groupId  '.(json_encode($groupId)), 'Success', 'no-popup');
/*
*/
    $result = civicrm_api3('Group', 'get', array(
      'sequential' => 1,
      'id' => $groupId,
    ));
//    $result = civicrm_api3_group_get(['group_id' => $groupId]);
//    CRM_Core_Session::setStatus('title = '.(json_encode($result)), 'Success', 'no-popup');
    return $result['values'][0]['title'];
  }

  public function getGroupTitles($groupIdsString) {
    $groupIds = explode(',', $groupIdsString);

    $groupTitles = array();
    foreach ($groupIds as $groupId) {
        $groupTitles[] = $this->getGroupTitle($groupId);
    };
    return $groupTitles;
  }

  public function alterDisplayGroups(&$row) {
    $groupTitles = $this->getGroupTitles($row['civicrm_group_contact_group_id']);
    $row['civicrm_group_contact_group_id'] = $groupTitles;

//    CRM_Core_Session::setStatus('smartGroups = '.(json_encode($row['civicrm_group_contact_cache_group_id'])), 'Success', 'no-popup');
    $groupTitles = $this->getGroupTitles($row['civicrm_group_contact_cache_group_id']);
//    CRM_Core_Session::setStatus('groupTitles= '.(json_encode($groupTitles)), 'Success', 'no-popup');
    $row['civicrm_group_contact_cache_group_id'] = $groupTitles;
    return TRUE;
  }


  /**
   * Alter display of rows.
   *
   * Iterate through the rows retrieved via SQL and make changes for display purposes,
   * such as rendering contacts as links.
   *
   * @param array $rows
   *   Rows generated by SQL, with an array for each row.
   */
  public function alterDisplay(&$rows) {
    $entryFound = FALSE;

    foreach ($rows as $rowNum => $row) {
      // make count columns point to detail report
      // convert sort name to links
      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Report_Utils_Report::getNextUrl('contact/detail',
          'reset=1&force=1&id_op=eq&id_value=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl, $this->_id, $this->_drilldownReport
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = ts('View Contact Detail Report for this contact');
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_country_id', $row)) {
        if ($value = $row['civicrm_address_country_id']) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($value, FALSE);
        }
        $entryFound = TRUE;
      }

      // Handle ID to label conversion for contact fields
      $entryFound = $this->alterDisplayContactFields($row, $rows, $rowNum, 'contact/summary', 'View Contact Summary') ? TRUE : $entryFound;

      // display birthday in the configured custom format
      if (array_key_exists('civicrm_contact_birth_date', $row)) {
        $birthDate = $row['civicrm_contact_birth_date'];
        if ($birthDate) {
          $rows[$rowNum]['civicrm_contact_birth_date'] = CRM_Utils_Date::customFormat($birthDate, '%Y%m%d');
        }
        $entryFound = TRUE;
      }

      if ($this->alterDisplayGroups($row)) {
        $rows[$rowNum]['civicrm_group_contact_group_id'] = implode(', &nbsp;', $row['civicrm_group_contact_group_id']);
        $rows[$rowNum]['civicrm_group_contact_cache_group_id'] = implode(', &nbsp;', $row['civicrm_group_contact_cache_group_id']);
        $entryFound = TRUE;
      }

      // skip looking further in rows, if first row itself doesn't
      // have the column we need
      if (!$entryFound) {
        break;
      }
    }
  }

}
