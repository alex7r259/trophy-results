jQuery(document).ready(function($) {
    var part_admin = {
        ajaxurl: part_admin_vars.ajaxurl,
        nonce: part_admin_vars.nonce
    };
    
    function SortByName(a, b){
        var aName = a.participants_name.toLowerCase();
        var bName = b.participants_name.toLowerCase();
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    }
    
    $.ajax({
        url: part_admin.ajaxurl,
        type: "POST",
        data: {
            action: "load_seasons",
            nonce: part_admin.nonce
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
                        url: part_admin.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'load_table',
                            id: season_id, 
                            table: 'participants',
                            col: 'season_id',
                            nonce: part_admin.nonce
                        },
                        beforeSend: function() {
                            $('#table_events').html('<H3>Загрузка...</H3>');
                        },
                        success: function(response) {
                            if (response.success) {
                                var div = '<table id="data_events" class="admin_table"><thead><tr><th>п/п</th><th name="participants_name">Фамилия имя пилота/штурмана</th><th name="city">Город</th><th name="car">Авто</th><th name="num">Стартовый номер</th><th name="delete"></th></tr></thead><tbody>';
                                response.data.sort(SortByName);
                                $.each(response.data, function(index, data) {
                                    div += '<tr id="' + data.participant_id + '">' +
                                           '<td>' + (index + 1) + '</td>' +
                                           '<td id="name' + data.participant_id + '">' + data.participants_name + '</td>' +
                                           '<td id="city' + data.participant_id + '">' + (data.city || '') + '</td>' +
                                           '<td id="car' + data.participant_id + '">' + data.car + '</td>' +
                                           '<td id="num' + data.participant_id + '">' + data.num + '</td>' +
                                           '<td id="del' + data.participant_id + '">🗑</td>' +
                                           '</tr>';
                                });
                                
                                div += '<tr><td>+</td><td id="foot"><input type="text" id="name" style="line-height: 1; min-height: 20px; max-width: 150px;"></td><td id="foot"><input type="text" id="city" style="line-height: 1; min-height: 20px; max-width: 120px;"></td><td id="foot"><input type="text" id="car" style="line-height: 1; min-height: 20px; max-width: 100px;"></td><td id="foot"><input type="number" min="1" id="num" style="line-height: 1; min-height: 20px; max-width: 70px;"></td><td id="foot"><p id="save_new">💾</p></td></tr>';
                                
                                div += '</tbody></table>';
                                $('#table_events').html(div);
                                $("#message").html("<div class=\"updated\"><p>Этапы сезона загружены!</p></div>");
                                $("#message").delay(3000).html("");
                                
                                // Обработка удаления
                                $('[id^="del"]').on("click", function() {
                                    var ev_id = $(this).closest('tr').attr('id');
                                    if(confirm("Вы уверены, что хотите удалить этот этап?")) {
                                        $.ajax({
                                            url: part_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_delete",
                                                id: ev_id,
                                                table: 'participants',
                                                col: 'participant_id',
                                                nonce: part_admin.nonce
                                                
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
                                    
                                    if($('#name').val() && $('#car').val() && $('#num').val()) {
                                        var variables = {
                                            participants_name: $('#name').val(),
                                            season_id: season_id,
                                            city: $('#city').val(),
                                            car: $('#car').val(),
                                            num: $('#num').val()
                                            
                                        };
                                    
                                        $.ajax({
                                            url: part_admin.ajaxurl,
                                            type: "POST",
                                            data: {
                                                action: "col_save",
                                                table: 'participants',
                                                variables: variables,
                                                nonce: part_admin.nonce
                                                
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
                                    var participant_id = td.closest('tr').attr('id');
                                    var col_name = td.closest('table').find('th').eq($(td).index()).attr('name');
                                    var id = td.attr('id');
                                    var currentValue = td.text();
                                    var type;
                                    var min;
                                    
                                    if (col_name == 'participants_name' || col_name == 'city' || col_name == 'car'){
                                        type = 'text';
                                    }else if(col_name == 'num'){
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
                                                    url: part_admin.ajaxurl,
                                                    type: 'POST',
                                                    data: {
                                                        action: 'update_table',
                                                        table: 'participants',
                                                        id: participant_id,
                                                        col: 'participant_id',
                                                        field: col_name,
                                                        value: newValue,
                                                        nonce: part_admin.nonce
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