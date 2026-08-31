jQuery(document).ready(function($) {
    var season_admin = {
        ajaxurl: season_admin_vars.ajaxurl,
        nonce: season_admin_vars.nonce
    };
    
    $.ajax({
        url: season_admin.ajaxurl,
        type: "POST",
        data: {
            action: "load_seasons",
            nonce: season_admin.nonce
        },
        beforeSend: function() {
            $("#message").html("<p>Загрузка...</p>");
        },
        success: function(response) {
                            if (response.success) {
                                div = '<div id="table_events" style="margin-top:30px; overflow-y: scroll;"></div>';
                                $('#body').html(div);
                                var div = '<table id="data_events" class="admin_table"><thead><tr><th>id</th><th name="season_name">Год</th><th name="countPlace">Кол-во этапов для зачета</th><th name="countPart">Кол-во участников для зачета этапа</th><th name="delete"></th></tr></thead><tbody>';

                                $.each(response.data, function(index, data) {
                                    if (index === 0){
                                        
                                    }else{
                                        div += '<tr id="' + data.season_id + '">' +
                                        '<td id="foot">' + data.season_id + '</td>' +
                                        '<td id="name' + data.season_id + '">' + data.season_name + '</td>' +
                                        '<td id="place' + data.season_id + '">' + data.countPlace + '</td>' +
                                        '<td id="part' + data.season_id + '">' + data.countPart + '</td>' +
                                        '<td id="del' + data.season_id + '">🗑</td>' +
                                        '</tr>';
                                    }
                                });
                                
                                div += '<tr><td id="foot"></td><td id="foot"><input type="number" id="name" style="line-height: 1; min-height: 20px; max-width: 70px;"></td><td id="foot"><input type="number" style="line-height: 1; min-height: 20px; max-width: 70px;" min="1" id="countPlace"></td><td id="foot"><input type="number" style="line-height: 1; min-height: 20px; max-width: 70px;" min="1" id="countPart"></td><td id="foot"><p id="save_new">💾</p></td></tr>';
                                
                                div += '</tbody></table>';
                                $('#table_events').html(div);
                                $("#message").html("<div class=\"updated\"><p>Этапы сезона загружены!</p></div>");
                                $("#message").delay(3000).html("");
                                
                                // Обработка удаления
                                $('[id^="del"]').on("click", function() {
                                    var ev_id = $(this).closest('tr').attr('id');
                                    if(confirm("Вы уверены, что хотите удалить этот этап?")) {
                                        $.ajax({
                                            url: season_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_delete",
                                                id: ev_id,
                                                table: 'seasons',
                                                col: 'season_id',
                                                nonce: season_admin.nonce
                                                
                                            },
                                            beforeSend: function() {
                                                $("#message").html("<p>Удаление...</p>");
                                                
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    $("#message").html("<div class=\"updated\"><p>Удален!</p></div>");
                                                    location.reload();
                                                    
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
                                    
                                    if($('#name').val() && $('#countPlace').val() && $('#countPart').val()) {
                                        var variables = {
                                            season_name: $('#name').val(),
                                            countPlace: $('#countPlace').val(),
                                            countPart: $('#countPart').val()
                                            
                                        };
                                    
                                        $.ajax({
                                            url: season_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_save",
                                                table: 'seasons',
                                                variables: variables,
                                                nonce: season_admin.nonce
                                                
                                            },
                                            beforeSend: function() {
                                                $("#message").html("<p>Сохранение...</p>");
                                                
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    $("#message").html("<div class=\"updated\"><p>Ок!</p></div>");
                                                    $("#message").delay(3000).html("");
                                                    location.reload();
                                                    
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
                                    var season_id = td.closest('tr').attr('id');
                                    var col_name = td.closest('table').find('th').eq($(td).index()).attr('name');
                                    var id = td.attr('id');
                                    var currentValue = td.text();
                                    var type;
                                    var min;
                                    
                                    if (col_name == 'season_name'){
                                        type = 'text';
                                    }else if(col_name == 'countPlace' || col_name == 'countPart'){
                                        type = 'number';
                                        min = '1';
                                    }
                                    
                                    if (id == 'foot'){
                                        return true;
                                    }
                                    
                                    // Создаем input для редактирования
                                    var input = $('<input>', {
                                        type: type,
                                        min: min,
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
                                                    url: season_admin.ajaxurl,
                                                    type: 'POST',
                                                    data: {
                                                        action: 'update_table',
                                                        table: 'seasons',
                                                        id: season_id,
                                                        col: 'season_id',
                                                        field: col_name,
                                                        value: newValue,
                                                        nonce: season_admin.nonce
                                                    },
                                                    beforeSend: function() {
                                                        $("#message").html('<em>Сохранение...</em>');
                                                    },
                                                    success: function(response) {
                                                        if (response.success) {
                                                            td.text(newValue); // Обновляем значение в таблице
                                                            $("#message").html("<div class=\"updated\"><p>Ок!</p></div>");
                                                            $("#message").delay(3000).html("");
                                                            location.reload();
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