/**
 * External Dependencies
 */
import { EntityCreateNewModal } from '@prc/components';

/**
 * Internal Dependencies
 */
import { createBlockModule } from '../../functions';

/**
 * @param {Object}   props
 * @param {string}   props.defaultTitle
 * @param {number}   props.blockAreaId
 * @param {number}   props.categoryId
 * @param {Function} props.onCreate
 * @param {Function} props.setAttributes
 */
export default function CreateA({
	defaultTitle = 'Block Module',
	blockAreaId,
	categoryId,
	onCreate = () => {},
	setAttributes,
}) {
	return (
		<EntityCreateNewModal
			defaultTitle={defaultTitle}
			onClose={() => {
				setAttributes({ wizardStep: 'intro' });
			}}
			onSubmit={(newTitle) => {
				createBlockModule(
					newTitle,
					blockAreaId,
					categoryId,
					'publish'
				).then((response) => {
					onCreate(response.id);
				});
			}}
		/>
	);
}
