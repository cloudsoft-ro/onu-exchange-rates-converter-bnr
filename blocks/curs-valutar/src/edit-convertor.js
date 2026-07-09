import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes, setAttributes }) {
  const { de_la, catre } = attributes;

  return (
    <div {...useBlockProps()}>
      <InspectorControls>
        <PanelBody title={__('Converter Settings', 'onu-rates-converter-for-bnr')} initialOpen={true}>
          <TextControl
            label={__('From (source currency)', 'onu-rates-converter-for-bnr')}
            value={de_la}
            onChange={(value) => setAttributes({ de_la: value.toUpperCase() })}
            placeholder="EUR"
          />
          <TextControl
            label={__('To (target currency)', 'onu-rates-converter-for-bnr')}
            value={catre}
            onChange={(value) => setAttributes({ catre: value.toUpperCase() })}
            placeholder="RON"
          />
        </PanelBody>
      </InspectorControls>

      <ServerSideRender
        block="onu-rates-converter-for-bnr/convertor-valutar"
        attributes={attributes}
      />
    </div>
  );
}
