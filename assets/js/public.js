var occasionSelect = document.getElementById('occasionSelect');
var birthdayFields = document.getElementById('birthdayFields');

function syncBirthdayFields() {
    if (!occasionSelect || !birthdayFields) {
        return;
    }
    var selected = occasionSelect.options[occasionSelect.selectedIndex];
    birthdayFields.style.display = selected && selected.getAttribute('data-birthday') === '1' ? 'grid' : 'none';
}

if (occasionSelect) {
    occasionSelect.addEventListener('change', syncBirthdayFields);
    syncBirthdayFields();
}
