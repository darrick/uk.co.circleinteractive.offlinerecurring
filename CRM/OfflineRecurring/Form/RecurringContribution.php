<?php
/*
 * CiviCRM Offline Recurring Payment Extension for CiviCRM - Circle Interactive 2013
 * Original author: rajesh
 * http://sourceforge.net/projects/civicrmoffline/
 * Converted to Civi extension by andyw@circle, 07/01/2013
 *
 * Distributed under the GNU Affero General Public License, version 3
 * http://www.gnu.org/licenses/agpl-3.0.html
 *
 */
class CRM_OfflineRecurring_Form_RecurringContribution extends CRM_Core_Form {
  /**
   * The id of the contribution that we are processing.
   *
   * @var int
   */
  public $_id;
  /**
  * The id of the contact associated with this contribution.
  *
  * @var int
  */
  public $_contactID;

  /**
  * The contribution recur values if an existing contribution recur
  */
  public $_values = [];

  /**
  * build all the data structures needed to build the form
  *
  * @return void
  * @access public
  */
  public function preProcess() {
    parent::preProcess();
    if (($this->_action & CRM_Core_Action::UPDATE)
      && !CRM_Core_Permission::check('edit offline recurring payments')
    ) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page.'));
    }
    elseif (($this->_action & CRM_Core_Action::ADD)
      && !CRM_Core_Permission::check('add offline recurring payments')
    ) {
      CRM_Core_Error::fatal(ts('You do not have permission to access this page.'));
    }

    $this->_contactID = CRM_Utils_Request::retrieve('cid', 'Integer', $this);
    $this->_id = CRM_Utils_Request::retrieve('crid', 'Integer', $this);
    try {
      $displayName = civicrm_api3('contact', 'getvalue', [
        'id' => $this->_contactID,
        'return' => 'display_name',
      ]);
      if ($this->_id) {
        $this->_values = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $this->_id]);
        if (CRM_Utils_Array::value('enable_edit', $this->_values) === FALSE) {
          CRM_Core_Error::fatal(ts('You are not allowed to edit the recurring record.'));
        }
      }
    }
    catch (CiviCRM_API3_Exception $e) {
      CRM_Core_Error::fatal(ts("Contact or Contribution Recur doesn't exists."));
    }
    CRM_Utils_System::setTitle(ts('Setup Recurring Payment - ') . $displayName);
  }

  /**
   * Set default values.
   *
   * @return array
   */
  public function setDefaultValues() {
    $defaults = $this->_values;
    $defaults['recur_id'] = $this->_id;
  }

  /**
  * Build the form
  *
  * @access public
  * @return void
  */
  public function buildQuickForm() {
    $attributes = CRM_Core_DAO::getAttribute('CRM_Contribute_DAO_ContributionRecur');
    if ($this->_action & CRM_Core_Action::UPDATE) {
      $this->addElement('hidden', 'recur_id');
      $this->addEntityRef(
        'contact_id',
        ts('Contact'),
        ['create' => TRUE, 'api' => ['extra' => ['email']]],
        TRUE
      );
      $this->addElement('checkbox', 'move_recurring_record', ts('Move Recurring Record?'));
      $this->addElement('checkbox', 'move_existing_contributions', ts('Move Existing Contributions?'));
      // Check if membership is linked with the recur record and allowed to be moved to different membership
      // NOTE: 'membership_id' is not in 'civicrm_contribution_recur' table by default
      if(CRM_Core_DAO::checkFieldExists('civicrm_contribution_recur', 'membership_id')) {
        // Get memberships of the contact
        // This will allow the recur record to be attached to a different membership of the same contact
        $memberships = civicrm_api3(
          'membership',
          'get',
          ['contact_id' => $cid]
        );
        if (!empty($memberships['values'])) {
          $existingMemberships = ['0' => '- select -'];
          foreach ($memberships['values'] as $membership_id => $membership_details) {
            // Replace with GetLabel
            $membershipStatus = CRM_Core_PseudoConstant::getLabel(
              'CRM_Membership_BAO_Membership',
              'status_id',
              $membership_details['status_id']
            );
            $existingMemberships[$membership_id] = $membership_details['membership_name']
            .' / '. $membershipStatus
            .' / '. $membership_details['start_date']
            .' / '. $membership_details['end_date'];
          }
          $this->add('select', 'membership_record', ts('Membership'), $existingMemberships);
        }
      }
    }
    $this->addMoney('amount',
      ts('Amount'),
      TRUE,
      $attributes['amount'],
      TRUE, 'currency', NULL, TRUE
    );
    $this->_values['is_recur_interval'] = 1;
    $this->_values['recur_frequency_unit'] = implode(
      CRM_Core_DAO::VALUE_SEPARATOR,
      CRM_Core_OptionGroup::values('recur_frequency_units')
    );

    CRM_Contribute_Form_Contribution_Main::buildRecur($this);
    $this->addDate('start_date', ts('Start Date'), TRUE, ['formatType' => 'activityDate']);
    $this->addDate('next_sched_contribution', ts('Next Scheduled Date'), TRUE, ['formatType' => 'activityDate']);
    $this->addDate('end_date', ts('End Date'), FALSE, ['formatType' => 'activityDate']);
    $this->addButtons([
      [
        'type' => 'next',
        'name' => ts('Save'),
        'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
        'spacing' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',
      ]
    ]);
    $this->addFormRule(['CRM_OfflineRecurring_Form_RecurringContribution', 'formRule'], $this);
  }

  /**
  * global validation rules for the form
  *
  * @param array $fields posted values of the form
  *
  * @return array list of errors to be posted back to the form
  * @static
  * @access public
  */
  public static function formRule($fields, $files, $self) {
    $errors = [];
    if (!empty($values['start_date']) && !empty($values['end_date'])) {
      $start = CRM_Utils_Date::processDate($values['start_date']);
      $end = CRM_Utils_Date::processDate($values['end_date']);
      if (($end < $start) && ($end != 0)) {
        $errors['end_date'] = ts('End date should be after Start date');
      }
    }
    return $errors;
  }

  /**
  * process the form after the input has been submitted and validated
  *
  * @access public
  * @return None
  */
  public function postProcess() {

    $config = CRM_Core_Config::singleton();
    $params = $this->controller->exportValues();
    $params['recur_id'] = $this->_submitValues['recur_id'];

        if(!empty($params['start_date']))
            $start_date = CRM_Utils_Date::processDate($params['start_date']);
        if(!empty($params['end_date']))
            $end_date = CRM_Utils_Date::processDate($params['end_date']);
        if(!empty($params['next_sched_contribution']))
            $next_sched_contribution = CRM_Utils_Date::processDate($params['next_sched_contribution']);

        if ($params['action'] == 'add') {

            $fields = "id, contact_id, amount, frequency_interval, frequency_unit, invoice_id, trxn_id, currency, create_date, start_date, next_sched_contribution";
            if (_offlinerecurring_getCRMVersion() >= 4.4)
                $fields .= '_date';

            $values       = "NULL, %1, %2, %3, %4, %5, %6, %7, %8, %9, %10";
            $invoice_id   = md5(uniqid(rand(), TRUE));

            $recur_params = array(
                1 =>  array($params['cid'],                'Integer'),
                2 =>  array($params['amount'],             'String'),
                3 =>  array($params['frequency_interval'], 'String'),
                4 =>  array($params['frequency_unit'],     'String'),
                5 =>  array($invoice_id,                   'String'),
                6 =>  array($invoice_id,                   'String'),
                7 =>  array($config->defaultCurrency,      'String'),
                8 =>  array(date('YmdHis'),                'String'),
                9 =>  array($start_date,                   'String'),
                10 => array($next_sched_contribution,      'String')
            );

            if (isset($end_date)) {
                $fields          .= ", end_date";
                $values          .= ", %11";
                $recur_params[11] = array($end_date, 'String');
            }

            $sql    = sprintf("INSERT INTO civicrm_contribution_recur (%s) VALUES (%s)", $fields, $values);
            $status = ts('Recurring Contribution setup successfully');

        } elseif ($params['action'] == 'update') {

            $sql = "UPDATE civicrm_contribution_recur SET amount = %1, frequency_interval = %2, frequency_unit = %3, start_date = %4, next_sched_contribution = %5, modified_date = %6";
            if (_offlinerecurring_getCRMVersion() >= 4.4)
                $sql = str_replace('next_sched_contribution', 'next_sched_contribution_date', $sql);

            $recur_params = array(
                1 =>  array($params['amount'],             'String'),
                2 =>  array($params['frequency_interval'], 'String'),
                3 =>  array($params['frequency_unit'],     'String'),
                4 =>  array($start_date,                   'String'),
                5 =>  array($next_sched_contribution,      'String'),
                6 =>  array(date('YmdHis'),                'String'),
                7 =>  array($params['recur_id'],           'Integer')
            );

            if (isset($end_date)) {
                $sql            .= ", end_date = %8";
                $recur_params[8] = array($end_date, 'String');
            }

            $sql   .= ' WHERE id = %7';
            $status = ts('Recurring Contribution updated');

            // Moving recurring record to another contact, if 'Move Recurring Record?' is ticked
            $move_recurring_record = $this->_submitValues['move_recurring_record'];
            if ($move_recurring_record == 1) {
              $move_existing_contributions = $this->_submitValues['move_existing_contributions'];
              $selected_cid = $this->_submitValues['selected_cid'];

              if (!empty($selected_cid)) {
                // Update contact id in civicrm_contribution_recur table
                $update_recur_sql = "UPDATE civicrm_contribution_recur SET contact_id = %1 WHERE id = %2";
                $update_recur_params = array(
                  1 =>  array($selected_cid,      'Integer'),
                  2 =>  array($params['recur_id'],  'Integer')
                );
                CRM_Core_DAO::executeQuery($update_recur_sql, $update_recur_params);

                // Update contact id in civicrm_contribution table, if 'Move Existing Contributions?' is ticked
                if ($move_existing_contributions == 1) {
                  $update_contribution_sql = "UPDATE civicrm_contribution SET contact_id = %1 WHERE contribution_recur_id = %2";
                  CRM_Core_DAO::executeQuery($update_contribution_sql, $update_recur_params);
                }

                // Move recurring record to another membership
                $membership_record = $this->_submitValues['membership_record'];
                if (CRM_Core_DAO::checkFieldExists('civicrm_contribution_recur', 'membership_id')) {
                  // Update membership id in civicrm_contribution_recur table
                  $update_membership_sql = "UPDATE civicrm_contribution_recur SET membership_id = %1 WHERE id = %2";
                  $update_membership_params = array(
                    1 =>  array($membership_record,   'Integer'),
                    2 =>  array($params['recur_id'],  'Integer')
                  );
                  CRM_Core_DAO::executeQuery($update_membership_sql, $update_membership_params);

                  // Move membership payments if 'Move Existing Contributions?' is ticked
                  if ($move_existing_contributions == 1 && $membership_record > 0 ) {

                    // Create/Update membership payment
                    // Check if the membership payment exists
                    // if not create new one
                    $contributions_sql = "SELECT cc.id , mp.contribution_id, mp.id as payment_id FROM civicrm_contribution cc LEFT JOIN civicrm_membership_payment mp ON mp.contribution_id = cc.id WHERE cc.contribution_recur_id = %1";
                    $contributions_params = array(
                      1 =>  array($params['recur_id'],  'Integer')
                    );
                    $contributions_dao = CRM_Core_DAO::executeQuery($contributions_sql, $contributions_params);
                    while($contributions_dao->fetch()) {
                      if (!empty($contributions_dao->contribution_id)) {
                        //Update membership payment
                        $update_membership_payment_sql = "UPDATE civicrm_membership_payment SET membership_id = %1 WHERE id = %2";
                        $update_membership_payment_params = array(
                          1 =>  array($membership_record,   'Integer'),
                          2 =>  array($contributions_dao->payment_id,  'Integer')
                        );
                        CRM_Core_DAO::executeQuery($update_membership_payment_sql, $update_membership_payment_params);
                      } else {
                        //Insert membership payment
                        $insert_membership_payment_sql = "INSERT INTO civicrm_membership_payment SET contribution_id = %2 , membership_id = %1";
                        $insert_membership_payment_params = array(
                          1 =>  array($membership_record,   'Integer'),
                          2 =>  array($contributions_dao->id,  'Integer')
                        );
                        CRM_Core_DAO::executeQuery($insert_membership_payment_sql, $insert_membership_payment_params);
                      }
                    }
                  }
                }
              }
            }
        }

        CRM_Core_DAO::executeQuery($sql, $recur_params);
        $recur_id = ($params['action'] == 'add' ? CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()') : $params['recur_id']);
        CRM_Core_DAO::executeQuery("REPLACE INTO civicrm_contribution_recur_offline (recur_id) VALUES (%1)", array(1 => array($recur_id, 'Integer')));

        $session = CRM_Core_Session::singleton();
        //CRM_Core_Session::setStatus($status);
        CRM_Core_Session::setStatus($status, ts('Complete'), 'success');
        //CRM_Utils_System::redirect(
        //    CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=' . $params['cid'], FALSE, null, FALSE, TRUE)
        //);

      }

}
