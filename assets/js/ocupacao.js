var occupancyActiveTable = null;
var occupancyOffsetX = 0;
var occupancyOffsetY = 0;

function occupancyPointerPosition(event, table) {
    var map = table.parentElement;
    var rect = map.getBoundingClientRect();
    return {
        x: event.clientX - rect.left - occupancyOffsetX,
        y: event.clientY - rect.top - occupancyOffsetY
    };
}

document.querySelectorAll('.occupancy-table').forEach(function (table) {
    table.addEventListener('pointerdown', function (event) {
        occupancyActiveTable = table;
        var tableRect = table.getBoundingClientRect();
        occupancyOffsetX = event.clientX - tableRect.left;
        occupancyOffsetY = event.clientY - tableRect.top;
        table.classList.add('dragging');
        table.setPointerCapture(event.pointerId);
        event.preventDefault();
    });
});

document.addEventListener('pointermove', function (event) {
    if (!occupancyActiveTable) {
        return;
    }
    var map = occupancyActiveTable.parentElement;
    var position = occupancyPointerPosition(event, occupancyActiveTable);
    var x = Math.max(0, Math.min(position.x, map.clientWidth - occupancyActiveTable.offsetWidth));
    var y = Math.max(0, Math.min(position.y, map.clientHeight - occupancyActiveTable.offsetHeight));
    occupancyActiveTable.style.left = x + 'px';
    occupancyActiveTable.style.top = y + 'px';
    event.preventDefault();
});

document.addEventListener('pointerup', function (event) {
    if (!occupancyActiveTable) {
        return;
    }
    var table = occupancyActiveTable;
    var map = table.parentElement;
    table.classList.remove('dragging');
    if (table.hasPointerCapture && table.hasPointerCapture(event.pointerId)) {
        table.releasePointerCapture(event.pointerId);
    }
    occupancyActiveTable = null;

    var body = new URLSearchParams();
    body.append('csrf_token', map.getAttribute('data-csrf'));
    body.append('action', 'move_daily_table');
    body.append('table_id', table.getAttribute('data-id'));
    body.append('environment_id', map.getAttribute('data-environment-id'));
    body.append('layout_date', map.getAttribute('data-layout-date'));
    body.append('x', parseInt(table.style.left, 10));
    body.append('y', parseInt(table.style.top, 10));

    fetch(map.getAttribute('data-save-url'), {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.toString()
    });
});

document.querySelectorAll('.table-picker').forEach(function (picker) {
    var form = picker.closest('form');
    var total = form.querySelector('[data-capacity-total]');
    var meter = form.querySelector('.capacity-meter');
    var partySize = parseInt(picker.getAttribute('data-party-size'), 10);

    function updateCapacity() {
        var capacity = 0;
        picker.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
            capacity += parseInt(input.getAttribute('data-seats'), 10);
        });
        total.textContent = capacity;
        meter.classList.toggle('ok', capacity >= partySize);
        meter.classList.toggle('attention', capacity < partySize);
    }

    picker.addEventListener('change', updateCapacity);
    updateCapacity();
});
