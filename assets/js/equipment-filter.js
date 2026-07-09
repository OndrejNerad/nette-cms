import $ from 'jquery';

$(function () {

    const $input = $('#equipment-search');
    const $dropdown = $('#equipment-dropdown');
    const $chips = $('#equipment-chips');

    if (!$input.length || !$dropdown.length || !$chips.length || typeof window.EQUIPMENT_DATA === 'undefined') {
        return;
    }

    const MAX_RESULTS = 20;

    const selectedIds = () => $chips.find('.chip').map((i, chip) => String(chip.dataset.id)).get();

    const addChip = (item) => {
        const $chip = $('<span class="chip"></span>').attr('data-id', item.id);
        $chip.append(document.createTextNode(item.title));
        $chip.append('<i class="remove">&times;</i>');
        $chip.append($('<input type="hidden" name="equipment[]">').val(item.id));
        $chips.append($chip);
    };

    const renderDropdown = (items) => {
        $dropdown.empty();

        if (!items.length) {
            $dropdown.removeClass('open');
            return;
        }

        items.forEach((item) => {
            $('<div class="equipment-dropdown-item"></div>')
                .text(item.title)
                .attr('data-id', item.id)
                .attr('data-title', item.title)
                .appendTo($dropdown);
        });

        $dropdown.addClass('open');
    };

    const showPopular = () => {
        const selected = selectedIds();
        renderDropdown(window.EQUIPMENT_DATA.popular.filter((item) => !selected.includes(String(item.id))));
    };

    const search = (query) => {
        const selected = selectedIds();
        const needle = query.toLowerCase();

        const items = window.EQUIPMENT_DATA.all
            .filter((item) => !selected.includes(String(item.id)) && item.title.toLowerCase().includes(needle))
            .slice(0, MAX_RESULTS);

        renderDropdown(items);
    };

    const refresh = () => {
        const query = $input.val().trim();
        if (query === '') {
            showPopular();
        } else {
            search(query);
        }
    };

    $input.on('focus input', refresh);

    $dropdown.on('click', '.equipment-dropdown-item', (e) => {
        addChip({ id: e.currentTarget.dataset.id, title: e.currentTarget.dataset.title });
        $input.val('').trigger('focus');
        refresh();
    });

    $chips.on('click', '.remove', (e) => {
        $(e.currentTarget).closest('.chip').remove();
        refresh();
    });

    $(document).on('click', (e) => {
        if (!$(e.target).closest('.equipment-filter').length) {
            $dropdown.removeClass('open');
        }
    });

});
