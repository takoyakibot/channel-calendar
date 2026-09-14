<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $group ? $group->name . ' | Channel Calendar' : 'Channel Calendar' }}</title>
    @vite(['resources/css/app.css'])
    <style>
        .channel-filter { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .channel-filter label { display: flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; cursor: pointer; border: 1px solid #e5e7eb; background: #fff; }
        .channel-filter label:hover { background-color: #f3f4f6; }
        .channel-filter img { width: 1.25rem; height: 1.25rem; border-radius: 50%; object-fit: cover; }

        .view-toggle { display: inline-flex; border: 1px solid #d1d5db; border-radius: 0.5rem; overflow: hidden; background: #fff; }
        .view-toggle button { padding: 0.375rem 0.875rem; font-size: 0.875rem; color: #374151; background: transparent; border: 0; cursor: pointer; }
        .view-toggle button + button { border-left: 1px solid #d1d5db; }
        .view-toggle button.is-active { background: #111827; color: #fff; }

        .board-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
        .board-toolbar .nav { display: inline-flex; gap: 0.25rem; }
        .board-toolbar .nav button { padding: 0.375rem 0.75rem; font-size: 0.875rem; border: 1px solid #d1d5db; border-radius: 0.375rem; background: #fff; color: #374151; cursor: pointer; }
        .board-toolbar .nav button:hover { background: #f3f4f6; }
        .board-toolbar .range { font-weight: 600; color: #111827; }

        .board { display: grid; grid-template-columns: repeat(7, minmax(220px, 1fr)); gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem; scroll-snap-type: x proximity; }
        .day-col { background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; display: flex; flex-direction: column; min-height: 12rem; scroll-snap-align: start; }
        .day-col.is-today { border-color: #111827; box-shadow: 0 0 0 1px #111827 inset; }
        .day-col.is-past { opacity: 0.7; }
        .day-head { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: baseline; gap: 0.375rem; }
        .day-head .d { font-size: 1.125rem; font-weight: 700; color: #111827; }
        .day-head .w { font-size: 0.8125rem; color: #6b7280; }
        .day-head .w.sun { color: #dc2626; }
        .day-head .w.sat { color: #2563eb; }
        .day-head .today-tag { margin-left: auto; font-size: 0.625rem; font-weight: 700; color: #fff; background: #111827; padding: 0.125rem 0.5rem; border-radius: 9999px; }
        .day-body { padding: 0.5rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .day-body .empty { color: #9ca3af; font-size: 0.8125rem; text-align: center; padding: 1rem 0; }

        .card { display: block; text-decoration: none; color: inherit; border: 1px solid #e5e7eb; border-left: 4px solid #9ca3af; border-radius: 0.375rem; padding: 0.5rem 0.625rem; background: #fff; }
        .card:hover { background: #f9fafb; border-color: #d1d5db; }
        .card.is-done { opacity: 0.55; }
        .card-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.375rem; }
        .avatar { width: 2rem; height: 2rem; border-radius: 50%; object-fit: cover; flex: none; background: #e5e7eb; }
        .avatar-fallback { width: 2rem; height: 2rem; border-radius: 50%; flex: none; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.875rem; }
        .card-meta { min-width: 0; display: flex; flex-direction: column; }
        .card-meta .time { font-weight: 700; font-size: 0.9375rem; color: #111827; line-height: 1.2; }
        .card-meta .ch { font-size: 0.75rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-title { font-size: 0.8125rem; line-height: 1.4; color: #1f2937; white-space: normal; overflow-wrap: anywhere; }
        .badge { display: inline-block; padding: 0.0625rem 0.4rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 700; margin-left: auto; flex: none; }
        .badge.live { background: #dc2626; color: #fff; }
        .badge.done { background: #e5e7eb; color: #6b7280; }

        .fc-event { cursor: pointer; }
        .fc-ev { display: flex; align-items: center; gap: 0.25rem; overflow: hidden; padding: 0 0.125rem; }
        .fc-ev img, .fc-ev .fc-ev-dot { width: 1rem; height: 1rem; border-radius: 50%; flex: none; object-fit: cover; }
        .fc-ev .fc-ev-time { font-weight: 700; font-size: 0.7rem; color: #111827; flex: none; }
        .fc-ev .fc-ev-title { font-size: 0.7rem; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fc-ev.is-done { opacity: 0.55; }

        .tooltip { position: absolute; z-index: 50; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); pointer-events: none; max-width: 320px; font-size: 0.875rem; }
        .tooltip .channel-name { font-weight: 600; margin-bottom: 0.25rem; }
        .tooltip .stream-title { color: #374151; overflow-wrap: anywhere; }
        .tooltip .stream-time { color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
            <div class="flex items-baseline gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900">{{ $group ? $group->name : 'Channel Calendar' }}</h1>
                @if ($group)
                    <a href="{{ url('/') }}" class="text-sm text-gray-500 hover:underline">すべてのチャンネル</a>
                @endif
            </div>
            <div class="flex items-center gap-4">
                <div class="view-toggle" role="tablist">
                    <button type="button" data-view="board" class="is-active">週ボード</button>
                    <button type="button" data-view="month">月</button>
                </div>
                @auth
                    <a href="{{ url('/admin/channels') }}" class="text-sm text-blue-600 hover:underline">管理画面</a>
                @endauth
            </div>
        </div>

        <div id="channel-filter" class="channel-filter"></div>

        <section id="board-view">
            <div class="board-toolbar">
                <div class="nav">
                    <button type="button" id="board-prev">← 前の週</button>
                    <button type="button" id="board-today">今日</button>
                    <button type="button" id="board-next">次の週 →</button>
                </div>
                <div class="range" id="board-range"></div>
            </div>
            <div id="board" class="board"></div>
        </section>

        <section id="month-view" hidden>
            <div id="calendar"></div>
        </section>
    </div>

    <div id="tooltip" class="tooltip" hidden></div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/ja.global.min.js"></script>
    <script>
    var GROUP_SLUG = @json($group?->slug);

    function apiUrl(path, params) {
        var parts = Object.keys(params).map(function (k) {
            return k + '=' + encodeURIComponent(params[k]);
        });
        if (GROUP_SLUG) {
            parts.push('group=' + encodeURIComponent(GROUP_SLUG));
        }
        return path + '?' + parts.join('&');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];
        var BOARD_DAYS = 7;

        var boardEl = document.getElementById('board');
        var rangeEl = document.getElementById('board-range');
        var calendarEl = document.getElementById('calendar');
        var tooltipEl = document.getElementById('tooltip');
        var filterEl = document.getElementById('channel-filter');
        var boardView = document.getElementById('board-view');
        var monthView = document.getElementById('month-view');
        var toggleButtons = document.querySelectorAll('.view-toggle button');

        var hiddenChannels = {};
        var boardStart = startOfDay(new Date());
        var boardEvents = [];
        var calendar = null;

        function startOfDay(d) {
            return new Date(d.getFullYear(), d.getMonth(), d.getDate());
        }
        function addDays(d, n) {
            return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
        }
        function dateKey(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
        function fmtTime(d) {
            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }
        function fmtShort(d) {
            return (d.getMonth() + 1) + '/' + d.getDate() + '(' + WEEKDAYS[d.getDay()] + ')';
        }
        function isVisible(ev) {
            return !hiddenChannels[ev.extendedProps.channel_id];
        }

        function avatarNode(props, color, size) {
            if (props.channel_thumbnail_url) {
                var img = document.createElement('img');
                img.className = size;
                img.src = props.channel_thumbnail_url;
                img.alt = '';
                img.loading = 'lazy';
                return img;
            }
            var span = document.createElement('span');
            span.className = size === 'avatar' ? 'avatar-fallback' : 'fc-ev-dot';
            span.style.backgroundColor = color || '#9ca3af';
            if (size === 'avatar') {
                span.textContent = (props.channel_name || '?').charAt(0);
            }
            return span;
        }

        function buildCard(ev) {
            var props = ev.extendedProps;
            var start = new Date(ev.start);
            var a = document.createElement('a');
            a.className = 'card' + (props.status === 'completed' ? ' is-done' : '');
            a.href = ev.url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.style.borderLeftColor = ev.color || '#9ca3af';

            var head = document.createElement('div');
            head.className = 'card-head';
            head.appendChild(avatarNode(props, ev.color, 'avatar'));

            var meta = document.createElement('div');
            meta.className = 'card-meta';
            var time = document.createElement('span');
            time.className = 'time';
            time.textContent = fmtTime(start);
            var ch = document.createElement('span');
            ch.className = 'ch';
            ch.textContent = props.channel_name;
            meta.appendChild(time);
            meta.appendChild(ch);
            head.appendChild(meta);

            if (props.status === 'live') {
                var live = document.createElement('span');
                live.className = 'badge live';
                live.textContent = 'LIVE';
                head.appendChild(live);
            } else if (props.status === 'completed') {
                var done = document.createElement('span');
                done.className = 'badge done';
                done.textContent = '終了';
                head.appendChild(done);
            }

            var title = document.createElement('div');
            title.className = 'card-title';
            title.textContent = ev.title;

            a.appendChild(head);
            a.appendChild(title);
            return a;
        }

        function renderBoard() {
            boardEl.textContent = '';
            var todayKey = dateKey(new Date());
            var byDay = {};
            boardEvents.filter(isVisible).forEach(function (ev) {
                var key = dateKey(new Date(ev.start));
                (byDay[key] = byDay[key] || []).push(ev);
            });

            for (var i = 0; i < BOARD_DAYS; i++) {
                var day = addDays(boardStart, i);
                var key = dateKey(day);
                var col = document.createElement('div');
                col.className = 'day-col' + (key === todayKey ? ' is-today' : '') + (key < todayKey ? ' is-past' : '');

                var head = document.createElement('div');
                head.className = 'day-head';
                var d = document.createElement('span');
                d.className = 'd';
                d.textContent = (day.getMonth() + 1) + '/' + day.getDate();
                var w = document.createElement('span');
                w.className = 'w' + (day.getDay() === 0 ? ' sun' : day.getDay() === 6 ? ' sat' : '');
                w.textContent = WEEKDAYS[day.getDay()];
                head.appendChild(d);
                head.appendChild(w);
                if (key === todayKey) {
                    var tag = document.createElement('span');
                    tag.className = 'today-tag';
                    tag.textContent = '今日';
                    head.appendChild(tag);
                }
                col.appendChild(head);

                var body = document.createElement('div');
                body.className = 'day-body';
                var list = (byDay[key] || []).slice().sort(function (a, b) {
                    return new Date(a.start) - new Date(b.start);
                });
                if (list.length === 0) {
                    var empty = document.createElement('div');
                    empty.className = 'empty';
                    empty.textContent = '予定なし';
                    body.appendChild(empty);
                } else {
                    list.forEach(function (ev) { body.appendChild(buildCard(ev)); });
                }
                col.appendChild(body);
                boardEl.appendChild(col);
            }

            rangeEl.textContent = fmtShort(boardStart) + ' 〜 ' + fmtShort(addDays(boardStart, BOARD_DAYS - 1));
        }

        function loadBoard() {
            var end = addDays(boardStart, BOARD_DAYS);
            fetch(apiUrl('/api/streams', { start: boardStart.toISOString(), end: end.toISOString() }), { headers: { Accept: 'application/json' } })
                .then(function (res) { if (!res.ok) { throw new Error(res.status); } return res.json(); })
                .then(function (events) { boardEvents = events; renderBoard(); })
                .catch(function () { boardEvents = []; renderBoard(); });
        }

        document.getElementById('board-prev').addEventListener('click', function () {
            boardStart = addDays(boardStart, -BOARD_DAYS);
            loadBoard();
        });
        document.getElementById('board-next').addEventListener('click', function () {
            boardStart = addDays(boardStart, BOARD_DAYS);
            loadBoard();
        });
        document.getElementById('board-today').addEventListener('click', function () {
            boardStart = startOfDay(new Date());
            loadBoard();
        });

        function ensureCalendar() {
            if (calendar) { return; }
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'ja',
                headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                dayMaxEvents: false,
                displayEventTime: false,
                events: function (info, successCallback, failureCallback) {
                    fetch(apiUrl('/api/streams', { start: info.startStr, end: info.endStr }), { headers: { Accept: 'application/json' } })
                        .then(function (res) { if (!res.ok) { throw new Error(res.status); } return res.json(); })
                        .then(function (events) { successCallback(events.filter(isVisible)); })
                        .catch(failureCallback);
                },
                eventContent: function (arg) {
                    var props = arg.event.extendedProps;
                    var wrap = document.createElement('div');
                    wrap.className = 'fc-ev' + (props.status === 'completed' ? ' is-done' : '');
                    wrap.appendChild(avatarNode(props, arg.event.backgroundColor || arg.event.borderColor, 'fc-ev-img'));
                    var t = document.createElement('span');
                    t.className = 'fc-ev-time';
                    t.textContent = props.status === 'live' ? 'LIVE' : fmtTime(arg.event.start);
                    if (props.status === 'live') { t.style.color = '#dc2626'; }
                    var ti = document.createElement('span');
                    ti.className = 'fc-ev-title';
                    ti.textContent = arg.event.title;
                    wrap.appendChild(t);
                    wrap.appendChild(ti);
                    return { domNodes: [wrap] };
                },
                eventClick: function (info) {
                    info.jsEvent.preventDefault();
                    if (info.event.url) {
                        window.open(info.event.url, '_blank', 'noopener,noreferrer');
                    }
                },
                eventMouseEnter: function (info) {
                    var props = info.event.extendedProps;
                    tooltipEl.textContent = '';
                    var name = document.createElement('div');
                    name.className = 'channel-name';
                    name.textContent = props.channel_name;
                    var title = document.createElement('div');
                    title.className = 'stream-title';
                    title.textContent = info.event.title;
                    var time = document.createElement('div');
                    time.className = 'stream-time';
                    time.textContent = new Date(info.event.start).toLocaleString('ja-JP') + (props.status === 'live' ? '  LIVE' : props.status === 'completed' ? '  終了' : '');
                    tooltipEl.appendChild(name);
                    tooltipEl.appendChild(title);
                    tooltipEl.appendChild(time);
                    tooltipEl.hidden = false;
                    var rect = info.el.getBoundingClientRect();
                    tooltipEl.style.top = (rect.bottom + window.scrollY + 8) + 'px';
                    tooltipEl.style.left = (rect.left + window.scrollX) + 'px';
                },
                eventMouseLeave: function () { tooltipEl.hidden = true; },
                height: 'auto',
            });
            calendar.render();
        }

        function setView(view) {
            var isBoard = view !== 'month';
            boardView.hidden = !isBoard;
            monthView.hidden = isBoard;
            toggleButtons.forEach(function (b) { b.classList.toggle('is-active', b.dataset.view === (isBoard ? 'board' : 'month')); });
            if (!isBoard) {
                ensureCalendar();
                calendar.updateSize();
            }
            try { localStorage.setItem('cc.view', isBoard ? 'board' : 'month'); } catch (e) {}
        }
        toggleButtons.forEach(function (b) {
            b.addEventListener('click', function () { setView(b.dataset.view); });
        });

        fetch(apiUrl('/api/channels', {}), { headers: { Accept: 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (channels) {
                channels.forEach(function (ch) {
                    var label = document.createElement('label');
                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = true;
                    checkbox.addEventListener('change', function () {
                        if (this.checked) { delete hiddenChannels[ch.id]; } else { hiddenChannels[ch.id] = true; }
                        renderBoard();
                        if (calendar) { calendar.refetchEvents(); }
                    });
                    label.appendChild(checkbox);
                    if (ch.thumbnail_url) {
                        var img = document.createElement('img');
                        img.src = ch.thumbnail_url;
                        img.alt = '';
                        label.appendChild(img);
                    } else {
                        var dot = document.createElement('span');
                        dot.style.cssText = 'display:inline-block;width:.75rem;height:.75rem;border-radius:50%;background:' + ch.color;
                        label.appendChild(dot);
                    }
                    label.appendChild(document.createTextNode(ch.name));
                    filterEl.appendChild(label);
                });
            });

        var savedView = 'board';
        try { savedView = localStorage.getItem('cc.view') || 'board'; } catch (e) {}
        setView(savedView);
        loadBoard();
    });
    </script>
</body>
</html>
