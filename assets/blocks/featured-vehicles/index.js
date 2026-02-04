/**
 * Featured Vehicles Block
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl, ToggleControl, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

registerBlockType(metadata.name, {
    edit: ({ attributes, setAttributes }) => {
        const blockProps = useBlockProps();

        return (
            <div {...blockProps}>
                <InspectorControls>
                    <PanelBody title="General Settings">
                        <TextControl
                            label="Title"
                            value={attributes.title}
                            onChange={(value) => setAttributes({ title: value })}
                        />
                        <SelectControl
                            label="Layout"
                            value={attributes.layout}
                            options={[
                                { label: 'Slider', value: 'slider' },
                                { label: 'Grid', value: 'grid' },
                            ]}
                            onChange={(value) => setAttributes({ layout: value })}
                        />
                        <RangeControl
                            label="Limit"
                            value={parseInt(attributes.limit)}
                            onChange={(value) => setAttributes({ limit: String(value) })}
                            min={1}
                            max={20}
                        />
                        <RangeControl
                            label="Columns (Grid/Slider View)"
                            value={parseInt(attributes.columns)}
                            onChange={(value) => setAttributes({ columns: String(value) })}
                            min={1}
                            max={6}
                        />
                    </PanelBody>
                    <PanelBody title="Filtering" initialOpen={false}>
                        <TextControl
                            label="Category Slug"
                            value={attributes.category}
                            onChange={(value) => setAttributes({ category: value })}
                            help="Enter vehicle category slug to filter."
                        />
                        <TextControl
                            label="Vehicle IDs"
                            value={attributes.ids}
                            onChange={(value) => setAttributes({ ids: value })}
                            help="Comma separated list of specific vehicle IDs."
                        />
                    </PanelBody>
                    {attributes.layout === 'slider' && (
                        <PanelBody title="Slider Settings" initialOpen={false}>
                            <ToggleControl
                                label="Autoplay"
                                checked={attributes.autoplay === '1'}
                                onChange={(value) => setAttributes({ autoplay: value ? '1' : '0' })}
                            />
                            <TextControl
                                label="Interval (ms)"
                                value={attributes.interval}
                                onChange={(value) => setAttributes({ interval: value })}
                            />
                        </PanelBody>
                    )}
                </InspectorControls>

                <ServerSideRender
                    block={metadata.name}
                    attributes={attributes}
                />
            </div>
        );
    },
    save: () => null, // Dynamic block
});
