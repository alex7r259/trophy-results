jQuery(function ($) {
    const cfg = sections_admin_vars;
    let seasonId, eventId, classId, sectionId;

    const scoringTypes = [
        'TIME',
        'CHECKPOINT_POINTS_TIME',
        'CHECKPOINT_COUNT_TIME',
        'CHECKPOINT_POINTS',
        'CUSTOM'
    ];

    const statuses = {
        finished: 'Финишировал',
        dnf: 'Сход',
        dsq: 'Дискв.'
    };

    function api(action, data, cb) {
        return $.post(cfg.ajaxurl, {
            action: action,
            nonce: cfg.nonce,
            ...data
        }, cb);
    }

    function msg(text) {
        $('#message').html('<div class="updated"><p>' + text + '</p></div>');
    }

    function boot() {
        api('results_install_stage_sections_schema', {}, loadSeasons);
    }

    function loadSeasons() {
        api('load_seasons', {}, function (response) {
            const seasons = response.data || [];
            seasonId = (seasons[0] || {}).season_id;

            const options = seasons.slice(1).map(function (season) {
                return '<option value="' + season.season_id + '" ' +
                    (season.season_id == seasonId ? 'selected' : '') + '>' +
                    season.season_name + '</option>';
            }).join('');

            $('#body').html(
                '<p><label>Сезон</label><br>' +
                '<select id="season">' + options + '</select></p>' +
                '<div id="eventBox"></div>' +
                '<div id="classBox"></div>' +
                '<div id="sectionsBox"></div>' +
                '<div id="resultsBox"></div>'
            );

            $('#season').on('change', function () {
                seasonId = $('#season').val();
                sectionId = null;
                loadEvents();
            });

            loadEvents();
        });
    }

    function loadEvents() {
        api('load_table', {
            table: 'events',
            col: 'season_id',
            id: seasonId
        }, function (response) {
            const events = response.data || [];
            const options = events.map(function (event) {
                return '<option value="' + event.event_id + '">' +
                    event.event_name + '</option>';
            }).join('');

            $('#eventBox').html(
                '<p><label>Этап</label><br>' +
                '<select id="event">' + options + '</select></p>'
            );

            eventId = $('#event').val();

            $('#event').on('change', function () {
                eventId = $('#event').val();
                sectionId = null;
                loadClasses();
            });

            loadClasses();
        });
    }

    function loadClasses() {
        api('load_table', {
            table: 'class',
            col: 'season_id',
            id: seasonId
        }, function (response) {
            const classes = response.data || [];
            const options = classes.map(function (item) {
                return '<option value="' + item.class_id + '">' +
                    item.class_name + '</option>';
            }).join('');

            $('#classBox').html(
                '<p><label>Категория</label><br>' +
                '<select id="class">' + options + '</select></p>'
            );

            classId = $('#class').val();

            $('#class').on('change', function () {
                classId = $('#class').val();
                sectionId = null;
                loadSections();
            });

            loadSections();
        });
    }

    function loadSections() {
        api('results_load_stage_sections', {
            event_id: eventId,
            class_id: classId
        }, function (response) {
            renderSections((response.data || {}).sections || []);
        });
    }

    function renderSections(sections) {
        const rows = sections.map(function (section) {
            return '<tr data-id="' + section.section_id + '">' +
                '<td>' + section.section_number + '</td>' +
                '<td>' + section.section_name + '</td>' +
                '<td>' + section.effective_scoring_type + '</td>' +
                '<td>' +
                    '<button type="button" class="choose">Открыть</button> ' +
                    '<button type="button" class="delete-section" data-id="' +
                        section.section_id + '">🗑 Удалить</button>' +
                '</td>' +
            '</tr>';
        }).join('');

        const typeOptions = scoringTypes.map(function (type) {
            return '<option value="' + type + '">' + type + '</option>';
        }).join('');

        $('#sectionsBox').html(
            '<h2>СУ категории</h2>' +
            '<div class="results-admin-table-scroll">' +
                '<table class="admin_table">' +
                    '<thead><tr>' +
                        '<th>№</th><th>Название</th><th>Расчёт</th><th></th>' +
                    '</tr></thead>' +
                    '<tbody>' + rows +
                        '<tr>' +
                            '<td><input id="suNum" type="number" min="1" value="1" style="width:70px"></td>' +
                            '<td><input id="suName" value="СУ-1"></td>' +
                            '<td><select id="suType">' + typeOptions + '</select></td>' +
                            '<td><button type="button" id="addSu">Добавить</button></td>' +
                        '</tr>' +
                    '</tbody>' +
                '</table>' +
            '</div>'
        );

        $('.choose').on('click', function () {
            sectionId = $(this).closest('tr').data('id');
            loadResultGrid();
        });

        $('.delete-section').on('click', function () {
            const id = $(this).data('id');

            if (!confirm('Удалить этот СУ? Все результаты СУ также будут удалены.')) {
                return;
            }

            api('results_delete_stage_section', { section_id: id }, function (response) {
                if (response.success) {
                    sectionId = null;
                    msg('СУ удалён');
                    loadSections();
                } else {
                    msg('Ошибка: ' + (response.data || 'не удалось удалить СУ'));
                }
            });
        });

        $('#addSu').on('click', function () {
            api('results_save_stage_section', {
                event_id: eventId,
                class_id: classId,
                section_number: $('#suNum').val(),
                section_name: $('#suName').val(),
                scoring_type: $('#suType').val()
            }, function (response) {
                if (response.success) {
                    sectionId = response.data.section_id;
                    msg('СУ сохранён');
                    loadSections();
                    loadResultGrid();
                } else {
                    msg('Ошибка: ' + (response.data || 'не удалось сохранить СУ'));
                }
            });
        });

        if (sections[0] && !sectionId) {
            sectionId = sections[0].section_id;
            loadResultGrid();
        }
    }

    function loadResultGrid() {
        api('results_load_stage_sections', {
            event_id: eventId,
            class_id: classId
        }, function (response) {
            const data = response.data || {};
            const participants = (data.participants || []).sort(function (a, b) {
                return (parseInt(a.num, 10) || 0) - (parseInt(b.num, 10) || 0);
            });

            const saved = {};
            (data.results || [])
                .filter(function (result) {
                    return result.section_id == sectionId;
                })
                .forEach(function (result) {
                    saved[result.participant_id] = result;
                });

            const statusOptions = Object.entries(statuses).map(function (entry) {
                return '<option value="' + entry[0] + '">' + entry[1] + '</option>';
            }).join('');

            const rows = participants.map(function (participant) {
                const result = saved[participant.participant_id] || {};

                return '<tr data-pid="' + participant.participant_id + '">' +
                    '<td>№' + participant.num + ' ' + participant.participants_name + '</td>' +
                    '<td><input class="cc" type="number" min="0" value="' + (result.checkpoints_count || 0) + '"></td>' +
                    '<td><input class="cp" type="number" min="0" step="0.01" value="' + (result.checkpoint_points || 0) + '"></td>' +
                    '<td><input class="startAt" type="datetime-local" value="' +
                        (result.start_at ? result.start_at.replace(' ', 'T').slice(0, 16) : '') + '"></td>' +
                    '<td><input class="finishAt" type="datetime-local" value="' +
                        (result.finish_at ? result.finish_at.replace(' ', 'T').slice(0, 16) : '') + '"></td>' +
                    '<td><span class="tm-display">' + (result.raw_time || '') + '</span></td>' +
                    '<td><select class="st">' + statusOptions + '</select></td>' +
                    '<td>' + (result.final_place || '') + '</td>' +
                    '<td>' + (result.final_points || '') + '</td>' +
                '</tr>';
            }).join('');

            $('#resultsBox').html(
                '<h2>Массовый ввод результатов СУ</h2>' +
                '<div class="results-admin-table-scroll">' +
                    '<table class="admin_table">' +
                        '<thead><tr>' +
                            '<th>Экипаж</th><th>КП</th><th>Баллы КП</th>' +
                            '<th>Старт</th><th>Финиш</th><th>Время</th>' +
                            '<th>Статус</th><th>Место</th><th>Очки СУ</th>' +
                        '</tr></thead>' +
                        '<tbody>' + rows + '</tbody>' +
                    '</table>' +
                '</div>' +
                '<p>' +
                    '<button type="button" id="saveRows">Сохранить и пересчитать</button> ' +
                    '<button type="button" id="recalcRows">Пересчитать результаты</button>' +
                '</p>'
            );

            $('#resultsBox tr[data-pid]').each(function () {
                const result = saved[$(this).data('pid')] || {};
                $(this).find('.st').val(result.final_status || result.status || 'finished');
            });

            $('#saveRows').on('click', saveRows);

            $('#recalcRows').on('click', function () {
                api('results_recalculate_section', {
                    section_id: sectionId,
                    class_id: classId
                }, function (result) {
                    if (result.success) {
                        msg('Пересчитано');
                        loadResultGrid();
                    } else {
                        msg('Ошибка: ' + (result.data || 'не удалось пересчитать'));
                    }
                });
            });
        });
    }

    function saveRows() {
        const rows = [];

        $('#resultsBox tr[data-pid]').each(function () {
            rows.push({
                participant_id: $(this).data('pid'),
                checkpoints_count: $(this).find('.cc').val(),
                checkpoint_points: $(this).find('.cp').val(),
                start_at: $(this).find('.startAt').val(),
                finish_at: $(this).find('.finishAt').val(),
                status: $(this).find('.st').val()
            });
        });

        api('results_save_section_results', {
            section_id: sectionId,
            class_id: classId,
            rows: rows
        }, function (response) {
            if (response.success) {
                msg('Сохранено и пересчитано');
                loadResultGrid();
            } else {
                msg('Ошибка: ' + (response.data || 'не удалось сохранить'));
            }
        });
    }

    boot();
});
