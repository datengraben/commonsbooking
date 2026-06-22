/**
 * Editor script for the commonsbooking/bookings block.
 *
 * Authored without JSX so it needs no extra build step: it relies on the
 * WordPress packages shipped by core (wp-blocks, wp-element, wp-block-editor,
 * wp-components, wp-server-side-render, wp-i18n), declared as dependencies when
 * the script is registered in PHP.
 *
 * The block is dynamic: it has no client-side save output (save returns null)
 * and is rendered on the server via the render_callback. In the editor we show
 * the real markup through ServerSideRender.
 */
(function (blocks, element, blockEditor, components, ServerSideRender, i18n) {
    'use strict';

    var el = element.createElement;
    var __ = i18n.__;

    blocks.registerBlockType('commonsbooking/bookings', {
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var inspector = el(
                blockEditor.InspectorControls,
                { key: 'inspector' },
                el(
                    components.PanelBody,
                    { title: __('Settings', 'commonsbooking'), initialOpen: true },
                    el(components.RangeControl, {
                        label: __('Bookings per page', 'commonsbooking'),
                        value: attributes.postsPerPage,
                        min: 1,
                        max: 50,
                        onChange: function (value) {
                            setAttributes({ postsPerPage: value });
                        },
                    }),
                    el(components.ToggleControl, {
                        label: __('Show filters', 'commonsbooking'),
                        checked: attributes.showFilters,
                        onChange: function (value) {
                            setAttributes({ showFilters: value });
                        },
                    }),
                ),
            );

            var preview = el(ServerSideRender, {
                key: 'preview',
                block: 'commonsbooking/bookings',
                attributes: attributes,
            });

            return [inspector, preview];
        },

        // Dynamic block: rendered on the server.
        save: function () {
            return null;
        },
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender,
    window.wp.i18n,
);
