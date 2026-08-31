jQuery(document).ready(function($) {
    var class_admin = {
        ajaxurl: class_admin_vars.ajaxurl,
        nonce: class_admin_vars.nonce
    };
    
    function formatServerDate(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        return `${parts[2]}.${parts[1]}.${parts[0]}`;
    }
    
    $.ajax({
        url: class_admin.ajaxurl,
        type: "POST",
        data: {
            action: "load_seasons",
            nonce: class_admin.nonce
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
                        url: class_admin.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'load_table',
                            id: season_id, 
                            table: 'class',
                            col: 'season_id',
                            nonce: class_admin.nonce
                        },
                        beforeSend: function() {
                            $('#table_events').html('<H3>Загрузка...</H3>');
                        },
                        success: function(response) {
                            if (response.success) {
                                var div = '<table id="data_events" class="admin_table"><thead><tr><th>id</th><th name="class_name">Название</th><th name="delete"></th></tr></thead><tbody>';
                                
                                $.each(response.data, function(index, data) {

                                    div += '<tr id="' + data.class_id + '">' +
                                           '<td id="foot">' + data.class_id + '</td>' +
                                           '<td id="name' + data.class_id + '">' + data.class_name + '</td>' +
                                           '<td id="del' + data.event_id + '">🗑</td>' +
                                           '</tr>';
                                });
                                
                                div += '<tr><td id="foot"></td><td id="foot"><input id="name"></td><td id="foot"><p id="save_new">💾</p></td></tr>';
                                
                                div += '</tbody></table>';
                                $('#table_events').html(div);
                                $("#message").html("<div class=\"updated\"><p>Классы сезона загружены!</p></div>");
                                $("#message").delay(3000).html("");
                                
                                // Обработка удаления
                                $('[id^="del"]').on("click", function() {
                                    var ev_id = $(this).closest('tr').attr('id');
                                    if(confirm("Вы уверены, что хотите удалить этот класс?")) {
                                        $.ajax({
                                            url: class_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_delete",
                                                id: ev_id,
                                                table: 'class',
                                                col: 'class_id',
                                                nonce: class_admin.nonce
                                                
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
                                    
                                    if($('#name').val()) {
                                        var variables = {
                                            class_name: $('#name').val(),
                                            season_id: season_id
                                            
                                        };
                                    
                                        $.ajax({
                                            url: class_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_save",
                                                table: 'class',
                                                variables: variables,
                                                nonce: class_admin.nonce
                                                
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
                                    var class_id = td.closest('tr').attr('id');
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
                                                    url: class_admin.ajaxurl,
                                                    type: 'POST',
                                                    data: {
                                                        action: 'update_table',
                                                        table: 'class',
                                                        id: class_id,
                                                        col: 'class_id',
                                                        field: col_name,
                                                        value: newValue,
                                                        nonce: class_admin.nonce
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