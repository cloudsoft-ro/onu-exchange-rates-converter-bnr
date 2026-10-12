/* eslint-disable camelcase */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	ToggleControl,
	RangeControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function ChartEdit( { attributes, setAttributes } ) {
	const {
		valuta,
		perioada,
		valute,
		comutator_perioade,
		comutator_valute,
		titlu,
		inaltime,
		tema,
		densitate,
	} = attributes;

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Chart Configuration',
						'curs-bnr-convertor-valutar'
					) }
					initialOpen={ true }
				>
					<TextControl
						label={ __(
							'Default Currency',
							'curs-bnr-convertor-valutar'
						) }
						value={ valuta }
						onChange={ ( value ) =>
							setAttributes( { valuta: value.toUpperCase() } )
						}
						placeholder="EUR"
						help={ __(
							'3-letter currency code, e.g. EUR, USD, GBP, CHF.',
							'curs-bnr-convertor-valutar'
						) }
					/>

					<SelectControl
						label={ __(
							'Default Time Period',
							'curs-bnr-convertor-valutar'
						) }
						value={ perioada }
						options={ [
							{
								label: __(
									'Last 7 Days (7 zile)',
									'curs-bnr-convertor-valutar'
								),
								value: '7zile',
							},
							{
								label: __(
									'Last 1 Month (1 lună)',
									'curs-bnr-convertor-valutar'
								),
								value: '1luna',
							},
							{
								label: __(
									'Last 3 Months (3 luni)',
									'curs-bnr-convertor-valutar'
								),
								value: '3luni',
							},
							{
								label: __(
									'Last 1 Year (1 an)',
									'curs-bnr-convertor-valutar'
								),
								value: '1an',
							},
							{
								label: __(
									'Last 5 Years (5 ani)',
									'curs-bnr-convertor-valutar'
								),
								value: '5ani',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { perioada: value } )
						}
					/>

					<ToggleControl
						label={ __(
							'Show period switcher buttons',
							'curs-bnr-convertor-valutar'
						) }
						checked={ comutator_perioade }
						onChange={ ( value ) =>
							setAttributes( { comutator_perioade: value } )
						}
						help={ __(
							'Allows visitors to toggle between 7 days, 1 month, 3 months, 1 year, and 5 years.',
							'curs-bnr-convertor-valutar'
						) }
					/>

					<ToggleControl
						label={ __(
							'Allow switching currency',
							'curs-bnr-convertor-valutar'
						) }
						checked={ comutator_valute }
						onChange={ ( value ) =>
							setAttributes( { comutator_valute: value } )
						}
					/>

					{ comutator_valute && (
						<TextControl
							label={ __(
								'Allowed currencies (comma separated)',
								'curs-bnr-convertor-valutar'
							) }
							value={ valute }
							onChange={ ( value ) =>
								setAttributes( { valute: value.toUpperCase() } )
							}
							placeholder="EUR,USD,GBP,CHF"
						/>
					) }

					<TextControl
						label={ __(
							'Custom Title (optional)',
							'curs-bnr-convertor-valutar'
						) }
						value={ titlu }
						onChange={ ( value ) =>
							setAttributes( { titlu: value } )
						}
						placeholder={ __(
							'E.g. Evoluție Curs Euro',
							'curs-bnr-convertor-valutar'
						) }
					/>

					<RangeControl
						label={ __(
							'Chart Height (px)',
							'curs-bnr-convertor-valutar'
						) }
						value={ inaltime || 300 }
						onChange={ ( value ) =>
							setAttributes( { inaltime: value } )
						}
						min={ 180 }
						max={ 500 }
						step={ 10 }
					/>

					<SelectControl
						label={ __( 'Theme', 'curs-bnr-convertor-valutar' ) }
						value={ tema }
						options={ [
							{
								label: __(
									'Default (Global Setting)',
									'curs-bnr-convertor-valutar'
								),
								value: 'implicit',
							},
							{
								label: __(
									'Light',
									'curs-bnr-convertor-valutar'
								),
								value: 'light',
							},
							{
								label: __(
									'Dark',
									'curs-bnr-convertor-valutar'
								),
								value: 'dark',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { tema: value } )
						}
					/>

					<SelectControl
						label={ __(
							'Density / Padding',
							'curs-bnr-convertor-valutar'
						) }
						value={ densitate }
						options={ [
							{
								label: __(
									'Default (Global Setting)',
									'curs-bnr-convertor-valutar'
								),
								value: 'implicit',
							},
							{
								label: __(
									'Compact (Narrow/Sidebar)',
									'curs-bnr-convertor-valutar'
								),
								value: 'compact',
							},
							{
								label: __(
									'Normal',
									'curs-bnr-convertor-valutar'
								),
								value: 'normal',
							},
							{
								label: __(
									'Spacious (Loose)',
									'curs-bnr-convertor-valutar'
								),
								value: 'spacious',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { densitate: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<ServerSideRender
				block="curs-bnr-convertor-valutar/grafic-valutar"
				attributes={ attributes }
			/>
		</div>
	);
}
