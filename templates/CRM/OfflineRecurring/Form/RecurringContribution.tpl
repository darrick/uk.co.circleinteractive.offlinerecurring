
<div class="crm-block crm-form-block crm-add-offline-recurring-form-block">
  {if $action eq '1' OR $action eq '2'}
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl"}
    </div>
    <div class="help">
      {ts}You can setup a recurring payment. You need to enable the 'Process Offline Recurring Payments' job on the <a href="{crmURL p="civicrm/admin/job" q="reset=1"}">Scheduled Jobs</a> page, which will create contributions on the specified intervals for the contact. Please note the 'Next Scheduled Date' is the date, the contribution will be created. Please make sure the background process (cron job) is set to run every day.{/ts}
    </div>

    <table class="form-layout-compressed">
      <tr><td class="label">{$form.amount.label}</td><td>{$form.amount.html}</td><tr>
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
                {if $form.membership_record}
                  <tr>
                    <td class="label">{$form.membership_record.label}</td>
                    <td>{$form.membership_record.html}<br />
                      <sub>( Membership Type / Membership Status / Start Date / End Date )</sub>
                    </td>
                  </tr>
                {/if}
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
    var $form = cj("form.{/literal}{$form.formClass}{literal}");
    cj("#contact_id", $form).change(displayMemberships);

    function displayMemberships( ) {
        var data = cj("#contact_id", $form).select2('data');
        cj('input[name=selected_cid]').val(data.id);
        CRM.api('Membership', 'get', {'contact_id': data.id},
        {success: function(data) {
            cj('#membership_record').find('option').remove();
            cj('#membership_record').append(cj('<option>', {
                value: '0',
                text : '- select -'
            }));
            cj.each(data.values, function(key, value) {

                // Get membership status label
                var membershipStatusId = value.status_id;
                var membershipStatuslabel = '';
                CRM.api('MembershipStatus', 'getsingle', {'id': membershipStatusId},
                  {success: function(memstatus_data) {
                        cj('#membership_record').append(cj('<option>', {
                            value: value.id,
                            text : value.membership_name + ' / ' + memstatus_data.label + ' / ' + value.start_date + ' / ' + value.end_date
                        }));
                   }
                });
            });
            cj('#membership_record').parents('tr').show();
          },
        }
      );
    }

    /*var contactUrl = {/literal}"{crmURL p='civicrm/ajax/rest' q='className=CRM_Contact_Page_AJAX&fnName=getContactList&json=1&context=navigation' h=0 }"{literal};
    cj( '#contact_name' ).autocomplete( contactUrl, {
        width: 200,
        selectFirst: false,
        minChars:1,
        matchContains: true,
        delay: 400
    }).result(function(event, data, formatted) {
        var cid = data[1];
        var name = data[0].split('::');
        cj('input[name=selected_cid]').val(cid);
        cj('#contact_name').val(name[0]);
        CRM.api('Membership', 'get', {'contact_id': cid},
        {success: function(data) {
            cj('#membership_record').find('option').remove();
            cj('#membership_record').append(cj('<option>', {
                value: '',
                text : '- select -'
            }));
            cj.each(data.values, function(key, value) {
                cj('#membership_record').append(cj('<option>', {
                    value: value.id,
                    text : value.membership_name + '/' + value.start_date + '/' + value.end_date
                }));
            });
            cj('#membership_record').parents('tr').show();
          },
        }
      );
    });*/
});

cj(document).ready(function(){

    //cj('#membership_record').parents('tr').hide();

    cj('#move-recurring-table').hide();

    cj('#move_recurring_record').change(function(){
        if (cj('#move_recurring_record').is(':checked')) {
            cj('#move-recurring-table').show();
        } else {
            cj('#move-recurring-table').hide();
        }
    });

    // Hide 'Move Existing Contributions?' field is no existing contributions available
    {/literal}{if $existingContributionsAvailable eq 0}{literal}
        cj('#move_existing_contributions').prop('checked', false);
        cj('#move_existing_contributions').parent().parent().hide();
    {/literal}{/if}{literal}

});

</script>
{/literal}
