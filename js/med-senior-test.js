jQuery( function( $ ) {
  $.each(medSeniorTestSettings.items, function(){
    let _this = $(this);
    console.log(_this);
    $(_this).DataTable({
      //"processing": true,
      //"serverSide": true,
      /*"ajax": {
        url: medSeniorTestSettings.ajax_url,
        type: 'POST',
        data: {
          'action': 'senior_test_get_data',
        }
      },*/
      "columns": _this.columns 
    }); 
  });
});