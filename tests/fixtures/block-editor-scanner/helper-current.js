    var yesNoToggle = function (label, key, attributes, setAttributes) {
        return el(ToggleControl, {
            label: label,
            checked: attributes[key] === 'yes',
            onChange: function (val) {
                var update = {};
                update[key] = val ? 'yes' : 'no';
                setAttributes(update);
            }
        });
    };
    var setAttributes = props.setAttributes;
    yesNoToggle(__('Filter bar', 'mhm-rentiva'), 'show_filter_bar', attributes, setAttributes);
