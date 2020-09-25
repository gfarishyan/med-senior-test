jQuery( function( $ ) {
  $.each(medSeniorTestSettings.items, function(index, item) {
    let cols = [];
    for (i in item.columns) {
      cols.push({"data": item.columns[i]});
    }
    let $target = $(item.target);
    $(item.id).DataTable({
      processing: true,
      serverSide: true,
      columns: cols,
      ajax: {
        url: medSeniorTestSettings.ajax_url,
        type: 'POST',
        data: {
          action: 'med_senior_test_data',
        },
        dataType: 'json',
        dataSrc: 'data'
      }
    }); 
  });

});