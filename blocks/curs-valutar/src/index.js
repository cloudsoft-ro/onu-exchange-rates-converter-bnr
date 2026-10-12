import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import cursMetadata from '../block.json';
import convertorMetadata from '../../convertor-valutar/block.json';
import chartMetadata from '../../grafic-valutar/block.json';
import CursEdit from './edit';
import ConvertorEdit from './edit-convertor';
import ChartEdit from './edit-chart';
import save from './save';

// Custom SVG icon for the currency converter block
const convertorIcon = createElement(
	'svg',
	{
		viewBox: '0 0 24 24',
		xmlns: 'http://www.w3.org/2000/svg',
		fill: 'none',
		stroke: 'currentColor',
		strokeWidth: '2',
		strokeLinecap: 'round',
		strokeLinejoin: 'round',
	},
	createElement( 'path', { d: 'M7 16V4m0 0L3 8m4-4l4 4' } ),
	createElement( 'path', { d: 'M17 8v12m0 0l4-4m-4 4l-4-4' } )
);

// Custom SVG icon for the chart block
const chartIcon = createElement(
	'svg',
	{
		viewBox: '0 0 24 24',
		xmlns: 'http://www.w3.org/2000/svg',
		fill: 'none',
		stroke: 'currentColor',
		strokeWidth: '2',
		strokeLinecap: 'round',
		strokeLinejoin: 'round',
	},
	createElement( 'line', { x1: '18', y1: '20', x2: '18', y2: '10' } ),
	createElement( 'line', { x1: '12', y1: '20', x2: '12', y2: '4' } ),
	createElement( 'line', { x1: '6', y1: '20', x2: '6', y2: '14' } ),
	createElement( 'path', { d: 'M4 20h16' } )
);

registerBlockType( cursMetadata.name, {
	...cursMetadata,
	edit: CursEdit,
	save,
} );

registerBlockType( convertorMetadata.name, {
	...convertorMetadata,
	icon: convertorIcon,
	edit: ConvertorEdit,
	save,
} );

registerBlockType( chartMetadata.name, {
	...chartMetadata,
	icon: chartIcon,
	edit: ChartEdit,
	save,
} );
