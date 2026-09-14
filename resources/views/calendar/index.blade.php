<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Channel Calendar</title>
    @vite(['resources/css/app.css'])
    <style>
        .channel-filter { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .channel-filter label { display: flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; cursor: pointer; border: 1px solid #e5e7eb; }
        .channel-filter label:hover { background-color: #f3f4f6; }
        .fc-event { cursor: pointer; }
        .tooltip { position: absolute; z-index: 50; background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); pointer-events: none; max-width: 300px; font-size: 0.875rem; }
        .tooltip .channel-name { font-weight: 600; margin-bottom: 0.25rem; }
        .tooltip .stream-title { color: #374151; }
        .tooltip .stream-time { color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem; }
        .status-badge { display: inline-block; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.625rem; font-weight: 600; text-transform: uppercase; }
        .status-live { background: #fef2f2; color: #dc2626; }
        .status-upcoming { background: #eff6ff; color: #2563eb; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Channel Calendar</h1>
            @auth
                <a href="{{ url('/admin/channels') }}" class="text-sm text-blue-600 hover:underline">管理画面</a>
            @endauth
        </div>

        <div id="channel-filter" class="channel-filter"></div>
        <div id="calendar"></div>
    </div>

    <div id="tooltip" class="tooltip" hidden></div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/ja.global.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('calendar');
        var tooltipEl = document.getElementById('tooltip');
        var filterEl = document.getElementById('channel-filter');
        var hiddenChannels = {};

        fetch('/api/channels')
            .then(function (res) { return res.json(); })
            .then(function (channels) {
                channels.forEach(function (ch) {
                    var label = document.createElement('label');
                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = true;
                    checkbox.dataset.channelId = ch.id;
                    checkbox.addEventListener('change', function () {
                        if (this.checked) {
                            delete hiddenChannels[ch.id];
                        } else {
                            hiddenChannels[ch.id] = true;
                        }
                        calendar.refetchEvents();
                    });

                    var dot = document.createElement('span');
                    dot.style.display = 'inline-block';
                    dot.style.width = '0.75rem';
                    dot.style.height = '0.75rem';
                    dot.style.borderRadius = '50%';
                    dot.style.backgroundColor = ch.color;

                    var text = document.createTextNode(ch.name);
                    label.appendChild(checkbox);
                    label.appendChild(dot);
                    label.appendChild(text);
                    filterEl.appendChild(label);
                });
            });

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'ja',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: function (info, successCallback, failureCallback) {
                fetch('/api/streams?start=' + encodeURIComponent(info.startStr) + '&end=' + encodeURIComponent(info.endStr))
                    .then(function (res) { return res.json(); })
                    .then(function (events) {
                        var filtered = events.filter(function (ev) {
                            return !hiddenChannels[ev.extendedProps.channel_id];
                        });
                        successCallback(filtered);
                    })
                    .catch(failureCallback);
            },
            eventClick: function (info) {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    window.open(info.event.url, '_blank', 'noopener,noreferrer');
                }
            },
            eventMouseEnter: function (info) {
                var props = info.event.extendedProps;
                var time = new Date(info.event.start).toLocaleString('ja-JP');
                var statusClass = props.status === 'live' ? 'status-live' : 'status-upcoming';
                var statusText = props.status === 'live' ? 'LIVE' : props.status.toUpperCase();

                tooltipEl.innerHTML =
                    '<div class="channel-name">' + escapeHtml(props.channel_name) + '</div>' +
                    '<div class="stream-title">' + escapeHtml(info.event.title) + '</div>' +
                    '<div class="stream-time">' + time + ' <span class="status-badge ' + statusClass + '">' + statusText + '</span></div>';
                tooltipEl.hidden = false;

                var rect = info.el.getBoundingClientRect();
                tooltipEl.style.top = (rect.bottom + window.scrollY + 8) + 'px';
                tooltipEl.style.left = (rect.left + window.scrollX) + 'px';
            },
            eventMouseLeave: function () {
                tooltipEl.hidden = true;
            },
            height: 'auto',
        });

        calendar.render();
    });

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
</body>
</html>
