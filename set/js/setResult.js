jQuery(document).ready(function($) {
    const result_admin = {
        ajaxurl: result_admin_vars.ajaxurl,
        nonce: result_admin_vars.nonce
    };
    
    let season_id_set, event_id_set, class_id_set;
    let season_id, event_id;

    function SortByName(a, b){
        var aName = parseInt(a.num.toLowerCase());
        var bName = parseInt(b.num.toLowerCase());
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    }

    function showMessage(type, text, delay = 3000) {
        const message = `<div class="${type}"><p>${text}</p></div>`;
        $("#message").html(message);
        if (delay) {
            $("#message").delay(delay).html("");
        }
    }

    function loadData(action, data, successCallback, targetSelector = "#message") {
        const requestData = {
            action: action,
            nonce: result_admin.nonce,
            ...data
        };

        return $.ajax({
            url: result_admin.ajaxurl,
            type: "POST",
            data: requestData,
            beforeSend: function() {
                $(targetSelector).html("<p>Загрузка...</p>");
            },
            success: successCallback,
            error: function(xhr, status, error) {
                showMessage("error", `Ошибка AJAX: ${error}`);
            }
        });
    }

    // Загрузка сезонов
    function loadSeasons() {
        loadData("load_seasons", {}, function(response) {
            if (!response.success) {
                showMessage("error", `Ошибка: ${response.data}`);
                return;
            }

            const seasons = response.data;
            if (!seasons || !seasons.length) {
                showMessage("error", "Нет доступных сезонов");
                return;
            }

            // Первый элемент - текущие настройки
            season_id_set = seasons[0].season_id;
            event_id_set = seasons[0].event_id;
            season_id = season_id_set;

            // Формируем options, пропуская первый элемент
            let options = '';
            for (let i = 1; i < seasons.length; i++) {
                if(seasons[i].season_id == season_id_set){
                options += `<option value="${seasons[i].season_id}" selected>${seasons[i].season_name}</option>`;
                    
                }else{
                options += `<option value="${seasons[i].season_id}">${seasons[i].season_name}</option>`;
                    
                }
            }

            const div = `
                <p>
                    <label for="season-select">Сезон:</label><br>
                    <select name="season" id="season-select">
                        ${options}
                    </select>
                </p>
                <div id="sel"></div>
            `;

            $('#body').html(div);
            showMessage("updated", "Сезоны серии загружены!");

            $('#season-select').on('change', function() {
                season_id = $(this).val();
                loadEvents(season_id, event_id_set);
            });

            // Загружаем события для текущего сезона
            loadEvents(season_id_set, event_id_set);
            
        });
    }

    // Загрузка этапов
    function loadEvents(seasonId, eventIdSet) {
        loadData("load_table", {
            id: seasonId,
            table: 'events',
            col: 'season_id'
        }, function(response) {
            if (!response.success) {
                showMessage("error", `Ошибка: ${response.data}`);
                return;
            }

            const events = response.data;
            if (!events || !events.length) {
                $('#sel').html('<p>Нет доступных этапов</p>');
                return;
            }
            
            // Формируем options
            let options = '';
            for (let i = 0; i < events.length; i++) {
                if(events[i].event_id == eventIdSet){
                    options += `<option value="${events[i].event_id}" selected>${events[i].event_name}</option>`;
                }else{
                options += `<option value="${events[i].event_id}">${events[i].event_name}</option>`;}
            }

            const div = `
                <p>
                    <label for="event-select">Этап:</label><br>
                    <select name="event" id="event-select">
                        ${options}
                    </select>
                </p>
                <div id="class"></div>
            `;

            $('#sel').html(div);
            showMessage("updated", "Этапы сезона загружены!");

            $('#event-select').on('change', function() {
                event_id = $(this).val();
                loadClasses(seasonId, event_id);
            });
            
            event_id = $('#event-select').val();
            // Загружаем классы для текущего этапа
            loadClasses(seasonId, event_id);
        });
    }

    // Загрузка классов
    function loadClasses(seasonId, eventId) {
        loadData("load_table", {
            id: seasonId,
            table: 'class',
            col: 'season_id'
        }, function(response) {
            if (!response.success) {
                showMessage("error", `Ошибка: ${response.data}`);
                return;
            }

            const classes = response.data;
            if (!classes || !classes.length) {
                $('#class').html('<p>Нет доступных классов</p>');
                return;
            }

            // Формируем options (все элементы, первый выбран по умолчанию)
            let options = '';
            for (let i = 0; i < classes.length; i++) {
                const selected = i === 0 ? ' selected' : '';
                options += `<option value="${classes[i].class_id}"${selected}>${classes[i].class_name}</option>`;
            }

            const div = `
                <p>
                    <label for="class-select">Класс:</label><br>
                    <select name="class" id="class-select">${options}</select>
                </p>
                <div id="table_events" style="margin-top:30px; overflow-y: scroll;"></div>
            `;

            $('#class').html(div);
            showMessage("updated", "Классы загружены!");

            $('#class-select').on('change', function() {
                const class_id = $(this).val();
                if (seasonId && event_id && class_id) {
                    loadResults(seasonId, event_id, class_id);
                }
            });

            // Загружаем результаты для первого класса
            if (classes.length > 0) {
                loadResults(seasonId, eventId, classes[0].class_id);
            }
        });
    }

    // Загрузка результатов
    function loadResults(seasonId, eventId, classId) {
        loadData("load_table_result", {
            season_id: seasonId,
            event_id: eventId,
            class_id: classId
        }, function(response) {
            if (!response.success) {
                showMessage("error", `Ошибка: ${response.data}`);
                return;
            }

            const results = response.data;

            // Параллельно загружаем участников и таблицу очков
            $.when(
                loadData("load_table", {
                    id: seasonId,
                    table: 'participants',
                    col: 'season_id'
                }, null, "#table_events"),
                loadData("load_table", {
                    id: seasonId,
                    table: 'pointstable',
                    col: 'season_id'
                }, null, "#table_events")
            ).done(function(participantsResp, pointsResp) {
                const participants = participantsResp[0].success ? participantsResp[0].data : [];
                participants.sort(SortByName);
                const points = pointsResp[0].success ? pointsResp[0].data : [];
                
                renderResultsTable(results, participants, points, seasonId, eventId, classId);
            });
        }, "#table_events");
    }

    // Рендер таблицы результатов
    function renderResultsTable(results, participants, points, seasonId, eventId, classId) {
        // Строки таблицы
        let rows = '';
        for (let i = 0; i < results.length; i++) {
            const result = results[i];
            let pos = result.position;
            if (result.missing){ pos = "Сход"; }
            if (result.disq){ pos = "Дискв."; }
            rows += `
                <tr id="${result.results_id}">
                    <td>${pos}</td>
                    <td id="name${result.results_id}">${result.participants_name}</td>
                    <td id="car${result.results_id}">${result.car}</td>
                    <td id="num${result.results_id}">${result.num}</td>
                    <td id="del${result.results_id}">🗑</td>
                </tr>
            `;
        }

        // Опции для select участников
        let participantsOptions = '';
        for (let i = 0; i < participants.length; i++) {
            participantsOptions += `<option value="${participants[i].participant_id}">№${participants[i].num} ${participants[i].participants_name}</option>`;
        }

        // Опции для select позиций
        let pointsOptions = '';
        for (let i = 0; i < points.length; i++) {
            pointsOptions += `<option value="${points[i].points_id}">${points[i].position}</option>`;
        }

        const div = `
            <table id="data_events" class="admin_table">
                <thead>
                    <tr>
                        <th>Место</th>
                        <th name="participants_name">Фамилия имя пилота/штурмана</th>
                        <th name="car">Авто</th>
                        <th name="num">Стартовый номер</th>
                        <th name="delete"></th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                    <tr>
                        <td id="points_id">
                            <select name="posi" id="posi-select" style="line-height: 1; min-height: 20px; max-width: 70px;">${pointsOptions}</select>
                        </td>
                        <td id="participant_id">
                            <select name="participants" id="participants-select" style="line-height: 1; min-height: 20px; max-width: 150px;">${participantsOptions}</select>
                        </td>
                        <td><p><input type="checkbox" id="missing" name="missing"><label for="missing">Сход</label></p><p><input type="checkbox" id="disq" name="disq"><label for="disq">Дискв.</label></p></td>
                        <td></td>
                        <td id="foot"><p id="save_new">💾</p></td>
                    </tr>
                </tbody>
            </table>
        `;

        $('#table_events').html(div);
        showMessage("updated", "Результаты загружены!");

        // Настройка обработчиков событий
        setupEventHandlers(seasonId, eventId, classId);
    }

    // Настройка обработчиков событий
    function setupEventHandlers(seasonId, eventId, classId) {
        // Удаление записи
        $('[id^="del"]').on("click", function() {
            const resultId = $(this).closest('tr').attr('id');
            if (confirm("Вы уверены, что хотите удалить этот результат?")) {
                loadData("col_delete", {
                    id: resultId,
                    table: 'results',
                    col: 'results_id'
                }, function(response) {
                    if (response.success) {
                        showMessage("updated", "Результат удален!");
                        loadResults(seasonId, eventId, classId);
                    } else {
                        showMessage("error", `Ошибка: ${response.data}`);
                    }
                });
            }
        });

        // Сохранение новой записи
        $('#save_new').on("click", function() {
            
            let missing = $('#missing').is(':checked') ? 1 : 0;
            let disq = $('#disq').is(':checked') ? 1 : 0;
            if (missing || disq) { $('#posi-select option:contains("-")').prop('selected', true); }
            
            const participantVal = $('#participants-select').val();
            const posiVal = $('#posi-select').val();

            if (!participantVal || !posiVal) {
                showMessage("error", "Заполните все поля!");
                return;
            }

            const variables = {
                season_id: seasonId,
                event_id: eventId,
                class_id: classId,
                participant_id: participantVal,
                points_id: posiVal,
                missing: missing,
                disq: disq
            };

            loadData("col_save", {
                table: 'results',
                variables: variables
            }, function(response) {
                if (response.success) {
                    showMessage("updated", "Результат сохранен!");
                    loadResults(seasonId, eventId, classId);
                } else {
                    showMessage("error", `Ошибка: ${response.data}`);
                }
            });
        });
    }

    // Инициализация
    loadSeasons();
});