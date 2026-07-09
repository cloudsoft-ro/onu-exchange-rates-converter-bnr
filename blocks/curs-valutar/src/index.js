import { registerBlockType } from '@wordpress/blocks';
import cursMetadata from '../block.json';
import convertorMetadata from '../../convertor-valutar/block.json';
import CursEdit from './edit';
import ConvertorEdit from './edit-convertor';
import save from './save';

registerBlockType(cursMetadata.name, {
  ...cursMetadata,
  edit: CursEdit,
  save,
});

registerBlockType(convertorMetadata.name, {
  ...convertorMetadata,
  edit: ConvertorEdit,
  save,
});
