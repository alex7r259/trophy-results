jQuery(document).ready(function($) {
    var point_admin = {
        ajaxurl: point_admin_vars.ajaxurl,
        nonce: point_admin_vars.nonce
    };
    
    $.ajax({
        url: point_admin.ajaxurl,
        type: "POST",
        data: {
            action: "load_seasons",
            nonce: point_admin.nonce
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
                $("#message").html("<div class=\"updated\"><p>Загружено!</p></div>");
                
                // Обработчик изменения сезона
                $('#event-select').on('change', function() {
                    var season_id = $(this).val();
                    if(!season_id) return; // Не загружаем если не выбран сезон
                    $.ajax({
                        url: point_admin.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'load_table',
                            id: season_id, 
                            table: 'pointstable',
                            col: 'season_id',
                            nonce: point_admin.nonce
                        },
                        beforeSend: function() {
                            $('#table_events').html('<H3>Загрузка...</H3>');
                        },
                        success: function(response) {
                            if (response.success) {
                                var div = '<table id="data_events" class="admin_table"><thead><tr><th name="position">Позиция</th><th name="points">Баллы</th><th name="delete"></th></tr></thead><tbody>';
                                
                                $.each(response.data, function(index, data) {

                                    div += '<tr id="' + data.points_id + '">' +
                                           '<td id="position' + data.points_id + '">' + data.position + '</td>' +
                                           '<td id="points' + data.points_id + '">' + data.points + '</td>' +
                                           '<td id="del' + data.event_id + '">🗑</td>' +
                                           '</tr>';
                                });
                                
                                div += '<tr><td id="foot"><input type="text" style="line-height: 1; min-height: 20px; max-width: 70px;" id="position"></td><td id="foot"><input type="number" min="0" step="0.1" style="line-height: 1; min-height: 20px; max-width: 70px;" id="points"></td><td id="foot"><p id="save_new">💾</p></td></tr>';
                                
                                div += '</tbody></table>';
                                $('#table_events').html(div);
                                $("#message").html("<div class=\"updated\"><p>Таблица баллов загружена!</p></div>");
                                
                                // Обработка удаления
                                $('[id^="del"]').on("click", function() {
                                    var ev_id = $(this).closest('tr').attr('id');
                                    if(confirm("Вы уверены, что хотите удалить?")) {
                                        $.ajax({
                                            url: point_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_delete",
                                                id: ev_id,
                                                table: 'pointstable',
                                                col: 'points_id',
                                                nonce: point_admin.nonce
                                                
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
                                    
                                    if($('#position').val() && $('#points').val()) {
                                        var variables = {
                                            position: $('#position').val(),
                                            points: $('#points').val(),
                                            season_id: season_id
                                            
                                        };
                                    
                                        $.ajax({
                                            url: point_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_save",
                                                table: 'pointstable',
                                                variables: variables,
                                                nonce: point_admin.nonce
                                                
                                            },
                                            beforeSend: function() {
                                                $("#message").html("<p>Сохранение...</p>");
                                                
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    $("#message").html("<div class=\"updated\"><p>Ок!</p></div>");
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
                                    var points_id = td.closest('tr').attr('id');
                                    var col_name = td.closest('table').find('th').eq($(td).index()).attr('name');
                                    var id = td.attr('id');
                                    var currentValue = td.text();
                                    var type = 'text';
                                    
                                    if (id == 'foot'){
                                        return true;
                                    }
                                    
                                    // Создаем input для редактирования
                                    var input = $('<input>', {
                                        type: type,
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
                                                    url: point_admin.ajaxurl,
                                                    type: 'POST',
                                                    data: {
                                                        action: 'update_table',
                                                        table: 'pointstable',
                                                        id: points_id,
                                                        col: 'points_id',
                                                        field: col_name,
                                                        value: newValue,
                                                        nonce: point_admin.nonce
                                                    },
                                                    beforeSend: function() {
                                                        $("#message").html('<em>Сохранение...</em>');
                                                    },
                                                    success: function(response) {
                                                        if (response.success) {
                                                            td.text(newValue); // Обновляем значение в таблице
                                                            $("#message").html("<div class=\"updated\"><p>Ок!</p></div>");
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