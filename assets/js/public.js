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

var restaurantInputs = document.querySelectorAll('input[name="restaurant_id"]');
var environmentSelect = document.querySelector('select[name="environment_id"]');

function syncEnvironmentOptions() {
    if (!environmentSelect || !restaurantInputs.length) {
        return;
    }
    var selectedRestaurant = document.querySelector('input[name="restaurant_id"]:checked');
    var restaurantId = selectedRestaurant ? selectedRestaurant.value : '';
    Array.prototype.forEach.call(environmentSelect.options, function (option) {
        var optionRestaurant = option.getAttribute('data-restaurant');
        option.hidden = optionRestaurant && optionRestaurant !== restaurantId;
    });
    if (environmentSelect.selectedOptions.length && environmentSelect.selectedOptions[0].hidden) {
        environmentSelect.value = '';
    }
}

Array.prototype.forEach.call(restaurantInputs, function (input) {
    input.addEventListener('change', syncEnvironmentOptions);
});
syncEnvironmentOptions();
