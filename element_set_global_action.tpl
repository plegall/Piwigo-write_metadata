{footer_script}
var writeMetadataProgressMessage = "{'Write metadata in progress...'|translate}";
var wm_pwg_token = '{$WM_PWG_TOKEN}';
{literal}
jQuery(document).ready(function() {
  jQuery('#applyAction').click(function(e) {
    if (typeof(elements) != "undefined") {
      return true;
    }

    if (jQuery('[name="selectAction"]').val() == 'writeMetadata')
    {
      e.stopPropagation();
    }
    else
    {
      return true;
    }

    jQuery('.bulkAction').hide();
    jQuery('#regenerationText').html(writeMetadataProgressMessage);
    var maxRequests=1;

    var queuedManager = jQuery.manageAjax.create('queued', { 
      queue: true,  
      cacheResponse: false,
      maxRequests: maxRequests
    });

    elements = Array();

    if (jQuery('input[name=setSelected]').is(':checked')) {
      elements = all_elements;
    }
    else {
      jQuery('input[name="selection[]"]').filter(':checked').each(function() {
        elements.push(jQuery(this).val());
      });
    }

    progressBar_max = elements.length;
    todo = 0;

    jQuery('#applyActionBlock').hide();
    jQuery('select[name="selectAction"]').hide();
    jQuery('#regenerationMsg').show();
    jQuery('#progressBar').progressBar(0, {
      max: progressBar_max,
      textFormat: 'fraction',
      boxImage: 'themes/default/images/progressbar.gif',
      barImage: 'themes/default/images/progressbg_orange.gif'
    });

    for (i=0;i<elements.length;i++) {
      queuedManager.add({
        type: 'POST', 
        url: 'ws.php?format=json', 
        data: {
          method: "pwg.images.writeMetadata",
          pwg_token: wm_pwg_token,
          image_id: elements[i]
        },
        dataType: 'json',
        success: ( function(data) { progressWriteMetadata(++todo, progressBar_max, data['result']) }),
        error: ( function(data) { progressWriteMetadata(++todo, progressBar_max, false) })
      });
    }
    return false;
  });

  function progressWriteMetadata(val, max, success) {
    jQuery('#progressBar').progressBar(val, {
      max: max,
      textFormat: 'fraction',
      boxImage: 'themes/default/images/progressbar.gif',
      barImage: 'themes/default/images/progressbg_orange.gif'
    });
    type = success ? 'regenerateSuccess': 'regenerateError'
    s = jQuery('[name="'+type+'"]').val();
    jQuery('[name="'+type+'"]').val(++s);

    if (val == max)
      jQuery('#applyAction').click();
  }

});
{/literal}{/footer_script}

<div id="write_metadata" class="bulkAction">
</div>