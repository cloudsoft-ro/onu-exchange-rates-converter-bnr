import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes, setAttributes }) {
  const { de_la, catre, tema, densitate } = attributes;

  return (
    <div {...useBlockProps()}>
      <InspectorControls>
        <PanelBody title={__('Converter Settings', 'curs-bnr-convertor-valutar')} initialOpen={true}>
          <TextControl
            label={__('From (source currency)', 'curs-bnr-convertor-valutar')}
            value={de_la}
            onChange={(value) => setAttributes({ de_la: value.toUpperCase() })}
            placeholder="EUR"
          />
          <TextControl
            label={__('To (target currency)', 'curs-bnr-convertor-valutar')}
            value={catre}
            onChange={(value) => setAttributes({ catre: value.toUpperCase() })}
            placeholder="RON"
          />
          <SelectControl
            label={__('Theme', 'curs-bnr-convertor-valutar')}
            value={tema}
            options={[
              { label: __('Default (Global Setting)', 'curs-bnr-convertor-valutar'), value: 'implicit' },
              { label: __('Light', 'curs-bnr-convertor-valutar'), value: 'light' },
              { label: __('Dark', 'curs-bnr-convertor-valutar'), value: 'dark' },
            ]}
            onChange={(value) => setAttributes({ tema: value })}
          />
          <SelectControl
            label={__('Layout Style / Density', 'curs-bnr-convertor-valutar')}
            value={densitate}
            options={[
              { label: __('Default (Global Setting)', 'curs-bnr-convertor-valutar'), value: 'implicit' },
              { label: __('Compact (Narrow)', 'curs-bnr-convertor-valutar'), value: 'compact' },
              { label: __('Normal', 'curs-bnr-convertor-valutar'), value: 'normal' },
              { label: __('Spacious (Loose)', 'curs-bnr-convertor-valutar'), value: 'spacious' },
            ]}
            onChange={(value) => setAttributes({ densitate: value })}
          />
        </PanelBody>
      </InspectorControls>

      <ServerSideRender
        block="curs-bnr-convertor-valutar/convertor-valutar"
        attributes={attributes}
      />
    </div>
  );
}
