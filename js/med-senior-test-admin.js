jQuery( function( $ ) {
  $(".med-senior-test-sync-data").click(function(e){
    e.preventDefault();
    med_senior_test_remote_sync_data();
  });
  
  function med_senior_test_remote_sync_data() {
    $.ajax({
      url: medSeniorTestAdminSettings.ajax_url,
      type: 'POST',
      data: {
        action: 'med_senior_test_data_sync_data',
      },
      dataType: 'json',
      success: function(resp) {
        if (resp.success) {
          if (resp.data.completed == 1) {
            $(".med-senior-test-sync-data").attr('disabled', false);
            $(".med-senior-test-sync-data").text($(".med-senior-test-sync-data").data('btn-text') + " completed");
          } else {
            $(".med-senior-test-sync-data").attr('disabled', true);
            $(".med-senior-test-sync-data").text($(".med-senior-test-sync-data").data('btn-text') + ' ' + resp.data.completed + '%');
            med_senior_test_remote_sync_data();
          }
        }
      }
    });
  }
});
