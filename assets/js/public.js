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
var reservationDate = document.querySelector('input[name="reservation_date"]');
var reservationTime = document.getElementById('reservationTime');
var reservationDetails = document.getElementById('reservationDetails');
var selectedRestaurantPill = document.getElementById('selectedRestaurantPill');
var selectedRestaurantSummary = document.getElementById('selectedRestaurantSummary');

function syncRestaurantShowcase(scrollToDetails) {
    var selectedRestaurant = document.querySelector('input[name="restaurant_id"]:checked');
    Array.prototype.forEach.call(document.querySelectorAll('.restaurant-showcase-card'), function (card) {
        var input = card.querySelector('input[name="restaurant_id"]');
        var selected = input && selectedRestaurant && input.value === selectedRestaurant.value;
        var label = card.querySelector('.restaurant-select-label');
        card.classList.toggle('selected', selected);
        if (label) {
            label.textContent = selected ? label.getAttribute('data-selected-label') : label.getAttribute('data-default-label');
        }
    });
    if (selectedRestaurantPill && selectedRestaurant) {
        var card = selectedRestaurant.closest('.restaurant-showcase-card');
        var name = card ? card.querySelector('.restaurant-showcase-content strong') : null;
        selectedRestaurantPill.textContent = name ? 'Selecionado: ' + name.textContent : '';
        if (selectedRestaurantSummary && card) {
            var logo = card.querySelector('.restaurant-logo.featured');
            var address = card.querySelector('.restaurant-showcase-content em');
            var meta = card.querySelector('.restaurant-meta');
            selectedRestaurantSummary.innerHTML = ''
                + '<div class="selected-restaurant-logo">' + (logo ? logo.innerHTML : '') + '</div>'
                + '<div><strong>' + (name ? name.textContent : '') + '</strong>'
                + (address ? '<p>' + address.textContent + '</p>' : '')
                + (meta ? '<small>' + meta.textContent.replace(/\s+/g, ' ').trim() + '</small>' : '')
                + '</div>';
        }
    }
    if (scrollToDetails && reservationDetails) {
        reservationDetails.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
}

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
    input.addEventListener('change', syncTimeOptions);
    input.addEventListener('change', function () {
        syncRestaurantShowcase(true);
    });
});
syncEnvironmentOptions();
syncRestaurantShowcase(false);

function timeToMinutes(time) {
    var parts = time.split(':');
    return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
}

function minutesToTime(minutes) {
    var hours = String(Math.floor(minutes / 60)).padStart(2, '0');
    var mins = String(minutes % 60).padStart(2, '0');
    return hours + ':' + mins;
}

function syncTimeOptions() {
    if (!reservationDate || !reservationTime) {
        return;
    }
    var selectedRestaurant = document.querySelector('input[name="restaurant_id"]:checked');
    var restaurantId = selectedRestaurant ? selectedRestaurant.value : '';
    var dateValue = reservationDate.value;
    reservationTime.innerHTML = '';

    if (!restaurantId || !dateValue) {
        reservationTime.append(new Option('Escolha restaurante e data', ''));
        return;
    }

    var weekday = new Date(dateValue + 'T12:00:00').getDay();
    var periods = window.restaurantAvailability
        && window.restaurantAvailability[restaurantId]
        && window.restaurantAvailability[restaurantId][weekday]
        ? window.restaurantAvailability[restaurantId][weekday]
        : [];

    if (!periods.length) {
        reservationTime.append(new Option('Restaurante fechado neste dia', ''));
        return;
    }

    reservationTime.append(new Option('Selecione um horário', ''));
    periods.forEach(function (period) {
        var label = period.period === 'lunch' ? 'Almoço' : 'Jantar';
        var group = document.createElement('optgroup');
        group.label = label + ' (' + period.open + ' - ' + period.close + ')';
        for (var minutes = timeToMinutes(period.open); minutes <= timeToMinutes(period.close); minutes += 30) {
            var time = minutesToTime(minutes);
            group.append(new Option(time, time));
        }
        reservationTime.append(group);
    });
}

if (reservationDate) {
    reservationDate.addEventListener('change', syncTimeOptions);
}
syncTimeOptions();
