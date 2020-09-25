jQuery( function( $ ) {
  $.each(medSeniorTestSettings.items, function(index, item) {
    let cols = [];
    let sorts = (item.sorts.length > 0) ? item.sorts : [];
    for (i in item.columns) {
      let col = {"data": item.columns[i], "orderable": false};
      for (j in sorts) {
        if (sorts[j][0] == i) {
          col.orderable = true;
          break;
        }
      }
      cols.push(col);
    }
    let $target = $(item.target);
    $(item.id).DataTable({
      processing: true,
      serverSide: true,
      columns: cols,
      order: (item.sorts.length > 0) ? item.sorts : false,
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