jQuery(document).ready(function($) {
    let languages = {
        "sProcessing":    "Processando...",
        "sLengthMenu":    "Mostrar _MENU_ registros",
        "sZeroRecords":   "Nenhum resultado encontrado",
        "sEmptyTable":    "Nenhum dado disponível nesta tabela",
        "sInfo":          "Mostrando registros de _START_ a _END_ de um total de _TOTAL_ registros",
        "sInfoEmpty":     "Mostrando registros de 0 a 0 de um total de 0 registros",
        "sInfoFiltered":  "(filtrado de um total de _MAX_ registros)",
        "sInfoPostFix":   "",
        "sSearch":        "Buscar:",
        "sUrl":           "",
        "sInfoThousands":  ",",
        "sLoadingRecords": "Carregando...",
        "oPaginate": {
            "sFirst":    "Primeiro",
            "sLast":    "Último",
            "sNext":    "Seguinte",
            "sPrevious": "Anterior"
        },
        "oAria": {
            "sSortAscending":  ": Ative para classificar a coluna em ordem crescente",
            "sSortDescending": ": Ative para classificar a coluna em ordem decrescente"
        }
    };

    let table = $('#magalu-orders').DataTable({
        data: data,
        columns: [
            {
                title: 'Magalu ID',
                data: 'id',
            },
            {
                'title': 'Pedido',
                'data': 'order_id'
            },
            {
                'title': 'Status',
                'data': 'status'
            },
            {
                'title': 'Data do Pagamento',
                'data': 'approved_date'
            },
            {
                'title': 'Ações',
                'data': 'actions'
            },
        ],
        "order": [[ 0, 'desc' ]],
        "pageLength": 50,
        "language": languages
    });

    if ( $('#form1 .button-primary.update').length == 0 ) {
        $('#form2 .button-primary').hide();
    }
    if ( $('#form2 .button-primary:visible').length != 0 && $('#form1 .button-primary.update').length == 0) {
        $('#form1 input').each(function(){
            $(this).attr('disabled', true);
        });
    }
    if ( $('#form3 .button-primary').length != 0 ) {
        $('#form1 input').each(function(){
            $(this).attr('disabled', true);
        });
        $('#form2 input').each(function(){
            $(this).attr('disabled', true);
        });
    }

    $('#magalu-order-logs').DataTable({
        data: logs,
        columns: [
            {
                title: 'Horário',
                data: 'event_date',
            },
            {
                'title': 'Ocorrência',
                'data': 'message'
            },
            {
                'title': 'Tipo',
                'data': 'type'
            },
        ],
        "order": [[ 0, 'desc' ]],
        "pageLength": 10,
        "language": languages
    });
});
