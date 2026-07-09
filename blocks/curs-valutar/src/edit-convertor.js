import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes, setAttributes }) {
  const { de_la, catre } = attributes;

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
        </PanelBody>
      </InspectorControls>

      <ServerSideRender
        block="curs-bnr-convertor-valutar/convertor-valutar"
        attributes={attributes}
      />
    </div>
  );
}
