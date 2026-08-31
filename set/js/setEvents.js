jQuery(document).ready(function($) {
    var event_admin = {
        ajaxurl: event_admin_vars.ajaxurl,
        nonce: event_admin_vars.nonce
    };
    
    function formatServerDate(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    
    $.ajax({
        url: event_admin.ajaxurl,
        type: "POST",
        data: {
            action: "load_seasons",
            nonce: event_admin.nonce
        },
        beforeSend: function() {
            $("#message").html("<p>Загрузка...</p>");
        },
        success: function(response) {
            if (response.success) {
                var div = '<select name="event" id="event-select">';
                $.each(response.data, function(index, data) {  
                    if(index === 0) {
                        }else{
                    div += '<option value="' + data.season_id + '">' + data.season_name + '</option>';}
                });
                div += '</select><div id="table_events" style="margin-top:30px; overflow-y: scroll;"></div>';
                $('#body').html(div);
                $("#message").html("<div class=\"updated\"><p>Сезоны серии загружены!</p></div>");
                $("#message").delay(3000).html("");
                
                // Обработчик изменения сезона
                $('#event-select').on('change', function() {
                    var season_id = $(this).val();
                    if(!season_id) return; // Не загружаем если не выбран сезон
                    $.ajax({
                        url: event_admin.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'load_table',
                            id: season_id, 
                            table: 'events',
                            col: 'season_id',
                            nonce: event_admin.nonce
                        },
                        beforeSend: function() {
                            $('#table_events').html('<H3>Загрузка...</H3>');
                        },
                        success: function(response) {
                            if (response.success) {
                                var div = '<table id="data_events" class="admin_table"><thead><tr><th name="event_name">Название</th><th name="location">Место проведения</th><th name="event_date">Дата</th><th name="coefficient">Коэф.</th><th name="delete"></th></tr></thead><tbody>';
                                
                                $.each(response.data, function(index, data) {

                                    div += '<tr id="' + data.event_id + '">' +
                                           '<td id="name' + data.event_id + '">' + data.event_name + '</td>' +
                                           '<td id="loc' + data.event_id + '">' + data.location + '</td>' +
                                           '<td id="data' + data.event_id + '">' + formatServerDate(data.event_date) + '</td>' +
                                           '<td id="coef' + data.event_id + '">' + data.coefficient + '</td>' +
                                           '<td id="del' + data.event_id + '">🗑</td>' +
                                           '</tr>';
                                });
                                
                                div += '<tr><td id="foot"><input type="text" id="name" style="line-height: 1; min-height: 20px; max-width: 150px;"></td><td id="foot"><input type="text" id="loc" style="line-height: 1; min-height: 20px; max-width: 150px;"></td><td id="foot"><input type="date" id="data" style="line-height: 1; min-height: 20px; max-width: 120px;"></td><td id="foot"><input type="number" step="0.5" min="1" max="2" id="coef" value="1.00" style="line-height: 1; min-height: 20px; max-width: 70px;"></td><td id="foot"><p id="save_new">💾</p></td></tr>';
                                
                                div += '</tbody></table>';
                                $('#table_events').html(div);
                                $("#message").html("<div class=\"updated\"><p>Этапы сезона загружены!</p></div>");
                                $("#message").delay(3000).html("");
                                
                                // Обработка удаления
                                $('[id^="del"]').on("click", function() {
                                    var ev_id = $(this).closest('tr').attr('id');
                                    if(confirm("Вы уверены, что хотите удалить этот этап?")) {
                                        $.ajax({
                                            url: event_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_delete",
                                                id: ev_id,
                                                table: 'events',
                                                col: 'event_id',
                                                nonce: event_admin.nonce
                                                
                                            },
                                            beforeSend: function() {
                                                $("#message").html("<p>Удаление...</p>");
                                                
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    $("#message").html("<div class=\"updated\"><p>Удален!</p></div>");
                                                    $('#event-select').val(season_id).trigger('change');
                                                    
                                                } else {
                                                    $("#message").html("<div class=\"error\"><p>Ошибка: " + response.data + "</p></div>");
                                                    
                                                }
                                                
                                            },
                                            error: function(xhr, status, error) {
                                                $("#message").html("<div class=\"error\"><p>Ошибка AJAX: " + error + "</p></div>");
                                                
                                            }
                                            
                                        });
                                        
                                    }
                                    
                                });
                                
                                // Обработка сохранения
                                $('#save_new').on("click", function() {
                                    
                                    if($('#name').val() && $('#loc').val() && $('#data').val() && $('#coef').val()) {
                                        var variables = {
                                            event_name: $('#name').val(),
                                            season_id: season_id,
                                            location: $('#loc').val(),
                                            coefficient: $('#coef').val(),
                                            event_date: $('#data').val()
                                            
                                        };
                                    
                                        $.ajax({
                                            url: event_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_save",
                                                table: 'events',
                                                variables: variables,
                                                nonce: event_admin.nonce
                                                
                                            },
                                            beforeSend: function() {
                                                $("#message").html("<p>Сохранение...</p>");
                                                
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    $("#message").html("<div class=\"updated\"><p>Ок!</p></div>");
                                                    $("#message").delay(3000).html("");
                                                    $('#event-select').val(season_id).trigger('change');
                                                    
                                                } else {
                                                    $("#message").html("<div class=\"error\"><p>Ошибка: " + response.data + "</p></div>");
                                                    
                                                }
                                                
                                            },
                                            error: function(xhr, status, error) {
                                                $("#message").html("<div class=\"error\"><p>Ошибка AJAX: " + error + "</p></div>");
                                                
                                            }
                                            
                                        });
                                        
                                    } else {
                                        $("#message").html("<div class=\"error\"><p>Заполните все поля!</p></div>");
                                    }
                                    
                                });
                                
                                // Редактирование таблицы
                                $('#data_events').on('dblclick', 'td', function() {
                                    var td = $(this);
                                    var event_id = td.closest('tr').attr('id');
                                    var col_name = td.closest('table').find('th').eq($(td).index()).attr('name');
                                    var id = td.attr('id');
                                    var currentValue = td.text();
                                    var type;
                                    var step;
                                    var min;
                                    var max;
                                    
                                    if (col_name == 'event_name' || col_name == 'location'){
                                        type = 'text';
                                    }else if(col_name == 'event_date'){
                                        type = 'date';
                                    }else if(col_name == 'coefficient'){
                                        type = 'number';
                                        step = '0.5';
                                        min = '1';
                                        max = '2';
                                    }
                                    
                                    if (id == 'foot'){
                                        return true;
                                    }
                                    
                                    // Создаем input для редактирования
                                    var input = $('<input>', {
                                        type: type,
                                        step: step,
                                        min: min,
                                        max: max,
                                        val: currentValue,
                                        css: {
                                            'font-size': '14px',
                                            'width': '100%',
                                            'box-sizing': 'border-box'
                                            },
                                            on: {
                                                // Сохраняем при нажатии Enter
                                                keypress: function(e) {
                                                    if (e.which === 13) { // 13 - код клавиши Enter
                                                    saveChanges();
                                                    }
                                                },
                                                // Сохраняем при потере фокуса
                                                blur: function() {
                                                    saveChanges();
                                                    }
                                            }
                                        });
                                        
                                        // Заменяем содержимое ячейки на input
                                        td.html(input);
                                        input.focus();
                                        
                                        function saveChanges() {
                                            var newValue = input.val();
                                            // Если значение изменилось
                                            if (newValue !== currentValue) {
                                                // AJAX-запрос для сохранения изменений
                                                $.ajax({
                                                    url: event_admin.ajaxurl,
                                                    type: 'POST',
                                                    data: {
                                                        action: 'update_table',
                                                        table: 'events',
                                                        id: event_id,
                                                        col: 'event_id',
                                                        field: col_name,
                                                        value: newValue,
                                                        nonce: event_admin.nonce
                                                    },
                                                    beforeSend: function() {
                                                        $("#message").html('<em>Сохранение...</em>');
                                                    },
                                                    success: function(response) {
                                                        if (response.success) {
                                                            td.text(newValue); // Обновляем значение в таблице
                                                            $("#message").html("<div class=\"updated\"><p>Ок!</p></div>");
                                                            $("#message").delay(3000).html("");
                                                            $('#event-select').val(season_id).trigger('change');
                                                            } else {
                                                                td.text(currentValue); // Возвращаем старое значение при ошибке
                                                                $('#message').html('<div class="error"><p>Ошибка: ' + response.data + '</p></div>');
                                                            }
                                                    },
                                                    error: function(xhr, status, error) {
                                                        td.text(currentValue); // Возвращаем старое значение при ошибке
                                                        $('#message').html('<div class="error"><p>Ошибка AJAX: ' + error + '</p></div>');
                                                    }
                                                });
                                            } else {
                                                // Если значение не изменилось, просто возвращаем текст
                                                td.text(currentValue);
                                            }
                                        }
                                });
                                
                            } else {
                                $('#message').html('<div class="error"><p>Ошибка: ' + response.data + '</p></div>');
                                
                            }
                            
                        },
                        error: function(xhr, status, error) {
                            $('#message').html('<div class="error"><p>Ошибка AJAX: ' + error + '</p></div>');
                        }
                    });
                });
                if(response.data.length > 0) {
                    $('#event-select').val(response.data[0].season_id).trigger('change');
                }
            } else {
                $("#message").html("<div class=\"error\"><p>Ошибка: " + response.data + "</p></div>");
            }
        },
        error: function(xhr, status, error) {
            $("#message").html("<div class=\"error\"><p>Ошибка AJAX: " + error + "</p></div>");
        }
    });
});