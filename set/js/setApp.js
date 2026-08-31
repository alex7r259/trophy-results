jQuery(document).ready(function($) {
    function JQueryScript() {
    var app_admin = {
        ajaxurl: app_admin_vars.ajaxurl,
        nonce: app_admin_vars.nonce
    };
    var event_id_set;
    $.ajax({
        url: app_admin.ajaxurl,
        type: "POST",
        data: {
            action: "load_seasons",
            nonce: app_admin.nonce
        },
        beforeSend: function() {
            $("#message").html("<p>Загрузка...</p>");
        },
        success: function(response) {
            if (response.success) {
                var div = '<p><label for="season_id">Сезон:</label><br><select name="season" id="season-select">';
                var pass = '';
                event_id_set = response.data[0].event_id;
                $.each(response.data, function(index, data) {  
                    if(index === 0) {
                        pass = data.pass;
                        }else{
                    div += '<option value="' + data.season_id + '">' + data.season_name + '</option>';}
                });
                div += '</select></p><div id="set"></div>';
                $('#body').html(div);
                
                // Обработчик изменения сезона
                $('#season-select').on('change', function() {
                    var season_id = $(this).val();
                    if(!season_id) return; // Не загружаем если не выбран сезон
                    $.ajax({
                        url: app_admin.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'load_table',
                            id: season_id, 
                            table: 'events',
                            col: 'season_id',
                            nonce: app_admin.nonce
                        },
                        beforeSend: function() {
                            $('#table_events').html('<H3>Загрузка...</H3>');
                        },
                        success: function(response) {
                            if (response.success) {
                                var div = '<p><label for="event_id">Этап:</label><br><select name="event" id="event-select">';
                                $.each(response.data, function(index, data) { 
                                        div += '<option value="' + data.event_id + '">' + data.event_name + '</option>';
                                });
                                div += '</select></p><p><label for="pass">Пароль:</label><br><input type="text" id="pass" name="pass" value="' + pass + '"></p><p><button type="submit" id="save" class="button button-primary">Сохранить</button></p>';
                                $('#set').html(div);
                                $("#message").html("<div class=\"updated\"><p>Данные загружены!</p></div>");
                                
                                // Редактирование таблицы
                                $('#save').on('click', function() {
                                    var season_id = $('#season-select').val();
                                    var event_id = $('#event-select').val();
                                    var pass = $('#pass').val();
                                    var id = 1;
                                    var type;
                                    
                                    if(!season_id) return;
                                    if(!event_id) return;
                                    if(!pass) return;
                                    
                                    // AJAX-запрос для сохранения изменений
                                    $.ajax({
                                        url: app_admin.ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'update_settings',
                                            table: 'appsettings',
                                            season_id: season_id,
                                            event_id: event_id,
                                            pass: pass,
                                            nonce: app_admin.nonce
                                        },
                                        beforeSend: function() {
                                            $("#message").html('<em>Сохранение...</em>');
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                $("#message").html("<div class=\"updated\"><p>Ок!</p></div>");
                                                JQueryScript();
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
                                    $('#event-select').val(event_id_set).trigger('change');
                                }
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
                    $('#season-select').val(response.data[0].season_id).trigger('change');
                }
            } else {
                $("#message").html("<div class=\"error\"><p>Ошибка: " + response.data + "</p></div>");
            }
        },
        error: function(xhr, status, error) {
            $("#message").html("<div class=\"error\"><p>Ошибка AJAX: " + error + "</p></div>");
        }
    });
    }
    JQueryScript();
});