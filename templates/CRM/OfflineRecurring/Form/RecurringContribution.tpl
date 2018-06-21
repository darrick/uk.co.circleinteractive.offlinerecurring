
<div class="crm-block crm-form-block crm-add-offline-recurring-form-block">
  {if $action eq '1' OR $action eq '2'}
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl"}
    </div>
    <div class="help">
      {ts}You can setup a recurring payment. You need to enable the 'Process Offline Recurring Payments' job on the <a href="{crmURL p="civicrm/admin/job" q="reset=1"}">Scheduled Jobs</a> page, which will create contributions on the specified intervals for the contact. Please note the 'Next Scheduled Date' is the date, the contribution will be created. Please make sure the background process (cron job) is set to run every day.{/ts}
    </div>

    <table class="form-layout-compressed">
      <tr><td class="label">{$form.amount.label}</td><td>{$form.currency.html} &nbsp; &nbsp;{$form.amount.html}</td><tr>
      <tr><td>&nbsp;</td><td>{$form.is_recur.html} {ts}{$form.frequency_interval.label}{/ts} &nbsp;{$form.frequency_interval.html} &nbsp; {$form.frequency_unit.html}</td></tr>
      <tr><td class="label">{$form.start_date.label}</td><td>{include file="CRM/common/jcalendar.tpl" elementName=start_date}</td></tr>
      <tr><td class="label">{$form.next_sched_contribution.label}</td><td>{include file="CRM/common/jcalendar.tpl" elementName=next_sched_contribution}<br />
        <div class="description">{ts}This is the date the contribution record will be created for the recurring payment (by the background process). If you want the first contribution on the start date, this should be same as start date.{/ts}</div>
      </td></tr>
      <tr><td class="label">{$form.end_date.label}</td><td>{include file="CRM/common/jcalendar.tpl" elementName=end_date} <br/>
        <div class="description">{ts}Please specify end date only if you want to end the recurring contribution. Else leave it blank.<br /><b>Please note: No contribution record will be created (by the background process) for the contact, if end date is specified</b>.{/ts}</div>
      </td></tr>
    </table>

    {if $action eq 2}
      {assign var="existingContributionsAvailable" value=0}
      {crmAPI var='result' entity='Contribution' action='get' contribution_recur_id=$recur_id}
      {if !empty($result.values)}
        {assign var="existingContributionsAvailable" value=1}
        <div class="crm-accordion-wrapper crm-contributionDetails-accordion collapsed">
          <div class="crm-accordion-header active">{ts}Existing Contributions{/ts}</div>
          <div class="crm-accordion-body" id="body-contributionDetails">
            <div id="contributionDetails">
              <div class="crm-section contribution-list">
                <table>
                  <tr>
                    <th>{ts}Amount{/ts}</th>
                    <th>{ts}Type{/ts}</th>
                    <th>{ts}Source{/ts}</th>
                    <th>{ts}Received{/ts}</th>
                    <th>{ts}Status{/ts}</th>
                  </tr>
                  {foreach from=$result.values item=ContributionDetails}
                    <tr>
                      <td>{$ContributionDetails.total_amount|crmMoney}</td>
                      <td>{$ContributionDetails.financial_type}</td>
                      <td>{$ContributionDetails.contribution_source}</td>
                      <td>{$ContributionDetails.receive_date|crmDate}</td>
                      <td>{$ContributionDetails.contribution_status}</td>
                    </tr>
                  {/foreach}
                </table>
              </div>
            </div>
          </div>
        </div>
      {/if}

      {* Move recur record to new contact *}
      <div class="crm-accordion-wrapper crm-moveRecur-accordion collapsed">
        <div class="crm-accordion-header active">{ts}Move{/ts}</div>
        <div class="crm-accordion-body" id="body-moveRecur">
          <div id="help">
            You can move the recurring record to another contact/membership.
            {if $existingContributionsAvailable eq 1}
              You can also choose to move the existing contributions to selected contact or retain with the existing contact.
            {/if}
          </div>
          <div id="moveRecur">
            <div class="crm-section">
              {$form.move_recurring_record.html}&nbsp;{$form.move_recurring_record.label}
              <br /><br />
              <table class="form-layout" id="move-recurring-table">
                <tr>
                  <td class="label">{$form.contact_id.label}</td>
                  <td>{$form.contact_id.html}</td>
                </tr>
                <tr>
                  <td class="label">{$form.move_existing_contributions.label}</td>
                  <td>{$form.move_existing_contributions.html}</td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>
    {/if}
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl"}
    </div>
  {/if}
</div>

{literal}
<script type="text/javascript">
CRM.$(function() {
  $('#move-recurring-table').hide();

  $('#move_recurring_record').change(function(){
    if ($('#move_recurring_record').is(':checked')) {
      $('#move-recurring-table').show();
    }
    else {
      $('#move-recurring-table').hide();
    }
  });

  // Hide 'Move Existing Contributions?' field is no existing contributions available
  {/literal}{if $existingContributionsAvailable eq 0}{literal}
    $('#move_existing_contributions').prop('checked', false);
    $('#move_existing_contributions').parent().parent().hide();
  {/literal}{/if}{literal}
});
</script>
{/literal}