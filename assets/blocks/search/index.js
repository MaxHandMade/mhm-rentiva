(function (blocks, element, components, serverSideRender, blockEditor) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var TextControl = components.TextControl;
    var __experimentalUnitControl = components.__experimentalUnitControl;

    var ServerSideRender = serverSideRender.default || serverSideRender;

    blocks.registerBlockType('mhm-rentiva/search', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            // Apply dimensions to the wrapper in the editor
            var blockProps = useBlockProps({
                style: {
                    minWidth: attributes.minWidth,
                    maxWidth: attributes.maxWidth,
                    height: attributes.height
                }
            });

            var useEffect = element.useEffect;

            useEffect(function () {
                var iframe = document.querySelector('iframe[name="editor-canvas"]');
                var doc = iframe ? iframe.contentDocument : document;
                if (!doc) return;

                var fixInputs = function () {
                    var inputs = doc.querySelectorAll('.js-datepicker');
                    inputs.forEach(function (input) {
                        if (input.type !== 'date') { input.type = 'date'; }
                        input.removeAttribute('readonly');
                        input.removeAttribute('disabled');
                        input.classList.remove('hasDatepicker');
                        input.style.pointerEvents = 'all';

                        var manualOpen = function (e) {
                            e.stopPropagation();
                            input.removeAttribute('readonly');
                            setTimeout(function () {
                                if (typeof input.showPicker === 'function') {
                                    try { input.showPicker(); } catch (err) { input.focus(); }
                                } else {
                                    input.focus();
                                }
                            }, 50);
                        };

                        input.removeEventListener('mousedown', manualOpen);
                        input.addEventListener('mousedown', manualOpen);
                    });
                };

                var observer = new MutationObserver(fixInputs);
                observer.observe(doc.body, { childList: true, subtree: true });
                fixInputs();

                return function () { observer.disconnect(); };
            }, [attributes.layout]);

            return el('div', blockProps,
                el(InspectorControls, { key: 'controls' },
                    el(PanelBody, { title: 'General Settings', initialOpen: true },
                        el(SelectControl, {
                            label: 'Layout',
                            value: attributes.layout,
                            options: [
                                { label: 'Compact (Horizontal)', value: 'compact' },
                                { label: 'Full (Vertical)', value: 'full' }
                            ],
                            onChange: function (val) { setAttributes({ layout: val }); },
                            __nextHasNoMarginBottom: true,
                            __next40pxDefaultSize: true
                        }),
                        el(TextControl, {
                            label: 'Redirect Page URL (Optional)',
                            value: attributes.redirect_page,
                            onChange: function (val) { setAttributes({ redirect_page: val }); },
                            __nextHasNoMarginBottom: true,
                            __next40pxDefaultSize: true
                        })
                    ),
                    el(PanelBody, { title: 'Dimensions (Manual Fix)', initialOpen: true },
                        el(__experimentalUnitControl || TextControl, {
                            label: 'Height (e.g. 500px, 50vh)',
                            value: attributes.height,
                            onChange: function (val) { setAttributes({ height: val }); },
                            __nextHasNoMarginBottom: true,
                            __next40pxDefaultSize: true
                        }),
                        el(__experimentalUnitControl || TextControl, {
                            label: 'Min Width (e.g. 300px)',
                            value: attributes.minWidth,
                            onChange: function (val) { setAttributes({ minWidth: val }); },
                            __nextHasNoMarginBottom: true,
                            __next40pxDefaultSize: true
                        }),
                        el(__experimentalUnitControl || TextControl, {
                            label: 'Max Width (e.g. 1200px)',
                            value: attributes.maxWidth,
                            onChange: function (val) { setAttributes({ maxWidth: val }); },
                            __nextHasNoMarginBottom: true,
                            __next40pxDefaultSize: true
                        })
                    ),
                    el(PanelBody, { title: 'Filter Options', initialOpen: false },
                        el(ToggleControl, {
                            label: 'Show Date Picker',
                            checked: attributes.show_date_picker === '1',
                            onChange: function (val) { setAttributes({ show_date_picker: val ? '1' : '0' }); },
                            __nextHasNoMarginBottom: true
                        }),
                        el(ToggleControl, {
                            label: 'Show Price Range',
                            checked: attributes.show_price_range === '1',
                            onChange: function (val) { setAttributes({ show_price_range: val ? '1' : '0' }); },
                            __nextHasNoMarginBottom: true
                        }),
                        el(ToggleControl, {
                            label: 'Show Fuel Type',
                            checked: attributes.show_fuel_type === '1',
                            onChange: function (val) { setAttributes({ show_fuel_type: val ? '1' : '0' }); },
                            __nextHasNoMarginBottom: true
                        }),
                        el(ToggleControl, {
                            label: 'Show Transmission',
                            checked: attributes.show_transmission === '1',
                            onChange: function (val) { setAttributes({ show_transmission: val ? '1' : '0' }); },
                            __nextHasNoMarginBottom: true
                        }),
                        el(ToggleControl, {
                            label: 'Show Seat Count',
                            checked: attributes.show_seats === '1',
                            onChange: function (val) { setAttributes({ show_seats: val ? '1' : '0' }); },
                            __nextHasNoMarginBottom: true
                        })
                    )
                ),
                el(ServerSideRender, {
                    key: 'render',
                    block: 'mhm-rentiva/search',
                    attributes: attributes
                })
            );
        },
        save: function () { return null; }
    });
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.serverSideRender, window.wp.blockEditor));
