import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes, setAttributes }) {
  const { valute, stil, arata_variatie, tema, densitate } = attributes;

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
        block="curs-bnr-convertor-valutar/curs-valutar"
        attributes={attributes}
      />
    </div>
  );
}
