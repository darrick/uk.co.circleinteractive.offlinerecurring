<?php

/**
 * CiviCRM Offline Recurring Extension
 * Legacy version - Civi < 4.5
 * @package uk.co.circleinteractive.offlinerecurring
 */

/**
 * Implementation of hook_civicrm_pageRun
 */
function offlinerecurring_civicrm_pageRun(&$page) {

    if ($page->getVar('_name') == 'CRM_Contribute_Page_Tab') {
        
        $contact_id = CRM_Utils_Array::value('cid', $_GET, '');
        
        // modified - andyw@circle, 19/07/2013
        // show all recurring payments
        // recurring record generated by the extension is allowed to be edited in hook
        // see offlinerecurring_civicrm_alterRecurringContributionsParams
        $query = "
            SELECT * FROM civicrm_contribution_recur ccr 
            WHERE contact_id = %1
        ";

        $dao        = CRM_Core_DAO::executeQuery($query, array(1 => array($contact_id, 'String')));
        $recurArray = array();
        
        if (_offlinerecurring_getCRMVersion() >= 4.4)
          $dao->next_sched_contribution = $dao->next_sched_contribution_date;
        
        $membershipIdExists = FALSE;
        // Check if membership is linked with the recur record and allowed to be moved to different membership
        // NOTE: 'membership_id' is not in 'civicrm_contribution_recur' table by default
        if(CRM_Core_DAO::checkFieldExists('civicrm_contribution_recur', 'membership_id')) {
          $membershipIdExists = TRUE;
          $page->assign('membershipIdExists', $membershipIdExists);
        }
        
        while ($dao->fetch()) {
            $recurParams = array(
                'id'                      => $dao->id,
                'amount'                  => CRM_Utils_Money::format($dao->amount),
                'frequency_unit'          => $dao->frequency_unit,
                'frequency_interval'      => $dao->frequency_interval,
                'start_date'              => $dao->start_date,
                'next_sched_contribution' => $dao->next_sched_contribution,
                'payment_processor_id'    => $dao->payment_processor_id,
                'payment_instrument_id'   => $dao->payment_instrument_id,  
                'enable_edit'             => 0,
            );
            
            if($membershipIdExists == TRUE) {
              $recurParams['membership_name'] = '';
              if (!empty($dao->membership_id)) {
                // get membership details, if linked to the recurring record
                require_once 'api/api.php';
                $result = civicrm_api('membership', 'get', array('version' => 3, 'id' => $dao->membership_id));
                $recurParams['membership_name'] = $result['values'][$dao->membership_id]['membership_name'];
              }
            }
            
            // Allow $recurParams to be modified via hook, before displayed
            // This will allow 'edit' link to be enabled for certain 'payment instruments' or 'payment processor'
            // 'edit' link is disabled for all recurring contributions by default
            require_once 'Recurring/Utils/Hook.php';
            Recurring_Utils_Hook::alterRecurringContributionParams( $recurParams );
            
            $recurArray[$dao->id] = $recurParams;
        }
        
        //for contribution tabular View
        $buildTabularView = CRM_Utils_Array::value('showtable', $_GET, false);
        $page->assign('buildTabularView', $buildTabularView);

        if ($buildTabularView)
            return;
        
        $page->assign('recurArray', $recurArray);
        $page->assign('recurArrayCount', count($recurArray));
    
    }

}

/* 
 * Implementation of hook_civicrm_alterRecurringContributionParams
 * 
 * Hook implemented to enable editing of recurring record
 * so that implementors can decide which recurring records can be allowed to be edited
 */ 
function offlinerecurring_civicrm_alterRecurringContributionParams( &$params ) {
  if (empty($params['id'])) {
    return;
  }
  $query = "SELECT * FROM civicrm_contribution_recur_offline WHERE recur_id = %1";
  $dao = CRM_Core_DAO::executeQuery($query, array(1 => array($params['id'], 'Integer')));
  if ($dao->fetch()) {
    $params['enable_edit'] = 1;
  }
}

# cron job converted from standalone cron script to job api call
# todo: really need to rewrite this using the ContributionRecur api - that api didn't
# exist when the extension was first written
function civicrm_api3_job_process_offline_recurring_payments($params) {
    
    $config = &CRM_Core_Config::singleton();
    $debug  = false;
                
    $dtCurrentDay      = date("Ymd", mktime(0, 0, 0, date("m") , date("d") , date("Y")));
    $dtCurrentDayStart = $dtCurrentDay."000000"; 
    $dtCurrentDayEnd   = $dtCurrentDay."235959"; 
    
    // Select the recurring payment, where current date is equal to next scheduled date
    $sql = "
        SELECT * FROM civicrm_contribution_recur ccr
    INNER JOIN civicrm_contribution_recur_offline ccro ON ccro.recur_id = ccr.id
         WHERE (ccr.end_date IS NULL OR ccr.end_date > NOW())
           AND ccr.next_sched_contribution >= %1 
           AND ccr.next_sched_contribution <= %2
    ";
    
    if (_offlinerecurring_getCRMVersion() >= 4.4)
        $sql = str_replace('next_sched_contribution', 'next_sched_contribution_date', $sql);

    $dao = CRM_Core_DAO::executeQuery($sql, array(
          1 => array($dtCurrentDayStart, 'String'),
          2 => array($dtCurrentDayEnd, 'String')
       )
    );
    
    $counter = 0;
    $errors  = 0;
    $output  = array();
    
    while($dao->fetch()) {
                
        $contact_id                 = $dao->contact_id;
        $hash                       = md5(uniqid(rand(), true)); 
        $total_amount               = $dao->amount;
        $contribution_recur_id      = $dao->id;
        $contribution_type_id       = 1;
        $source                     = "Offline Recurring Contribution";
        $receive_date               = date("YmdHis");
        $contribution_status_id     = 2;    // Set to pending, must complete manually
        $payment_instrument_id      = 3;
        
        require_once 'api/api.php';
        $result = civicrm_api('contribution', 'create',
            array(
                'version'                => 3,
                'contact_id'             => $contact_id,
                'receive_date'           => $receive_date,
                'total_amount'           => $total_amount,
                'payment_instrument_id'  => $payment_instrument_id,
                'trxn_id'                => $hash,
                'invoice_id'             => $hash,
                'source'                 => $source,
                'contribution_status_id' => $contribution_status_id,
                'contribution_type_id'   => $contribution_type_id,
                'contribution_recur_id'  => $contribution_recur_id,
                //'contribution_page_id'   => $entity_id
            )
        );
        if ($result['is_error']) {
            $output[] = $result['error_message'];
            ++$errors;
            ++$counter;
            continue;
        } else {
            $contribution = reset($result['values']);
            $contribution_id = $contribution['id'];
            $output[] = ts('Created contribution record for contact id %1', array(1 => $contact_id)); 
        }
    
        //$mem_end_date = $member_dao->end_date;

        $next_sched_contribution = _offlinerecurring_getCRMVersion() >= 4.4 ? 
            $dao->next_sched_contribution_date : $dao->next_sched_contribution;
        
        $temp_date = strtotime($next_sched_contribution);
        
        $next_collectionDate = strtotime ("+$dao->frequency_interval $dao->frequency_unit", $temp_date);
        $next_collectionDate = date('YmdHis', $next_collectionDate);
        
        $sql = "
            UPDATE civicrm_contribution_recur 
               SET next_sched_contribution = %1 
             WHERE id = %2
        ";
        
        if (_offlinerecurring_getCRMVersion() >= 4.4)
            $sql = str_replace('next_sched_contribution', 'next_sched_contribution_date', $sql);

        CRM_Core_DAO::executeQuery($sql, array(
               1 => array($next_collectionDate, 'String'),
               2 => array($dao->id, 'Integer')
           )
        );


        $result = civicrm_api('activity', 'create',
            array(
                'version'             => 3,
                'activity_type_id'    => 6,
                'source_record_id'    => $contribution_id,
                'source_contact_id'   => $contact_id,
                'assignee_contact_id' => $contact_id,
                'subject'             => "Offline Recurring Contribution - " . $total_amount,
                'status_id'           => 2,
                'activity_date_time'  => date("YmdHis"),            
            )
        );
        if ($result['is_error']) {
            $output[] = ts(
                'An error occurred while creating activity record for contact id %1: %2',
                array(
                    1 => $contact_id,
                    2 => $result['error_message']
                )
            );
            ++$errors;
        } else {
            $output[] = ts('Created activity record for contact id %1', array(1 => $contact_id)); 

        }
        ++$counter;
    }
    
    // If errors ..
    if ($errors)
        return civicrm_api3_create_error(
            ts("Completed, but with %1 errors. %2 records processed.", 
                array(
                    1 => $errors,
                    2 => $counter
                )
            ) . "<br />" . implode("<br />", $output)
        );
    
    // If no errors and records processed ..
    if ($counter)
        return civicrm_api3_create_success(
            ts(
                '%1 contribution record(s) were processed.', 
                array(
                    1 => $counter
                )
            ) . "<br />" . implode("<br />", $output)
        );
   
    // No records processed
    return civicrm_api3_create_success(ts('No contribution records were processed.'));
    
}   
