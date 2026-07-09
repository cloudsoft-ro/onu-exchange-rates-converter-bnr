import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes, setAttributes }) {
  const { valute, stil, arata_variatie } = attributes;

  return (
    <div {...useBlockProps()}>
      <InspectorControls>
          <PanelBody title={__('BNR Exchange Rates Settings', 'onu-rates-converter-for-bnr')} initialOpen={true}>
            <TextControl
              label={__('Currencies to display (comma separated)', 'onu-rates-converter-for-bnr')}
              value={valute}
              onChange={(value) => setAttributes({ valute: value })}
              placeholder="EUR,USD,GBP,CHF"
              help={__('Leave empty to use default currencies configured in plugin settings.', 'onu-rates-converter-for-bnr')}
            />
            <SelectControl
              label={__('Display style', 'onu-rates-converter-for-bnr')}
              value={stil}
              options={[
                { label: __('Table', 'onu-rates-converter-for-bnr'), value: 'tabel' },
                { label: __('Cards', 'onu-rates-converter-for-bnr'), value: 'card' },
                { label: __('Inline', 'onu-rates-converter-for-bnr'), value: 'inline' },
              ]}
              onChange={(value) => setAttributes({ stil: value })}
            />
            <ToggleControl
              label={__('Show variation', 'onu-rates-converter-for-bnr')}
              checked={arata_variatie}
              onChange={(value) => setAttributes({ arata_variatie: value })}
            />
        </PanelBody>
      </InspectorControls>

      <ServerSideRender
        block="onu-rates-converter-for-bnr/curs-valutar"
        attributes={attributes}
      />
    </div>
  );
}
