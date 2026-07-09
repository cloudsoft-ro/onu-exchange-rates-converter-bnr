import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes, setAttributes }) {
  const { valute, stil, arata_variatie } = attributes;

  return (
    <div {...useBlockProps()}>
      <InspectorControls>
          <PanelBody title={__('BNR Exchange Rates Settings', 'curs-bnr-convertor-valutar')} initialOpen={true}>
            <TextControl
              label={__('Currencies to display (comma separated)', 'curs-bnr-convertor-valutar')}
              value={valute}
              onChange={(value) => setAttributes({ valute: value })}
              placeholder="EUR,USD,GBP,CHF"
              help={__('Leave empty to use default currencies configured in plugin settings.', 'curs-bnr-convertor-valutar')}
            />
            <SelectControl
              label={__('Display style', 'curs-bnr-convertor-valutar')}
              value={stil}
              options={[
                { label: __('Table', 'curs-bnr-convertor-valutar'), value: 'tabel' },
                { label: __('Cards', 'curs-bnr-convertor-valutar'), value: 'card' },
                { label: __('Inline', 'curs-bnr-convertor-valutar'), value: 'inline' },
              ]}
              onChange={(value) => setAttributes({ stil: value })}
            />
            <ToggleControl
              label={__('Show variation', 'curs-bnr-convertor-valutar')}
              checked={arata_variatie}
              onChange={(value) => setAttributes({ arata_variatie: value })}
            />
        </PanelBody>
      </InspectorControls>

      <ServerSideRender
        block="curs-bnr-convertor-valutar/curs-valutar"
        attributes={attributes}
      />
    </div>
  );
}
