var activeTable = null;
var offsetX = 0;
var offsetY = 0;

document.querySelectorAll('.map-table').forEach(function (table) {
    table.addEventListener('mousedown', function (event) {
        activeTable = table;
        offsetX = event.clientX - table.offsetLeft;
        offsetY = event.clientY - table.offsetTop;
        table.classList.add('dragging');
    });
});

document.addEventListener('mousemove', function (event) {
    if (!activeTable) {
        return;
    }
    var map = activeTable.parentElement;
    var x = event.clientX - offsetX;
    var y = event.clientY - offsetY;
    x = Math.max(0, Math.min(x, map.clientWidth - activeTable.offsetWidth));
    y = Math.max(0, Math.min(y, map.clientHeight - activeTable.offsetHeight));
    activeTable.style.left = x + 'px';
    activeTable.style.top = y + 'px';
});

document.addEventListener('mouseup', function () {
    if (!activeTable) {
        return;
    }
    var table = activeTable;
    var map = table.parentElement;
    table.classList.remove('dragging');
    activeTable = null;

    var body = new URLSearchParams();
    body.append('csrf_token', map.getAttribute('data-csrf'));
    body.append('action', 'move_table');
    body.append('table_id', table.getAttribute('data-id'));
    body.append('x', parseInt(table.style.left, 10));
    body.append('y', parseInt(table.style.top, 10));

    fetch(map.getAttribute('data-save-url'), {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body.toString()
    });
});
